<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2026 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

namespace Glpi\ImportExport;

use Closure;
use CommonDBChild;
use CommonDBTM;
use Entity;
use Glpi\Error\ErrorHandler;
use Glpi\ImportExport\Context\DatabaseMapper;
use Glpi\ImportExport\Result\ExportResult;
use Glpi\ImportExport\Result\ImportError;
use Glpi\ImportExport\Result\ImportResult;
use Glpi\ImportExport\Result\ImportResultIssues;
use Glpi\ImportExport\Result\ImportResultPreview;
use Glpi\ImportExport\Specification\DataRequirementSpecification;
use InvalidArgumentException;
use RuntimeException;
use Session;
use Throwable;
use Toolbox;

use function Safe\base64_decode;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\preg_replace;

/**
 * Exports and imports any {@see ExportableInterface} itemtype from its
 * {@see ExportSchema}.
 *
 * File format:
 * `{"type": itemtype, "version": n, "items": [{"id", "name", "fields", "children": {itemtype: [{"fields", "children"}]}}]}`
 *
 * Only ids are turned into names; the data requirements are computed again
 * from the schema on import, so the file holds no duplicated information.
 *
 * @phpstan-type Node array{id?: int, name?: string, fields: array<string, mixed>, children?: array<string, list<mixed>>}
 */
final class SchemaSerializer implements ImportExportHandlerInterface
{
    private const IGNORED_COLUMNS = ['id', 'date_creation', 'date_mod'];

    private readonly ExportSchema $schema;

    /**
     * @param class-string<CommonDBTM> $itemtype
     * @param ?ExportSchema $schema Defaults to the itemtype's own schema.
     */
    public function __construct(private readonly string $itemtype, ?ExportSchema $schema = null)
    {
        $this->schema = $schema ?? (is_a($itemtype, ExportableInterface::class, true)
            ? $itemtype::getExportSchema()
            : throw new InvalidArgumentException("$itemtype has no export schema."));
    }

