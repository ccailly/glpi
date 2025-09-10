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

namespace phpunit\functional\Glpi\Form\Condition\ConditionHandler;

use Computer;
use Glpi\Form\Condition\ConditionHandler\UserDevicesConditionHandler;
use Glpi\Form\Condition\ConditionHandler\UserDevicesConditionHandlerConfig;
use Glpi\Form\Condition\ValueOperator;
use Glpi\Form\QuestionType\QuestionTypeUserDevice;
use Glpi\Form\QuestionType\QuestionTypeUserDevicesConfig;
use Override;
use tests\units\Glpi\Form\Condition\AbstractConditionHandler;

final class UserDevicesConditionHandlerEnhancedTest extends AbstractConditionHandler
{
    public static function getConditionHandler(): array
    {
        return [
            // Original handlers for backward compatibility
            new UserDevicesConditionHandler(is_multiple_devices: false),
            new UserDevicesConditionHandler(is_multiple_devices: true),
            
            // Enhanced handlers with context
            new UserDevicesConditionHandler(
                UserDevicesConditionHandlerConfig::withContext(false, 'flexible_single')
            ),
            new UserDevicesConditionHandler(
                UserDevicesConditionHandlerConfig::withContext(true, 'flexible_multiple')
            ),
            
            // Handler with additional parameters
            new UserDevicesConditionHandler(
                new UserDevicesConditionHandlerConfig(
                    is_multiple_devices: false,
                    additional_parameters: ['user_role' => 'admin']
                )
            ),
        ];
    }

    #[Override]
    public static function conditionHandlerProvider(): iterable
    {
        $type = QuestionTypeUserDevice::class;
        $single_config = new QuestionTypeUserDevicesConfig(false);
        $multiple_config = new QuestionTypeUserDevicesConfig(true);

        // Test enhanced single device with EQUALS operator
        yield "EQUALS check - flexible single device" => [
            'question_type'       => $type,
            'condition_operator'  => ValueOperator::EQUALS,
            'condition_value'     => "Computer_42",
            'submitted_answer'    => "Computer_42",
            'expected_result'     => true,
            'question_extra_data' => $single_config,
        ];
        
        yield "NOT_EQUALS check - flexible single device" => [
            'question_type'       => $type,
            'condition_operator'  => ValueOperator::NOT_EQUALS,
            'condition_value'     => "Computer_42",
            'submitted_answer'    => "Printer_23",
            'expected_result'     => true,
            'question_extra_data' => $single_config,
        ];

        // Test enhanced multiple devices with CONTAINS operator
        yield "CONTAINS check - flexible multiple devices" => [
            'question_type'       => $type,
            'condition_operator'  => ValueOperator::CONTAINS,
            'condition_value'     => ["Computer_42"],
            'submitted_answer'    => ["Computer_42", "Printer_23"],
            'expected_result'     => true,
            'question_extra_data' => $multiple_config,
        ];
        
        yield "NOT_CONTAINS check - flexible multiple devices" => [
            'question_type'       => $type,
            'condition_operator'  => ValueOperator::NOT_CONTAINS,
            'condition_value'     => ["Phone_1"],
            'submitted_answer'    => ["Computer_42", "Printer_23"],
            'expected_result'     => true,
            'question_extra_data' => $multiple_config,
        ];

        // Test EMPTY/NOT_EMPTY operators for admin context
        yield "EMPTY check - admin user" => [
            'question_type'       => $type,
            'condition_operator'  => ValueOperator::EMPTY,
            'condition_value'     => null,
            'submitted_answer'    => "",
            'expected_result'     => true,
            'question_extra_data' => $single_config,
        ];
        
        yield "NOT_EMPTY check - admin user" => [
            'question_type'       => $type,
            'condition_operator'  => ValueOperator::NOT_EMPTY,
            'condition_value'     => null,
            'submitted_answer'    => "Computer_42",
            'expected_result'     => true,
            'question_extra_data' => $single_config,
        ];

        // Test backward compatibility - these should work exactly as before
        yield "IS_ITEMTYPE check - backward compatibility" => [
            'question_type'       => $type,
            'condition_operator'  => ValueOperator::IS_ITEMTYPE,
            'condition_value'     => Computer::class,
            'submitted_answer'    => "Computer_42",
            'expected_result'     => true,
            'question_extra_data' => $single_config,
        ];
        
        yield "AT_LEAST_ONE_ITEM_OF_ITEMTYPE check - backward compatibility" => [
            'question_type'       => $type,
            'condition_operator'  => ValueOperator::AT_LEAST_ONE_ITEM_OF_ITEMTYPE,
            'condition_value'     => [Computer::class],
            'submitted_answer'    => ["Computer_42", "Printer_23"],
            'expected_result'     => true,
            'question_extra_data' => $multiple_config,
        ];
    }
}