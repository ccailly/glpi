<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2024 Teclib' and contributors.
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

namespace tests\units\Glpi\Form\QuestionType;

use DbTestCase;

class AbstractQuestionTypeShortAnswer extends DbTestCase
{
    public function onQuestionTypeChangeDataProvider(): iterable
    {
        yield [
            'old_type' => 'Glpi\Form\QuestionType\QuestionTypeShortText',
            'new_type' => 'Glpi\Form\QuestionType\QuestionTypeShortText',
            'value'    => 'This is a short text',
            'expected' => 'This is a short text'
        ];

        yield [
            'old_type' => 'Glpi\Form\QuestionType\QuestionTypeShortText',
            'new_type' => 'Glpi\Form\QuestionType\QuestionTypeEmail',
            'value'    => 'This is a short text',
            'expected' => 'This is a short text'
        ];

        yield [
            'old_type' => 'Glpi\Form\QuestionType\QuestionTypeShortText',
            'new_type' => 'Glpi\Form\QuestionType\QuestionTypeNumber',
            'value'    => 'This is a short text',
            'expected' => null
        ];

        yield [
            'old_type' => 'Glpi\Form\QuestionType\QuestionTypeDateTime',
            'new_type' => 'Glpi\Form\QuestionType\QuestionTypeShortText',
            'value'    => '2021-09-01 00:00:00',
            'expected' => null
        ];
    }

    /**
     * Test the onQuestionTypeChange method
     *
     * @dataProvider onQuestionTypeChangeDataProvider
     * @return void
     */
    public function testOnQuestionTypeChange(string $old_type, string $new_type, string $value, ?string $expected): void
    {
        $question_type = new $new_type();
        $this->variable($question_type->onQuestionTypeChange($old_type, $new_type, $value))->isEqualTo($expected);
    }
}
