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

namespace tests\units\Glpi\ImportExport;

use CommonDBTM;
use Computer;
use Entity;
use Glpi\ImportExport\Context\DatabaseMapper;
use Glpi\ImportExport\ExportSchema;
use Glpi\ImportExport\Result\ImportError;
use Glpi\ImportExport\SchemaSerializer;
use Glpi\ImportExport\Specification\DataRequirementSpecification;
use Glpi\Tests\DbTestCase;
use Group;
use InvalidArgumentException;
use Item_Disk;
use Location;
use Notepad;
use Profile;
use Psr\Log\LogLevel;
use RuntimeException;
use Session;
use User;

use function Safe\json_decode;
use function Safe\json_encode;

/**
 * The engine, run on core itemtypes with a schema given by the test. The
 * `comment` column of computers is used as a JSON column holding ids.
 */
final class SchemaSerializerTest extends DbTestCase
{
    private function getComputerSerializer(): SchemaSerializer
    {
        return new SchemaSerializer(Computer::class, new ExportSchema(
            exclude: ['uuid'],
            json: ['comment'],
            references: [
                'comment.groups'         => Group::class,
                'comment.rights.#'       => Profile::class,
                'comment.rows.*.value'   => static fn(array $row) => $row['type'] === 'user' ? User::class : null,
            ],
            classes: ['comment.class'],
            children: [Item_Disk::class => new ExportSchema(), Notepad::class => new ExportSchema()],
            // The comment is a plain text column: give it back as a string.
            prepare_input: static fn(array $input) => ['comment' => json_encode($input['comment'])] + $input,
        ));
    }

    /** @return array{Computer, Group} */
    private function createComputer(string $name = 'Exported computer'): array
    {
        $entity = $this->getTestRootEntity(true);
        $group = $this->createItem(Group::class, ['name' => 'Export group', 'entities_id' => $entity]);
        $parent = $this->createItem(Location::class, ['name' => 'Export building', 'entities_id' => $entity]);
        $location = $this->createItem(Location::class, ['name' => 'Export room', 'locations_id' => $parent->getID(), 'entities_id' => $entity]);
        $computer = $this->createItem(Computer::class, [
            'name'         => $name,
            'entities_id'  => getItemByTypeName(Entity::class, '_test_child_1', true),
            'locations_id' => $location->getID(),
            'users_id'     => getItemByTypeName(User::class, 'tech', true),
            'comment'      => json_encode([
                'groups' => [$group->getID(), 0],
                'rights' => [getItemByTypeName(Profile::class, 'Technician', true) => 31],
                'rows'   => [
                    ['type' => 'user', 'value' => getItemByTypeName(User::class, 'glpi', true)],
                    ['type' => 'text', 'value' => '42'],
                ],
                'none'   => 7,
            ]),
        ], ['comment']);
        $this->createItem(Item_Disk::class, ['itemtype' => Computer::class, 'items_id' => $computer->getID(), 'name' => 'C:', 'totalsize' => 100]);
        $this->createItem(Notepad::class, ['itemtype' => Computer::class, 'items_id' => $computer->getID(), 'content' => 'A note']);
        return [$computer, $group];
    }

    private function getMapper(): DatabaseMapper
    {
        return new DatabaseMapper(Session::getActiveEntities());
    }

    public function testExportTurnsIdsIntoNames(): void
    {
        $this->login();
        [$computer] = $this->createComputer();

        $export = $this->getComputerSerializer()->exportItems([$computer]);
        $this->assertStringStartsWith('exported-computer-', $export->getFileName());

        $data = json_decode($export->getJsonContent(), true);
        $this->assertSame(Computer::class, $data['type']);
        $this->assertSame(1, $data['version']);
        $item = $data['items'][0];
        $this->assertSame($computer->getID(), $item['id']);
        $this->assertSame('Exported computer', $item['name']);

        $fields = $item['fields'];
        $this->assertArrayNotHasKey('id', $fields);
        $this->assertArrayNotHasKey('uuid', $fields);
        $this->assertArrayNotHasKey('date_mod', $fields);
        // Foreign keys: entity and tree dropdown by complete name, user by name, 0 kept
        $this->assertSame('Root entity > _test_root_entity > _test_child_1', $fields['entities_id']);
        $this->assertSame('Export building > Export room', $fields['locations_id']);
        $this->assertSame('tech', $fields['users_id']);
        $this->assertSame(0, $fields['manufacturers_id']);
        // JSON: declared paths only, non ids untouched
        $this->assertSame([
            'groups' => ['Export group', 0],
            'rights' => ['Technician' => 31],
            'rows'   => [['type' => 'user', 'value' => 'glpi'], ['type' => 'text', 'value' => '42']],
            'none'   => 7,
        ], $fields['comment']);

        // Children, without their link to the parent
        $this->assertSame('C:', $item['children'][Item_Disk::class][0]['fields']['name']);
        $this->assertArrayNotHasKey('items_id', $item['children'][Item_Disk::class][0]['fields']);
        $this->assertArrayNotHasKey('itemtype', $item['children'][Item_Disk::class][0]['fields']);
        $this->assertSame('A note', $item['children'][Notepad::class][0]['fields']['content']);
    }

