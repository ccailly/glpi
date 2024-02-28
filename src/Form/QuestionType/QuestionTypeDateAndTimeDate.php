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

use DateTime;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Form\Question;
use Override;

final class QuestionTypeDateAndTimeDate extends QuestionTypeDateAndTime
{
    #[Override]
    public function getName(): string
    {
        return _n('Date', 'Dates', 1);
    }

    #[Override]
    public function getWeight(): int
    {
        return 10;
    }

    #[Override]
    public function formatAnswer($answer): string
    {
        return $answer;
    }

    #[Override]
    public function getCurrentTimeOptionLabel(): string
    {
        return __('Use current date as default value');
    }

    #[Override]
    public function getCurrentTimePlaceholder(): string
    {
        return __('Current date');
    }

    #[Override]
    public function getDefaultValue(?Question $question): string
    {
        $value = '';
        if ($question !== null) {
            if ($this->isDefaultValueCurrentTime($question)) {
                $value = (new DateTime())->format('Y-m-d');
            } else {
                $value = $question->fields['default_value'];
            }
        }

        return $value;
    }


    #[Override]
    public function renderAdminstrationTemplate(
        ?Question $question = null
    ): string {
        $template = <<<TWIG
            {% import 'components/form/fields_macros.html.twig' as fields %}

            {% set rand = random() %}

            {{ fields.dateField(
                "default_value",
                value,
                "",
                {
                    'full_width'            : true,
                    'no_label'              : true,
                    'rand'                  : rand,
                    'disabled'              : is_default_value_current_time,
                    'mb'                    : 'mb-2',
                    'additional_attributes' : {
                        'placeholder'       : 'Date',
                    }
                }
            ) }}
TWIG;
        $template .= parent::renderAdminstrationTemplate($question);

        $twig = TemplateRenderer::getInstance();
        return $twig->renderFromStringTemplate($template, [
            'question'          => $question,
            'value'             => $this->getDefaultValue($question),
            'is_default_value_current_time' => $this->isDefaultValueCurrentTime($question),
        ]);
    }

    #[Override]
    public function renderEndUserTemplate(
        Question $question,
    ): string {
        $template = <<<TWIG
            {% import 'components/form/fields_macros.html.twig' as fields %}

            {{ fields.dateField(
                "answers[" ~ question.fields.id ~ "]",
                value,
                "",
                {
                    'full_width'   : true,
                    'no_label'     : true,
                }
            ) }}
TWIG;

        $twig = TemplateRenderer::getInstance();
        return $twig->renderFromStringTemplate($template, [
            'question'   => $question,
            'value'      => $this->getDefaultValue($question),
        ]);
    }
}
