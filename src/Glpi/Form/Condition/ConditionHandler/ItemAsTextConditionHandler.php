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

/**
 * Allow text comparison on items using contains operator.
 */
final class ItemAsTextConditionHandler implements ConditionHandlerInterface
{
    public function __construct(
        private string $itemtype,
    ) {}

    #[Override]
    public function getSupportedValueOperators(): array
    {
        return [
            ValueOperator::CONTAINS,
            ValueOperator::NOT_CONTAINS,
            ValueOperator::MATCH_REGEX,
            ValueOperator::NOT_MATCH_REGEX,
        ];
    }

    #[Override]
    public function getTemplate(): string
    {
        return '/pages/admin/form/condition_handler_templates/input.html.twig';
    }

    #[Override]
    public function getTemplateParameters(ConditionData $condition): array
    {
        return [];
    }

    #[Override]
    public function applyValueOperator(
        mixed $a,
        ValueOperator $operator,
        mixed $b,
    ): bool {
        // $a is the submitted answer
        if (!is_array($a) || !isset($a['items_id'])) {
            return false;
        }

        $item = $this->itemtype::getById($a['items_id']);
        if (!$item) {
            return false;
        }
        $a = $item->getName();

        // Normalize values
        $a = strtolower(strval($a));
        $b = strtolower(strval($b));

        return match ($operator) {
            ValueOperator::CONTAINS     => str_contains($a, $b),
            ValueOperator::NOT_CONTAINS => !str_contains($a, $b),

            // Note: we do not want to throw warnings here if an invalid regex
            // is configured by the user.
            // There is no clean way to test that a regex is valid in PHP,
            // therefore the simplest way to deal with that is to ignore
            // warnings using the "@" prefix.
            ValueOperator::MATCH_REGEX     => @preg_match($b, $a),    // @phpstan-ignore theCodingMachineSafe.function
            ValueOperator::NOT_MATCH_REGEX => !@preg_match($b, $a),   // @phpstan-ignore theCodingMachineSafe.function

            // Unsupported operators
            default => false,
        };
    }
}
