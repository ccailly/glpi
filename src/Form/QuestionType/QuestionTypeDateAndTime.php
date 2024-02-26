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

namespace Glpi\Form\QuestionType;

use Glpi\Application\View\TemplateRenderer;
use Glpi\Form\Question;
use Override;

/**
 * Short answers are single line inputs used to answer simple questions.
 */
abstract class QuestionTypeDateAndTime implements QuestionTypeInterface
{
    #[Override]
    public function __construct()
    {
    }

    /**
     * Format the answer to be displayed
     *
     * @param mixed $answer
     * @return string
     */
    abstract public function formatAnswer($answer): string;

    /**
     * Get the label for the current time option
     *
     * @return string
     */
    abstract public function getCurrentTimeOptionLabel(): string;

    /**
     * Get the default value for the question type
     *
     * @return string
     */
    abstract public function getDefaultValue(?Question $question): string;

    /**
     * Check if the default value is the current time
     *
     * @param ?Question $question
     * @return string
     */
    public function isDefaultValueCurrentTime(?Question $question): bool
    {
        if ($question === null) {
            return false;
        }

        return $question->getExtraDatas()['is_default_value_current_time'] ?? false;
    }

    #[Override]
    public function renderAdminstrationTemplate(
        ?Question $question = null
    ): string {
        $template = <<<TWIG
            <script>
                $(document).ready(function() {
                    // Check if the global variable exists
                    if (typeof window.flatpickr_configs === 'undefined') {
                        window.flatpickr_configs = {};
                    }

                    // Check if the flatpickr instance exists
                    if (
                        $('#default-value_{{ rand }}').get(0) === undefined
                        || $('#default-value_{{ rand }}').get(0)._flatpickr === undefined
                    ) {
                        return;
                    }

                    // Save the flatpickr config in a global variable
                    window.flatpickr_configs['#default-value_{{ rand }}'] = $('#default-value_{{ rand }}').get(0)._flatpickr.config;
                });
            </script>
TWIG;

        return $template;
    }

    #[Override]
    public function renderAdminstrationOptionsTemplate(
        ?Question $question = null
    ): string {
        $template = <<<TWIG
        {% set rand = random() %}

        <label class="form-check">
            {# We use a hidden input to send the value when the checkbox is unchecked #}
            <input type="hidden" name="is_default_value_current_time" value="0">
            <input id="is_default_value_current_time_{{ rand }}" class="form-check-input" type="checkbox"
                name="is_default_value_current_time" value="1" {{ is_default_value_current_time ? 'checked' : '' }}
                onchange="handleDefaultValueCurrentTimeCheckbox_{{ rand }}(this)">
            <span class="form-check-label">{{ is_default_value_current_time_label }}</span>
        </label>

        <script>
            {# Disabled the default value input if the checkbox is checked #}
            function handleDefaultValueCurrentTimeCheckbox_{{ rand }}(input) {
                const isChecked = $(input).is(':checked');
                $(input).parent().parent().parent().find('div .flatpickr').find('input[type="text"]').prop('disabled', isChecked);
            }
        </script>
TWIG;

        $twig = TemplateRenderer::getInstance();
        return $twig->renderFromStringTemplate($template, [
            'question' => $question,
            'is_default_value_current_time' => $this->isDefaultValueCurrentTime($question),
            'is_default_value_current_time_label' => $this->getCurrentTimeOptionLabel(),
        ]);
    }

    #[Override]
    public function renderAnswerTemplate($answer): string
    {
        $template = <<<TWIG
            <div class="form-control-plaintext">{{ answer }}</div>
TWIG;

        $twig = TemplateRenderer::getInstance();
        return $twig->renderFromStringTemplate($template, [
            'answer' => $this->formatAnswer($answer),
        ]);
    }

    #[Override]
    public function getCategory(): QuestionTypeCategory
    {
        return QuestionTypeCategory::DATE_AND_TIME;
    }
}
