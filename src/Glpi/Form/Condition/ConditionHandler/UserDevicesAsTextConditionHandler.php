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

use CommonDBTM;
use Glpi\Form\Condition\ConditionData;
use Glpi\Form\Condition\ValueOperator;
use Override;

/**
 * Allow text comparison on items using contains operator.
 */
final class UserDevicesAsTextConditionHandler implements ConditionHandlerInterface
{
    public function __construct(
        private bool $is_multiple_devices = false,
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
        if (!$this->is_multiple_devices && is_string($a)) {
            $a = [$a];
        }

        if (!is_array($a)) {
            return false;
        }

        // Get valid item names from the raw answer
        $a = array_filter($this->getItemNamesFromRawAnswer($a));

        // Normalize values
        $a = array_map(fn(string $item) => strtolower(strval($item)), $a);
        $b = strtolower(strval($b));

        return match ($operator) {
            ValueOperator::CONTAINS     => array_reduce(
                $a,
                fn(bool $carry, string $item) => $carry || str_contains($item, $b),
                false
            ),
            ValueOperator::NOT_CONTAINS => !array_reduce(
                $a,
                fn(bool $carry, string $item) => $carry || str_contains($item, $b),
                false
            ),

            // Note: we do not want to throw warnings here if an invalid regex
            // is configured by the user.
            // There is no clean way to test that a regex is valid in PHP,
            // therefore the simplest way to deal with that is to ignore
            // warnings using the "@" prefix.
            ValueOperator::MATCH_REGEX     => $this->array_preg_match($a, $b),
            ValueOperator::NOT_MATCH_REGEX => $this->array_preg_not_match($a, $b),

            // Unsupported operators
            default => false,
        };
    }

    /**
     * Get items from raw answer.
     *
     * @param mixed $raw_answer The raw answer from the form.
     * @return array<string> The list of item names.
     */
    private function getItemNamesFromRawAnswer(
        mixed $raw_answer,
    ): array {
        if (is_array($raw_answer)) {
            return array_map(
                fn(string $raw_answer) => $this->getItemFromRawAnswer($raw_answer)?->getName(),
                $raw_answer
            );
        } elseif (is_string($raw_answer)) {
            return [
                $this->getItemFromRawAnswer($raw_answer)?->getName(),
            ];
        }

        return [];
    }

    /**
     * Get item from raw answer.
     *
     * @param string $raw_answer The raw answer from the form.
     * @return CommonDBTM|null The item if found, null otherwise.
     */
    private function getItemFromRawAnswer(string $raw_answer): ?CommonDBTM
    {
        if (preg_match('/^([A-Za-z]+)_\d+$/', $raw_answer, $matches)) { // @phpstan-ignore theCodingMachineSafe.function
            $itemtype = $matches[1];
            $item_id = substr($raw_answer, strlen($itemtype) + 1); // Get the ID part after the itemtype
            $item = getItemForItemtype($itemtype);
            if ($item instanceof CommonDBTM && $item->getFromDB($item_id)) {
                return $item;
            }
        }
        return null;
    }

    private function array_preg_match(array $values, string $pattern): bool
    {
        $pattern = strtolower($pattern);
        if ($values === []) {
            return false; // No elements, so none match
        }

        foreach ($values as $value) {
            if (!@preg_match($pattern, $value)) {    // @phpstan-ignore theCodingMachineSafe.function
                return false; // If any value doesn't match, the whole condition fails
            }
        }
        return true; // All values match the regex, so MATCH_REGEX is true
    }

    private function array_preg_not_match(array $values, string $pattern): bool
    {
        $pattern = strtolower($pattern);
        if ($values === []) {
            return true; // No elements, so none match
        }

        foreach ($values as $value) {
            if (@preg_match($pattern, $value)) {    // @phpstan-ignore theCodingMachineSafe.function
                return false; // At least one element matches, so NOT_MATCH_REGEX is false
            }
        }
        return true; // No elements match, so NOT_MATCH_REGEX is true
    }
}