    public function exportItems(array $items): ExportResult
    {
        $nodes = [];
        foreach ($items as $item) {
            $row = $this->getRows($item::getTable(), ['id' => $item->getID()])[0];
            $nodes[] = ['id' => $item->getID(), 'name' => $item->getFriendlyName()]
                + $this->exportNode($this->itemtype, $this->schema, $row, []);
        }

        $json = json_encode(
            ['type' => $this->itemtype, 'version' => $this->schema->version, 'items' => $nodes],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        return new ExportResult($this->getFileName($items), $json);
    }

    public function previewImport(string $json, DatabaseMapper $mapper, array $skipped_items = []): ImportResultPreview
    {
        $preview = new ImportResultPreview();
        foreach ($this->readItems($json) as $node) {
            if (in_array($node['id'], $skipped_items)) {
                $preview->addSkippedItem($node['id'], $node['name']);
                continue;
            }
            foreach ($this->getFatalErrors($node) as $error) {
                $preview->addFatalError($node['id'], $node['name'], $error);
            }
            if ($preview->hasFatalError($node['id'])) {
                continue;
            }

            $requirements = $this->getRequirements($node);
            $mapper->mapExistingItemsForRequirements($requirements);
            if (!$mapper->validateRequirements($requirements)) {
                $preview->addInvalidItem($node['id'], $node['name']);
                continue;
            }
            foreach ($this->check($node, $mapper) as $error) {
                $preview->addFatalError($node['id'], $node['name'], $error);
            }
            if (!$preview->hasFatalError($node['id'])) {
                $preview->addValidItem($node['id'], $node['name']);
            }
        }
        return $preview;
    }

    public function listIssues(DatabaseMapper $mapper, string $json): ImportResultIssues
    {
        $issues = new ImportResultIssues();
        foreach ($this->readItems($json) as $node) {
            $requirements = $this->getRequirements($node);
            $mapper->mapExistingItemsForRequirements($requirements);
            $issues->addIssues($node['id'], $mapper->getInvalidRequirements($requirements));
        }
        return $issues;
    }

    public function importItems(string $json, DatabaseMapper $mapper, array $skipped_items = []): ImportResult
    {
        global $DB;

        $result = new ImportResult();
        foreach ($this->readItems($json) as $node) {
            if (in_array($node['id'], $skipped_items)) {
                continue;
            }
            if ($this->getFatalErrors($node) !== []) {
                $result->addFailedImport($node['name'], ImportError::FATAL_ERROR);
                continue;
            }
            $requirements = $this->getRequirements($node);
            $mapper->mapExistingItemsForRequirements($requirements);
            if (!$mapper->validateRequirements($requirements)) {
                $result->addFailedImport($node['name'], ImportError::MISSING_DATA_REQUIREMENT);
                continue;
            }
            if ($this->check($node, $mapper) !== []) {
                $result->addFailedImport($node['name'], ImportError::FATAL_ERROR);
                continue;
            }

            $DB->beginTransaction();
            try {
                $item = $this->importNode($this->itemtype, $this->schema, $node, $mapper, []);
                $notes = $this->schema->after_import === null ? [] : ($this->schema->after_import)($item, $node['fields']);
                $DB->commit();
                $result->addImportedItem($item, $notes);
            } catch (Throwable $e) {
                $DB->rollBack();
                ErrorHandler::logCaughtException($e);
                $result->addFailedImport($node['name'], ImportError::INVALID_DATA);
            }
        }
        return $result;
    }

    /**
     * @param class-string<CommonDBTM> $itemtype
     * @param array<string, mixed> $row
     * @param array<string, mixed> $parent_keys
     * @return Node
     */
    private function exportNode(string $itemtype, ExportSchema $schema, array $row, array $parent_keys): array
    {
        $fields = $this->filterColumns($schema, $schema->prepare_export !== null ? ($schema->prepare_export)($row) : $row, $parent_keys);
        foreach ($schema->json as $column) {
            // Values added by `prepare_export` are not stored, so not encoded.
            if (is_string($fields[$column] ?? null) && array_key_exists($column, $row)) {
                $fields[$column] = json_decode($fields[$column], true);
            }
        }
        foreach ($schema->pictures as $column) {
            $fields[$column] = $this->exportPicture($fields[$column] ?? null);
        }
        $node = ['fields' => $this->convertReferences($schema, $fields, $this->exportReference(...))];

        foreach ($schema->children as $child_itemtype => $child_schema) {
            $keys = $this->getChildKeys($itemtype, $child_itemtype, $row['id']);
            foreach ($this->getRows($child_itemtype::getTable(), $keys) as $child_row) {
                $node['children'][$child_itemtype][] = $this->exportNode($child_itemtype, $child_schema, $child_row, $keys);
            }
        }
        return $node;
    }

    /**
     * @param class-string<CommonDBTM> $itemtype
     * @param Node $node
     * @param array<string, mixed> $parent_keys
     */
    private function importNode(string $itemtype, ExportSchema $schema, array $node, DatabaseMapper $mapper, array $parent_keys): CommonDBTM
    {
        $input = $this->toInput($itemtype, $schema, $node['fields'], $mapper, $parent_keys);
        foreach ($schema->pictures as $column) {
            $input = $this->importPicture($input, $column);
        }
        $input = $parent_keys + $input;
        if ($schema->prepare_input !== null) {
            $input = ($schema->prepare_input)($input);
        }

        // Replacements are posted by the browser: an entity must not be forced.
        if (isset($input['entities_id']) && !Session::haveAccessToEntity((int) $input['entities_id'])) {
            throw new RuntimeException(sprintf('No access to the entity of %s "%s".', $itemtype, $node['name'] ?? ''));
        }
        $item = getItemForItemtype($itemtype);
        if ($item === false || $item->add($input) === false) {
            throw new RuntimeException(sprintf('Unable to import %s "%s".', $itemtype, $node['name'] ?? ''));
        }

        foreach ($schema->children as $child_itemtype => $child_schema) {
            $keys = $this->getChildKeys($itemtype, $child_itemtype, $item->getID());
            foreach ($node['children'][$child_itemtype] ?? [] as $child) {
                /** @var Node $child */
                $this->importNode($child_itemtype, $child_schema, $child, $mapper, $keys);
            }
        }
        return $item;
    }

    /**
     * The imported values, with the ids of the target instance.
     *
     * @param class-string<CommonDBTM> $itemtype
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $parent_keys
     * @return array<string, mixed>
     */
    private function toInput(string $itemtype, ExportSchema $schema, array $fields, DatabaseMapper $mapper, array $parent_keys): array
    {
        global $DB;

        // Only real columns (and declared JSON values): other keys would drive add(),
        // e.g. `_picture` moves a file from the server.
        $allowed = array_flip([...array_keys($DB->listFields($itemtype::getTable())), ...$schema->json]);
        return $this->convertReferences(
            $schema,
            $this->filterColumns($schema, array_intersect_key($fields, $allowed), $parent_keys),
            static fn(string $type, mixed $value): mixed => self::isName($value) ? $mapper->getItemId($type, $value) : $value,
        );
    }

    /**
     * The item's own errors, once its data requirements are met.
     *
     * @param Node $node
     * @return list<string>
     */
    private function check(array $node, DatabaseMapper $mapper): array
    {
        return $this->schema->check === null
            ? []
            : ($this->schema->check)($this->toInput($this->itemtype, $this->schema, $node['fields'], $mapper, []));
    }

    /**
     * Turn every id held by the fields into something else (a name on export,
     * an id of the target on import), through `$convert`.
     *
     * @param array<string, mixed> $fields
     * @param Closure(class-string<CommonDBTM>, mixed, bool): mixed $convert
     *        Receives the itemtype, the value, and whether 0 is a valid id.
     * @return array<string, mixed>
     */
    private function convertReferences(ExportSchema $schema, array $fields, Closure $convert): array
    {
        $row = $fields;
        foreach ($fields as $column => $value) {
            $fk_itemtype = $column !== 'items_id' && isForeignKeyField($column) ? getItemtypeForForeignKeyField($column) : null;
            if ($fk_itemtype !== null && is_a($fk_itemtype, CommonDBTM::class, true)) {
                $fields[$column] = $convert($fk_itemtype, $value, $fk_itemtype === Entity::class);
            }
        }
        if (isset($fields['items_id']) && is_string($row['itemtype'] ?? null) && is_a($row['itemtype'], CommonDBTM::class, true)) {
            $fields['items_id'] = $convert($row['itemtype'], $fields['items_id'], false);
        }

        foreach ($schema->references as $path => $type) {
            $fields = $this->walk($fields, explode('.', $path), $fields, static function (mixed $value, array $parent) use ($type, $row, $convert): mixed {
                $itemtype = $type instanceof Closure ? $type($parent, $row) : $type;
                return is_string($itemtype) && is_a($itemtype, CommonDBTM::class, true)
                    ? $convert($itemtype, $value, false)
                    : $value;
            });
        }
        return $fields;
    }

    /**
     * Apply `$callback` to every value (or key, for `#`) found at the given path.
     *
     * @param list<string> $path
     * @param array<mixed> $parent The array holding `$data`.
     * @param Closure(mixed, array<mixed>): mixed $callback
     */
    private function walk(mixed $data, array $path, array $parent, Closure $callback): mixed
    {
        $segment = array_shift($path);
        if ($segment === null) {
            return is_array($data)
                ? array_map(static fn(mixed $value): mixed => $callback($value, $parent), $data)
                : $callback($data, $parent);
        }
        if (!is_array($data)) {
            return $data;
        }
        if ($segment === '#') {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$callback($key, $data)] = $value;
            }
            return $result;
        }
        foreach ($data as $key => $value) {
            if ($segment === '*' || (string) $key === $segment) {
                $data[$key] = $this->walk($value, $path, $data, $callback);
            }
        }
        return $data;
    }

    /** @param class-string<CommonDBTM> $itemtype */
    private function exportReference(string $itemtype, mixed $value, bool $zero_is_id): mixed
    {
        if (!is_numeric($value)) {
            return $value;
        }
        $id = (int) $value;
        if ($id < 0 || ($id === 0 && !$zero_is_id)) {
            return $value;
        }
        $item = getItemForItemtype($itemtype);
        if ($item !== false && $item->getFromDB($id)) {
            return DataRequirementSpecification::fromItem($item)->name;
        }
        // Deleted item: no name will match it, so the wizard asks for a replacement
        // rather than silently dropping the reference.
        return '#' . $id;
    }

    /**
     * Whether an imported reference is a name, to be mapped to an id. Values
     * that were not ids on export (`0`, `-1`...) are kept as they were, so
     * they are numeric.
     *
     * @phpstan-assert-if-true string $value
     */
    private static function isName(mixed $value): bool
    {
        return is_string($value) && !is_numeric($value);
    }

    /**
     * Visit the fields of the node and of all its children.
     *
     * @param Node $node
     * @param Closure(ExportSchema, array<string, mixed>): void $visitor
     */
    private function visit(ExportSchema $schema, array $node, Closure $visitor): void
    {
        $visitor($schema, $node['fields']);
        foreach ($schema->children as $child_itemtype => $child_schema) {
            foreach ($node['children'][$child_itemtype] ?? [] as $child) {
                /** @var Node $child */
                $this->visit($child_schema, $child, $visitor);
            }
        }
    }

    /**
     * @param Node $node
     * @return list<DataRequirementSpecification>
     */
    private function getRequirements(array $node): array
    {
        $requirements = [];
        $this->visit($this->schema, $node, function (ExportSchema $schema, array $fields) use (&$requirements): void {
            $this->convertReferences($schema, $fields, static function (string $itemtype, mixed $value) use (&$requirements): mixed {
                if (self::isName($value)) {
                    $requirements[$itemtype . '|' . $value] = new DataRequirementSpecification($itemtype, $value);
                }
                return $value;
            });
        });
        return array_values($requirements);
    }

    /**
     * Errors that cannot be fixed from the wizard.
     *
     * @param Node $node
     * @return list<string>
     */
    private function getFatalErrors(array $node): array
    {
        $errors = [];
        $this->visit($this->schema, $node, function (ExportSchema $schema, array $fields) use (&$errors): void {
            foreach ($schema->classes as $path) {
                $this->walk($fields, explode('.', $path), $fields, static function (mixed $class) use (&$errors): mixed {
                    if (is_string($class) && $class !== '' && !class_exists($class)) {
                        $plugin = isPluginItemType($class);
                        $errors[] = is_array($plugin)
                            ? sprintf(__('Missing plugin: %s'), $plugin['plugin'])
                            : sprintf(__('Unknown type: %s'), $class);
                    }
                    return $class;
                });
            }
        });
        foreach ($this->schema->unique as $column) {
            $value = $node['fields'][$column] ?? null;
            if (is_scalar($value) && countElementsInTable($this->itemtype::getTable(), [$column => $value]) > 0) {
                $errors[] = sprintf(__('%1$s "%2$s" already exists.'), $this->itemtype::getTypeName(1), $value);
            }
        }
        return array_values(array_unique($errors));
    }

    /**
     * @return list<array{id: int, name: string, fields: array<string, mixed>, children?: array<string, list<mixed>>}>
     */
    private function readItems(string $json): array
    {
        $data = json_decode($json, true);
        if (
            !is_array($data)
            || ($data['type'] ?? null) !== $this->itemtype
            || ($data['version'] ?? null) !== $this->schema->version
            || !is_array($data['items'] ?? null)
        ) {
            throw new InvalidArgumentException('Unsupported file.');
        }

        $items = [];
        foreach ($data['items'] as $item) {
            if (is_array($item) && is_scalar($item['id'] ?? null) && is_array($item['fields'] ?? null)) {
                $items[] = [
                    'id'       => (int) $item['id'],
                    'name'     => (string) (is_scalar($item['name'] ?? null) ? $item['name'] : $item['id']),
                    'fields'   => $item['fields'],
                    'children' => is_array($item['children'] ?? null) ? $item['children'] : [],
                ];
            }
        }
        return $items;
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $parent_keys
     * @return array<string, mixed>
     */
    private function filterColumns(ExportSchema $schema, array $fields, array $parent_keys): array
    {
        return array_diff_key($fields, array_flip([...self::IGNORED_COLUMNS, ...$schema->exclude]), $parent_keys);
    }

    /**
     * Columns linking a child to its parent.
     *
     * @param class-string<CommonDBTM> $itemtype
     * @return array<string, mixed>
     */
    private function getChildKeys(string $itemtype, string $child_itemtype, int $id): array
    {
        if (is_a($child_itemtype, CommonDBChild::class, true)) {
            return $child_itemtype::$itemtype === 'itemtype'
                ? ['itemtype' => $itemtype, $child_itemtype::$items_id => $id]
                : [$child_itemtype::$items_id => $id];
        }
        return [getForeignKeyFieldForItemType($itemtype) => $id];
    }

    /**
     * @param array<string, mixed> $criteria
     * @return list<array<string, mixed>>
     */
    private function getRows(string $table, array $criteria): array
    {
        global $DB;

        return array_values(iterator_to_array($DB->request(['FROM' => $table, 'WHERE' => $criteria, 'ORDER' => 'id'])));
    }

    /** @return array{name: string, data: string}|null */
    private function exportPicture(mixed $path): ?array
    {
        if (!is_string($path) || $path === '' || str_contains($path, '..') || !is_file(GLPI_PICTURE_DIR . '/' . $path)) {
            return null;
        }
        return ['name' => basename($path), 'data' => base64_encode(file_get_contents(GLPI_PICTURE_DIR . '/' . $path))];
    }

    /**
     * Hand the embedded picture over to `AssetImage::managePictures()`, which
     * checks it is an image and stores it.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function importPicture(array $input, string $column): array
    {
        $picture = $input[$column] ?? null;
        unset($input[$column]);
        if (is_array($picture) && is_string($picture['data'] ?? null)) {
            $extension = preg_replace('/[^a-z0-9]/i', '', pathinfo((string) ($picture['name'] ?? ''), PATHINFO_EXTENSION));
            $filename = uniqid('import_') . '.' . $extension;
            file_put_contents(GLPI_TMP_DIR . '/' . $filename, base64_decode($picture['data'], true));
            $input['_' . $column] = [$filename];
        }
        return $input;
    }

    /** @param CommonDBTM[] $items */
    private function getFileName(array $items): string
    {
        $date = Session::getCurrentDate();
        if (count($items) === 1) {
            return Toolbox::slugify(current($items)->getFriendlyName()) . "-$date.json";
        }

        // Two exports of as many items must not share the same name.
        $checksum = crc32(json_encode(array_map(static fn(CommonDBTM $item) => $item->getID(), $items)));
        $type = Toolbox::slugify($this->itemtype::getTypeName(count($items)));
        return sprintf('export-of-%d-%s-%s-%s.json', count($items), $type, $date, $checksum);
    }
}