    public function testRoundTrip(): void
    {
        $this->login();
        [$computer, $group] = $this->createComputer();
        $serializer = $this->getComputerSerializer();
        $json = $serializer->exportItems([$computer])->getJsonContent();

        $preview = $serializer->previewImport($json, $this->getMapper());
        $this->assertSame([$computer->getID() => 'Exported computer'], $preview->getValidItems());

        $result = $serializer->importItems($json, $this->getMapper());
        $this->assertSame([], $result->getFailedImports());
        $this->assertCount(1, $result->getImportedItems());
        $imported = Computer::getById($result->getImportedItems()[0]->getID());

        $this->assertNotSame($computer->getID(), $imported->getID());
        foreach (['name', 'entities_id', 'locations_id', 'users_id'] as $field) {
            $this->assertSame($computer->fields[$field], $imported->fields[$field], $field);
        }
        $this->assertSame(json_decode($computer->fields['comment'], true), json_decode($imported->fields['comment'], true));

        $disks = (new Item_Disk())->find(['itemtype' => Computer::class, 'items_id' => $imported->getID()]);
        $this->assertSame(['C:'], array_column($disks, 'name'));
        $notes = (new Notepad())->find(['itemtype' => Computer::class, 'items_id' => $imported->getID()]);
        $this->assertSame(['A note'], array_column($notes, 'content'));
    }

    public function testMissingRequirementCanBeReplaced(): void
    {
        $this->login();
        [$computer, $group] = $this->createComputer();
        $serializer = $this->getComputerSerializer();
        $json = $serializer->exportItems([$computer])->getJsonContent();

        // The group does not exist on the "target" instance
        $this->assertTrue($group->update(['id' => $group->getID(), 'name' => 'Renamed group']));
        $preview = $serializer->previewImport($json, $this->getMapper());
        $this->assertSame([$computer->getID() => 'Exported computer'], $preview->getInvalidItems());

        $issues = $serializer->listIssues($this->getMapper(), $json)->getIssues()[$computer->getID()];
        $this->assertEquals([new DataRequirementSpecification(Group::class, 'Export group')], $issues);

        $result = $serializer->importItems($json, $this->getMapper());
        $this->assertCount(0, $result->getImportedItems());
        $this->assertSame(['Exported computer'], array_keys($result->getFailedImports()));

        // The administrator picks a replacement
        $replacement = $this->createItem(Group::class, ['name' => 'Replacement group']);
        $mapper = $this->getMapper();
        $mapper->addMappedItem(Group::class, 'Export group', $replacement->getID());
        $this->assertCount(1, $serializer->previewImport($json, $mapper)->getValidItems());

        $imported = $serializer->importItems($json, $mapper)->getImportedItems()[0];
        $comment = json_decode(Computer::getById($imported->getID())->fields['comment'], true);
        $this->assertSame([$replacement->getID(), 0], $comment['groups']);
    }

    public function testDeletedReferenceMustBeReplaced(): void
    {
        $this->login();
        [$computer, $group] = $this->createComputer();
        $this->assertTrue($group->delete(['id' => $group->getID()], true));

        $serializer = $this->getComputerSerializer();
        $json = $serializer->exportItems([$computer])->getJsonContent();
        $this->assertSame(['#' . $group->getID(), 0], json_decode($json, true)['items'][0]['fields']['comment']['groups']);

        $issues = $serializer->listIssues($this->getMapper(), $json)->getIssues()[$computer->getID()];
        $this->assertEquals([new DataRequirementSpecification(Group::class, '#' . $group->getID())], $issues);
    }

