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

namespace tests\units\Glpi\Form\Destination\CommonITILField;

use DbTestCase;
use Glpi\Form\AnswersHandler\AnswersHandler;
use Glpi\Form\Destination\CommonITILField\UrgencyField;
use Glpi\Form\Destination\FormDestinationTicket;
use Glpi\Form\Form;
use Glpi\Form\QuestionType\QuestionTypeUrgency;
use Glpi\Tests\FormBuilder;
use Glpi\Tests\FormTesterTrait;
use Ticket;
use TicketTemplate;
use TicketTemplatePredefinedField;

final class UrgencyFieldTest extends DbTestCase
{
    use FormTesterTrait;

    public function testUrgencyFromTemplate(): void
    {
        // The default GLPI's template use "INCIDENT"
        $this->checkUrgencyFieldConfiguration(
            form: $this->createAndGetFormWithMultipleUrgencyQuestions(),
            config: ['value' => UrgencyField::CONFIG_FROM_TEMPLATE],
            answers: [],
            expected_request_type: 3 // Default urgency
        );

        // Set the default urgency as "Very high" using predefined fields
        $this->createItem(TicketTemplatePredefinedField::class, [
            'tickettemplates_id' => getItemByTypeName(TicketTemplate::class, "Default", true),
            'num' => 10, // Urgency
            'value' => 5, // Very high
        ]);
        $this->checkUrgencyFieldConfiguration(
            form: $this->createAndGetFormWithMultipleUrgencyQuestions(),
            config: ['value' => UrgencyField::CONFIG_FROM_TEMPLATE],
            answers: [],
            expected_request_type: 5 // Very high
        );
    }

    public function testSpecificUrgency(): void
    {
        $form = $this->createAndGetFormWithMultipleUrgencyQuestions();

        // Specific value: High
        $this->checkUrgencyFieldConfiguration(
            form: $form,
            config: [
                'value' => UrgencyField::CONFIG_SPECIFIC_VALUE,
                UrgencyField::EXTRA_CONFIG_URGENCY => 4, // High
            ],
            answers: [],
            expected_request_type: 4 // High
        );

        // Specific value: Very low
        $this->checkUrgencyFieldConfiguration(
            form: $form,
            config: [
                'value' => UrgencyField::CONFIG_SPECIFIC_VALUE,
                UrgencyField::EXTRA_CONFIG_URGENCY => 1, // Very low
            ],
            answers: [],
            expected_request_type: 1 // Very low
        );
    }

    public function testUrgencyFromSpecificQuestion(): void
    {
        $form = $this->createAndGetFormWithMultipleUrgencyQuestions();

        // Using answer from first question
        $this->checkUrgencyFieldConfiguration(
            form: $form,
            config: [
                'value' => UrgencyField::CONFIG_SPECIFIC_ANSWER,
                UrgencyField::EXTRA_CONFIG_QUESTION_ID => $this->getQuestionId($form, "Urgency 1"),
            ],
            answers: [
                "Urgency 1" => 2, // Low
                "Urgency 2" => 5, // Very high
            ],
            expected_request_type: 2 // Low
        );

        // Using answer from second question
        $this->checkUrgencyFieldConfiguration(
            form: $form,
            config: [
                'value' => UrgencyField::CONFIG_SPECIFIC_ANSWER,
                UrgencyField::EXTRA_CONFIG_QUESTION_ID => $this->getQuestionId($form, "Urgency 2"),
            ],
            answers: [
                "Urgency 1" => 2, // Low
                "Urgency 2" => 5, // Very high
            ],
            expected_request_type: 5 // Very high
        );
    }

    public function testUrgencyFromLastValidQuestion(): void
    {
        $form = $this->createAndGetFormWithMultipleUrgencyQuestions();

        // With multiple answers submitted
        $this->checkUrgencyFieldConfiguration(
            form: $form,
            config: [
                'value' => UrgencyField::CONFIG_LAST_VALID_ANSWER,
            ],
            answers: [
                "Urgency 1" => 2, // Low
                "Urgency 2" => 5, // Very high
            ],
            expected_request_type: 5 // Very high
        );

        // Only first answer was submitted
        $this->checkUrgencyFieldConfiguration(
            form: $form,
            config: [
                'value' => UrgencyField::CONFIG_LAST_VALID_ANSWER,
            ],
            answers: [
                "Urgency 1" => 2, // Low
            ],
            expected_request_type: 2 // Low
        );

        // Only second answer was submitted
        $this->checkUrgencyFieldConfiguration(
            form: $form,
            config: [
                'value' => UrgencyField::CONFIG_LAST_VALID_ANSWER,
            ],
            answers: [
                "Urgency 2" => 5, // Very high
            ],
            expected_request_type: 5 // Very high
        );

        // No answers, fallback to default value
        $this->checkUrgencyFieldConfiguration(
            form: $form,
            config: [
                'value' => UrgencyField::CONFIG_LAST_VALID_ANSWER,
            ],
            answers: [],
            expected_request_type: 3 // Default urgency
        );

        // Try again with a different template value
        $this->createItem(TicketTemplatePredefinedField::class, [
            'tickettemplates_id' => getItemByTypeName(TicketTemplate::class, "Default", true),
            'num' => 10, // Urgency
            'value' => 4, // High
        ]);
        $this->checkUrgencyFieldConfiguration(
            form: $form,
            config: [
                'value' => UrgencyField::CONFIG_LAST_VALID_ANSWER,
            ],
            answers: [],
            expected_request_type: 4 // High
        );
    }

    private function checkUrgencyFieldConfiguration(
        Form $form,
        array $config,
        array $answers,
        int $expected_request_type
    ): void {
        // Insert config
        $destinations = $form->getDestinations();
        $this->assertCount(1, $destinations);
        $destination = current($destinations);
        $this->updateItem(
            $destination::getType(),
            $destination->getId(),
            ['config' => ['urgency' => $config]],
            ["config"],
        );

        // The provider use a simplified answer format to be more readable.
        // Rewrite answers into expected format.
        $formatted_answers = [];
        foreach ($answers as $question => $answer) {
            $key = $this->getQuestionId($form, $question);
            $formatted_answers[$key] = $answer;
        }

        // Submit form
        $answers_handler = AnswersHandler::getInstance();
        $answers = $answers_handler->saveAnswers(
            $form,
            $formatted_answers,
            getItemByTypeName(\User::class, TU_USER, true)
        );

        // Get created ticket
        $created_items = $answers->getCreatedItems();
        $this->assertCount(1, $created_items);
        $ticket = current($created_items);

        // Check request type
        $this->assertEquals($expected_request_type, $ticket->fields['urgency']);
    }

    private function createAndGetFormWithMultipleUrgencyQuestions(): Form
    {
        $builder = new FormBuilder();
        $builder->addQuestion("Urgency 1", QuestionTypeUrgency::class);
        $builder->addQuestion("Urgency 2", QuestionTypeUrgency::class);
        $builder->addDestination(
            FormDestinationTicket::class,
            "My ticket",
        );
        return $this->createForm($builder);
    }
}
