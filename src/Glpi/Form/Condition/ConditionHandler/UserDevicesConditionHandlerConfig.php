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

/**
 * Configuration class for UserDevicesConditionHandler to support
 * more flexible operator selection based on various parameters.
 */
final class UserDevicesConditionHandlerConfig
{
    public function __construct(
        private bool $is_multiple_devices = false,
        private ?array $allowed_device_types = null,
        private ?string $context = null,
        private ?array $additional_parameters = null,
    ) {}

    public function isMultipleDevices(): bool
    {
        return $this->is_multiple_devices;
    }

    public function getAllowedDeviceTypes(): ?array
    {
        return $this->allowed_device_types;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function getAdditionalParameters(): ?array
    {
        return $this->additional_parameters;
    }

    /**
     * Get the supported value operators based on configuration parameters.
     * This method can be extended to handle more complex scenarios.
     *
     * @return ValueOperator[]
     */
    public function getSupportedValueOperators(): array
    {
        // Handle specific context-based operator selection
        if ($this->context !== null) {
            $operators = $this->getOperatorsForContext();
            if ($operators !== null) {
                return $operators;
            }
        }

        // Handle device type specific operators
        if ($this->allowed_device_types !== null) {
            $operators = $this->getOperatorsForDeviceTypes();
            if ($operators !== null) {
                return $operators;
            }
        }

        // Handle additional parameter-based operators
        if ($this->additional_parameters !== null) {
            $operators = $this->getOperatorsForAdditionalParameters();
            if ($operators !== null) {
                return $operators;
            }
        }

        // Default behavior based on multiple devices setting
        return $this->getDefaultOperators();
    }

    /**
     * Get operators based on context.
     *
     * @return ValueOperator[]|null
     */
    private function getOperatorsForContext(): ?array
    {
        return match ($this->context) {
            'strict_single' => [
                ValueOperator::IS_ITEMTYPE,
            ],
            'strict_multiple' => [
                ValueOperator::ALL_ITEMS_OF_ITEMTYPE,
            ],
            'flexible_single' => [
                ValueOperator::IS_ITEMTYPE,
                ValueOperator::IS_NOT_ITEMTYPE,
                ValueOperator::EQUALS,
                ValueOperator::NOT_EQUALS,
            ],
            'flexible_multiple' => [
                ValueOperator::AT_LEAST_ONE_ITEM_OF_ITEMTYPE,
                ValueOperator::ALL_ITEMS_OF_ITEMTYPE,
                ValueOperator::CONTAINS,
                ValueOperator::NOT_CONTAINS,
            ],
            default => null,
        };
    }

    /**
     * Get operators based on allowed device types.
     *
     * @return ValueOperator[]|null
     */
    private function getOperatorsForDeviceTypes(): ?array
    {
        // If specific device types are allowed, we might want to provide
        // different operators. For example, if only computers are allowed,
        // we might not need the itemtype operators
        if (count($this->allowed_device_types) === 1) {
            // Single device type - simplified operators
            return $this->is_multiple_devices 
                ? [ValueOperator::CONTAINS, ValueOperator::NOT_CONTAINS]
                : [ValueOperator::EQUALS, ValueOperator::NOT_EQUALS];
        }

        return null; // Use default behavior
    }

    /**
     * Get operators based on additional parameters.
     *
     * @return ValueOperator[]|null
     */
    private function getOperatorsForAdditionalParameters(): ?array
    {
        // Handle specific parameter combinations
        if (isset($this->additional_parameters['user_role'])) {
            $role = $this->additional_parameters['user_role'];
            
            // Administrators might get more operators
            if ($role === 'admin') {
                return array_merge(
                    $this->getDefaultOperators(),
                    [ValueOperator::EMPTY, ValueOperator::NOT_EMPTY]
                );
            }
            
            // Regular users might get limited operators
            if ($role === 'user') {
                return $this->is_multiple_devices
                    ? [ValueOperator::AT_LEAST_ONE_ITEM_OF_ITEMTYPE]
                    : [ValueOperator::IS_ITEMTYPE];
            }
        }

        // Handle permission-based operators
        if (isset($this->additional_parameters['permissions'])) {
            $permissions = $this->additional_parameters['permissions'];
            
            if (in_array('advanced_conditions', $permissions, true)) {
                return array_merge(
                    $this->getDefaultOperators(),
                    [
                        ValueOperator::GREATER_THAN,
                        ValueOperator::LESS_THAN,
                        ValueOperator::MATCH_REGEX,
                    ]
                );
            }
        }

        return null; // Use default behavior
    }

    /**
     * Get default operators based on multiple devices setting.
     *
     * @return ValueOperator[]
     */
    private function getDefaultOperators(): array
    {
        if ($this->is_multiple_devices) {
            return [
                ValueOperator::AT_LEAST_ONE_ITEM_OF_ITEMTYPE,
                ValueOperator::ALL_ITEMS_OF_ITEMTYPE,
            ];
        } else {
            return [
                ValueOperator::IS_ITEMTYPE,
                ValueOperator::IS_NOT_ITEMTYPE,
            ];
        }
    }

    /**
     * Create a config from a boolean for backward compatibility.
     */
    public static function fromBoolean(bool $is_multiple_devices): self
    {
        return new self(is_multiple_devices: $is_multiple_devices);
    }

    /**
     * Create a config with context.
     */
    public static function withContext(
        bool $is_multiple_devices,
        string $context,
        ?array $additional_parameters = null
    ): self {
        return new self(
            is_multiple_devices: $is_multiple_devices,
            context: $context,
            additional_parameters: $additional_parameters
        );
    }

    /**
     * Create a config with specific device types.
     */
    public static function withDeviceTypes(
        bool $is_multiple_devices,
        array $allowed_device_types
    ): self {
        return new self(
            is_multiple_devices: $is_multiple_devices,
            allowed_device_types: $allowed_device_types
        );
    }
}