    public function testSkippedItemIsNotImported(): void
    {
        $this->login();
        [$computer_a] = $this->createComputer('Computer A');
        $computer_b = $this->createItem(Computer::class, ['name' => 'Computer B', 'entities_id' => $this->getTestRootEntity(true)]);
        $serializer = $this->getComputerSerializer();
        $export = $serializer->exportItems([$computer_a, $computer_b]);
        $this->assertMatchesRegularExpression('/^export-of-2-computers-.*\.json$/', $export->getFileName());

        $preview = $serializer->previewImport($export->getJsonContent(), $this->getMapper(), [$computer_a->getID()]);
        $this->assertSame([$computer_a->getID() => 'Computer A'], $preview->getSkippedItems());
        $this->assertSame([$computer_b->getID() => 'Computer B'], $preview->getValidItems());

        $result = $serializer->importItems($export->getJsonContent(), $this->getMapper(), [(string) $computer_a->getID()]);
        $this->assertSame(['Computer B'], array_map(static fn($item) => $item->fields['name'], $result->getImportedItems()));
    }

    public function testItemtypeItemsIdPairIsConverted(): void
    {
        $this->login();
        $computer = $this->createItem(Computer::class, ['name' => 'Noted computer', 'entities_id' => $this->getTestRootEntity(true)]);
        $note = $this->createItem(Notepad::class, ['itemtype' => Computer::class, 'items_id' => $computer->getID(), 'content' => 'Hello']);

        $serializer = new SchemaSerializer(Notepad::class, new ExportSchema());
        $json = $serializer->exportItems([$note])->getJsonContent();
        $this->assertSame('Noted computer', json_decode($json, true)['items'][0]['fields']['items_id']);

        $imported = $serializer->importItems($json, $this->getMapper())->getImportedItems()[0];
        $this->assertSame($computer->getID(), $imported->fields['items_id']);
    }

    public function testFatalErrors(): void
    {
        $this->login();
        $computer = $this->createItem(Computer::class, [
            'name'        => 'Computer with classes',
            'entities_id' => $this->getTestRootEntity(true),
            'comment'     => json_encode(['class' => ['Glpi\\Unknown\\Thing', 'GlpiPlugin\\Missing\\Thing', Computer::class]]),
        ], ['comment']);
        $serializer = $this->getComputerSerializer();
        $json = $serializer->exportItems([$computer])->getJsonContent();

        $errors = $serializer->previewImport($json, $this->getMapper())->getItemsWithFatalErrors();
        $this->assertSame(
            ['Unknown type: Glpi\\Unknown\\Thing', 'Missing plugin: Missing'],
            $errors[$computer->getID()]['errors'],
        );
        $this->assertCount(0, $serializer->importItems($json, $this->getMapper())->getImportedItems());

        // Unique columns
        $serializer = new SchemaSerializer(Computer::class, new ExportSchema(unique: ['name']));
        $json = $serializer->exportItems([$computer])->getJsonContent();
        $errors = $serializer->previewImport($json, $this->getMapper())->getItemsWithFatalErrors();
        $this->assertSame(['Computer "Computer with classes" already exists.'], $errors[$computer->getID()]['errors']);
    }

    public function testItemCanReportItsOwnErrors(): void
    {
        $this->login();
        $computer = $this->createItem(Computer::class, [
            'name'        => 'Checked computer',
            'entities_id' => $this->getTestRootEntity(true),
            'users_id'    => getItemByTypeName(User::class, 'tech', true),
        ]);
        $received = null;
        $serializer = new SchemaSerializer(Computer::class, new ExportSchema(check: static function (array $input) use (&$received): array {
            $received = $input;
            return $input['users_id'] === getItemByTypeName(User::class, 'tech', true) ? ['Not tech!'] : [];
        }));
        $json = $serializer->exportItems([$computer])->getJsonContent();

        // Checked with the ids of the target instance
        $errors = $serializer->previewImport($json, $this->getMapper())->getItemsWithFatalErrors();
        $this->assertSame(['Not tech!'], $errors[$computer->getID()]['errors']);
        $this->assertSame(getItemByTypeName(User::class, 'tech', true), $received['users_id']);
        $result = $serializer->importItems($json, $this->getMapper());
        $this->assertSame([], $result->getImportedItems());
        $this->assertSame(['Checked computer' => ImportError::FATAL_ERROR], $result->getFailedImports());

        // Not called while data requirements are missing: the wizard asks for them first
        $received = null;
        $data = json_decode($json, true);
        $data['items'][0]['fields']['users_id'] = 'Unknown user';
        $preview = $serializer->previewImport(json_encode($data), $this->getMapper());
        $this->assertSame([$computer->getID() => 'Checked computer'], $preview->getInvalidItems());
        $this->assertNull($received);
    }

