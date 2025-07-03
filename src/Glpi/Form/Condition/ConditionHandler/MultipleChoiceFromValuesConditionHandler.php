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

use Glpi\Form\Condition\ConditionData;
use Glpi\Form\Condition\ValueOperator;
use Override;

final class MultipleChoiceFromValuesConditionHandler implements ConditionHandlerInterface
{
    use ArrayConditionHandlerTrait;

    public function __construct(
        private array $values,
    ) {}

    #[Override]
    public function getSupportedValueOperators(): array
    {
        return $this->getSupportedArrayValueOperators();
    }

    #[Override]
    public function getTemplate(): string
    {
        return '/pages/admin/form/condition_handler_templates/dropdown_multiple.html.twig';
    }

    #[Override]
    public function getTemplateParameters(ConditionData $condition): array
    {
        switch ($condition->getValueOperator()) {
            case ValueOperator::SELECTED_ITEMS_COUNT_GREATER_THAN:
            case ValueOperator::SELECTED_ITEMS_COUNT_GREATER_THAN_OR_EQUALS:
            case ValueOperator::SELECTED_ITEMS_COUNT_LESS_THAN:
            case ValueOperator::SELECTED_ITEMS_COUNT_LESS_THAN_OR_EQUALS:
                // For selected items count operators, we want to display a number input.
                return [
                    'use_number_input' => true,
                    'attributes' => [
                        'type' => 'number',
                        'step' => 'any',
                    ],
                ];
            default:
                // For other operators, we want to display a dropdown with multiple selection.
                return [
                    'values' => $this->values,
                ];
        }
    }

    #[Override]
    public function applyValueOperator(
        mixed $a,
        ValueOperator $operator,
        mixed $b,
    ): bool {
        return $this->applyArrayValueOperator($a, $operator, $b);
    }
}
