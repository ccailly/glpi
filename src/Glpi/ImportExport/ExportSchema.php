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
use CommonDBTM;
use Glpi\Features\AssetImage;

/**
 * Declares how an itemtype is exported and imported by {@see SchemaSerializer}.
 *
 * Everything not declared here is handled by the engine: the file envelope,
 * foreign keys (`*_id` columns, `entities_id`, `itemtype`/`items_id` pairs),
 * which travel as names and become data requirements, the import wizard and
 * its results.
 *
 * Paths, used by `references` and `classes`, start with a column name and go
 * down its decoded JSON value: `a.b` is a key, `*` every value of a list or
 * map, `#` every key of a map (last segment only). When a path ends on a list,
 * every element of the list is converted.
 *
 * A reference is converted when it is an id (a positive integer). Other
 * values, such as `0` ("none") or `-1` ("all"), are kept as they are. On
 * import, any non numeric string found at a reference is a name to map back.
 */
final class ExportSchema
{
    /**
     * @param list<string> $exclude Columns not exported, on top of `id`,
     *        `date_creation`, `date_mod` and the key to the parent item.
     * @param list<string> $json Columns holding JSON. They are exported
     *        decoded, and given decoded to `add()` on import. Values added by
     *        `prepare_export` are declared here too: only columns and these
     *        keys are imported.
     * @param array<string, class-string<CommonDBTM>|Closure(array<mixed>, array<string, mixed>): ?string> $references
     *        Ids held in JSON columns, by path. The itemtype is either fixed,
     *        or computed from the array holding the value and from the row.
     * @param list<string> $classes Paths holding class names that must exist
     *        on the target instance (capacities, custom types, plugin classes).
     * @param list<string> $unique Columns whose value must not already exist
     *        on the target instance: such an item is refused, not duplicated.
     * @param list<string> $pictures Picture columns managed by
     *        {@see AssetImage::managePictures()}, embedded in the file.
     * @param array<class-string<CommonDBTM>, ExportSchema> $children Child
     *        itemtypes, exported and imported with their parent.
     * @param ?Closure(array<string, mixed>): array<string, mixed> $prepare_export
     *        Adapts the database row before export, for example to add values
     *        stored elsewhere (the `id` is still there, other ids are not yet
     *        converted).
     * @param ?Closure(array<string, mixed>): array<string, mixed> $prepare_input
     *        Adapts the imported values to what `add()` expects, when it
     *        differs from what is stored.
     * @param ?Closure(array<string, mixed>): list<string> $check
     *        Errors only the itemtype can detect, which block the import of
     *        the item. Receives the values as given to `prepare_input` (ids
     *        of the target instance), once its data requirements are met.
     * @param ?Closure(CommonDBTM, array<string, mixed>): list<string> $after_import
     *        Completes the import of what `add()` cannot take (items linked
     *        to a part created by `add()`...). Receives the created item and
     *        the values of the file, runs in the same transaction, and returns
     *        notes shown to the administrator with the import results.
     */
    public function __construct(
        public readonly array $exclude = [],
        public readonly array $json = [],
        public readonly array $references = [],
        public readonly array $classes = [],
        public readonly array $unique = [],
        public readonly array $pictures = [],
        public readonly array $children = [],
        public readonly ?Closure $prepare_export = null,
        public readonly ?Closure $prepare_input = null,
        public readonly ?Closure $check = null,
        public readonly ?Closure $after_import = null,
        public readonly int $version = 1,
    ) {}
}