    public function testItemCanCompleteItsImportAndLeaveNotes(): void
    {
        $this->login();
        $computer = $this->createItem(Computer::class, ['name' => 'Completed computer', 'entities_id' => $this->getTestRootEntity(true)]);
        $serializer = new SchemaSerializer(Computer::class, new ExportSchema(
            prepare_export: static fn(array $row) => ['_note' => 'From the file'] + $row,
            json: ['_note'],
            after_import: static function (CommonDBTM $item, array $fields): array {
                (new Notepad())->add(['itemtype' => $item::class, 'items_id' => $item->getID(), 'content' => $fields['_note']]);
                return ['Note added'];
            },
        ));
        $json = $serializer->exportItems([$computer])->getJsonContent();

        $result = $serializer->importItems($json, $this->getMapper());
        $imported = $result->getImportedItems()[0];
        $this->assertSame(['Note added'], $result->getNotes($imported));
        $notes = (new Notepad())->find(['itemtype' => Computer::class, 'items_id' => $imported->getID()]);
        $this->assertSame(['From the file'], array_column($notes, 'content'));

        // Same transaction: a failure undoes the item
        $serializer = new SchemaSerializer(Computer::class, new ExportSchema(
            after_import: static fn(): array => throw new RuntimeException('Failed'),
        ));
        $count = countElementsInTable(Computer::getTable(), ['name' => 'Completed computer']);
        $result = $serializer->importItems($json, $this->getMapper());
        $this->assertSame(['Completed computer' => ImportError::INVALID_DATA], $result->getFailedImports());
        $this->hasPhpLogRecordThatContains('Caught RuntimeException: Failed', LogLevel::ERROR);
        $this->assertSame($count, countElementsInTable(Computer::getTable(), ['name' => 'Completed computer']));
    }

    public function testItemIsOnlyImportedInAnAccessibleEntity(): void
    {
        $this->login();
        $child_1 = getItemByTypeName(Entity::class, '_test_child_1', true);
        $computer = $this->createItem(Computer::class, ['name' => 'Computer of child 1', 'entities_id' => $child_1]);
        $serializer = new SchemaSerializer(Computer::class, new ExportSchema());
        $json = $serializer->exportItems([$computer])->getJsonContent();

        // A replacement crafted to target an entity the user cannot see
        $this->setEntity('_test_child_2', false);
        $mapper = $this->getMapper();
        $mapper->addMappedItem(Entity::class, 'Root entity > _test_root_entity > _test_child_1', $child_1);

        $result = $serializer->importItems($json, $mapper);
        $this->assertSame(['Computer of child 1' => ImportError::INVALID_DATA], $result->getFailedImports());
        $this->assertSame(1, countElementsInTable(Computer::getTable(), ['name' => 'Computer of child 1']));
        $this->hasPhpLogRecordThatContains('No access to the entity of Computer "Computer of child 1"', LogLevel::ERROR);
    }

    public function testOnlyColumnsAreImported(): void
    {
        $this->login();
        $computer = $this->createItem(Computer::class, ['name' => 'Crafted computer', 'entities_id' => $this->getTestRootEntity(true)]);
        $serializer = new SchemaSerializer(Computer::class, new ExportSchema());
        $data = json_decode($serializer->exportItems([$computer])->getJsonContent(), true);
        // Keys that are not columns could drive add() (`_picture` would move a server file)
        $data['items'][0]['fields']['_crafted'] = true;
        $data['items'][0]['fields']['unknown'] = 'value';
        $data['items'][0]['fields']['id'] = $computer->getID();

        $imported = $serializer->importItems(json_encode($data), $this->getMapper())->getImportedItems()[0];
        $this->assertNotSame($computer->getID(), $imported->getID());
        $this->assertArrayNotHasKey('_crafted', $imported->input);
        $this->assertArrayNotHasKey('unknown', $imported->input);
    }

    public function testFileOfAnotherTypeIsRefused(): void
    {
        $this->login();
        $json = json_encode(['type' => Computer::class, 'version' => 1, 'items' => []]);

        $this->expectException(InvalidArgumentException::class);
        (new SchemaSerializer(Notepad::class, new ExportSchema()))->previewImport($json, $this->getMapper());
    }
}
