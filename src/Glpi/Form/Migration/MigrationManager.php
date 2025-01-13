<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
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

namespace Glpi\Form\Migration;

use DBmysql;
use Plugin;

final class MigrationManager
{
    private DBmysql $DB;
    private array $keysMap = [];

    public function __construct(DBmysql $DB)
    {
        $this->DB = $DB;
    }

    public function doMigration(): void
    {
        if (!$this->checkPlugin()) {
            return;
        }

        FormMigration::processMigrationOfForms($this);
    }

    public function getDB(): DBmysql
    {
        return $this->DB;
    }

    public function getKeysMap(): array
    {
        return $this->keysMap;
    }

    public function getKeyMap(string $fc_table_name, string $oldKey): ?string
    {
        return $this->keysMap[$fc_table_name][$oldKey] ?? null;
    }

    public function addKeyMap(string $fc_table_name, string $oldKey, string $newKey): void
    {
        $this->keysMap[$fc_table_name][$oldKey] = $newKey;
    }

    /**
     * Sort items by their dependencies
     *
     * @param array $items List of items to sort
     * @param string $idKey Key of the item id
     * @param string $parentKey Key of the parent id
     * @return array Sorted items
     */
    public function sortItems(array $items, string $idKey, string $parentKey): array
    {
        $sorted_items = [];
        $items = array_combine(array_column($items, $idKey), $items);

        $visit = function ($item) use (&$visit, &$items, &$sorted_items, $idKey, $parentKey) {
            if (isset($sorted_items[$item[$idKey]])) {
                return;
            }

            if (!empty($item[$parentKey])) {
                $visit($items[$item[$parentKey]]);
            }

            $sorted_items[$item[$idKey]] = $item;
        };

        foreach ($items as $item) {
            $visit($item);
        }

        return array_values($sorted_items);
    }

    private function checkPlugin(): bool
    {
        $formcreator_tables = [
            'glpi_plugin_formcreator_categories',
            'glpi_plugin_formcreator_forms',
            'glpi_plugin_formcreator_sections',
            'glpi_plugin_formcreator_questions'
        ];

        foreach ($formcreator_tables as $table) {
            if (!$this->getDB()->tableExists($table)) {
                return false;
            }
        }

        return true;
    }
}
