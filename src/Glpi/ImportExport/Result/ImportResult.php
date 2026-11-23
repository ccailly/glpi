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

namespace Glpi\ImportExport\Result;

use CommonDBTM;

final class ImportResult
{
    /** @var CommonDBTM[] $imported_items */
    private array $imported_items = [];

    /** @var array<string, ImportError> $failed_items */
    private array $failed_items = [];

    /** @var array<int, list<string>> $notes By object id of the imported item */
    private array $notes = [];

    /** @param list<string> $notes What the administrator should know about this import */
    public function addImportedItem(CommonDBTM $item, array $notes = []): void
    {
        $this->imported_items[] = $item;
        $this->notes[spl_object_id($item)] = $notes;
    }

    /** @return list<string> */
    public function getNotes(CommonDBTM $item): array
    {
        return $this->notes[spl_object_id($item)] ?? [];
    }

    /** @return CommonDBTM[] */
    public function getImportedItems(): array
    {
        return $this->imported_items;
    }

    public function addFailedImport(string $name, ImportError $error): void
    {
        $this->failed_items[$name] = $error;
    }

    /** @return array<string, ImportError> */
    public function getFailedImports(): array
    {
        return $this->failed_items;
    }
}
