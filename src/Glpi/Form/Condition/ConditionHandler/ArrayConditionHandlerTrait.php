<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
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

namespace Glpi\Form\Condition\ConditionHandler;

use Glpi\Form\Condition\ValueOperator;

trait ArrayConditionHandlerTrait
{
    protected function getSupportedArrayValueOperators(): array
    {
        return [
            ValueOperator::EQUALS,
            ValueOperator::NOT_EQUALS,
            ValueOperator::CONTAINS,
            ValueOperator::NOT_CONTAINS,
            ValueOperator::SELECTED_ITEMS_COUNT_GREATER_THAN,
            ValueOperator::SELECTED_ITEMS_COUNT_GREATER_THAN_OR_EQUALS,
            ValueOperator::SELECTED_ITEMS_COUNT_LESS_THAN,
            ValueOperator::SELECTED_ITEMS_COUNT_LESS_THAN_OR_EQUALS,
        ];
    }

    protected function applyArrayValueOperator(
        mixed $a,
        ValueOperator $operator,
        mixed $b,
    ): bool {
        // For count operators, we only need $a to be an array
        if (in_array($operator, [
            ValueOperator::SELECTED_ITEMS_COUNT_GREATER_THAN,
            ValueOperator::SELECTED_ITEMS_COUNT_GREATER_THAN_OR_EQUALS,
            ValueOperator::SELECTED_ITEMS_COUNT_LESS_THAN,
            ValueOperator::SELECTED_ITEMS_COUNT_LESS_THAN_OR_EQUALS,
        ])) {
            if (!is_array($a)) {
                return false;
            }

            return match ($operator) {
                ValueOperator::SELECTED_ITEMS_COUNT_GREATER_THAN           => count($a) > intval($b),
                ValueOperator::SELECTED_ITEMS_COUNT_GREATER_THAN_OR_EQUALS => count($a) >= intval($b),
                ValueOperator::SELECTED_ITEMS_COUNT_LESS_THAN              => count($a) < intval($b),
                ValueOperator::SELECTED_ITEMS_COUNT_LESS_THAN_OR_EQUALS    => count($a) <= intval($b),
            };
        }

        // For other operators, both $a and $b need to be arrays
        if (!is_array($a) || !is_array($b)) {
            return false;
        }

        // Normalize values
        $a = array_values($a);
        $b = array_values($b);
        sort($a);
        sort($b);

        return match ($operator) {
            ValueOperator::EQUALS       => $a == $b,
            ValueOperator::NOT_EQUALS   => $a != $b,
            ValueOperator::CONTAINS     => empty(array_diff($b, $a)),
            ValueOperator::NOT_CONTAINS => !empty(array_diff($b, $a)),

            // Unsupported operators
            default => false,
        };
    }
}
