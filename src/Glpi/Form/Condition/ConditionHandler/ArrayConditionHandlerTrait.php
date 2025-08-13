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
            ValueOperator::MATCH_REGEX,
            ValueOperator::NOT_MATCH_REGEX,
        ];
    }

    protected function applyArrayValueOperator(
        mixed $a,
        ValueOperator $operator,
        mixed $b,
    ): bool {
        if (!is_array($a) || !is_array($b)) {
            return false;
        }

        // Normalize values
        $a = array_values($a);
        $b = array_values($b);
        sort($a);
        sort($b);

        return match ($operator) {
            ValueOperator::EQUALS          => $a == $b,
            ValueOperator::NOT_EQUALS      => $a != $b,
            ValueOperator::CONTAINS        => empty(array_diff($b, $a)),
            ValueOperator::NOT_CONTAINS    => !empty(array_diff($b, $a)),

            // Unsupported operators
            default => false,
        };
    }

    protected function applyRegexValueOperator(
        mixed $a,
        ValueOperator $operator,
        mixed $b,
    ): bool {
        if (!is_array($a)) {
            return false;
        }

        $pattern = $b;
        if (!is_string($pattern)) {
            return false;
        }

        return match ($operator) {
            ValueOperator::MATCH_REGEX     => $this->array_preg_match($a, $pattern),
            ValueOperator::NOT_MATCH_REGEX => $this->array_preg_not_match($a, $pattern),

            // Unsupported operators
            default => false,
        };
    }

    protected function array_preg_match(array $values, string $pattern): bool
    {
        $pattern = strtolower($pattern);
        if ($values === []) {
            return false; // No elements, so none match
        }

        foreach ($values as $value) {
            if (!@preg_match($pattern, $this->getAlternativeValue($value))) {    // @phpstan-ignore theCodingMachineSafe.function
                return false; // If any value doesn't match, the whole condition fails
            }
        }
        return true; // All values match the regex, so MATCH_REGEX is true
    }

    protected function array_preg_not_match(array $values, string $pattern): bool
    {
        $pattern = strtolower($pattern);
        if ($values === []) {
            return true; // No elements, so none match
        }

        foreach ($values as $value) {
            if (@preg_match($pattern, $this->getAlternativeValue($value))) {    // @phpstan-ignore theCodingMachineSafe.function
                return false; // At least one element matches, so NOT_MATCH_REGEX is false
            }
        }
        return true; // No elements match, so NOT_MATCH_REGEX is true
    }

    /**
     * Get an alternative value for a given value.
     * Maybe useful to handle specific cases like UUIDs or labels.
     */
    protected function getAlternativeValue(string $value): ?string
    {
        return $value; // Default implementation, can be overridden in specific handlers
    }
}
