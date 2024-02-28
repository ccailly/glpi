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

final class QuestionTypeDateAndTimeTime extends QuestionTypeDateAndTime
{
    #[Override]
    public function getName(): string
    {
        return _n('Time', 'Times', 1);
    }

    #[Override]
    public function getWeight(): int
    {
        return 30;
    }

    #[Override]
    public function formatAnswer($answer): string
    {
        return $answer;
    }

    #[Override]
    public function getCurrentTimeOptionLabel(): string
    {
        return __('Use current time as default value');
    }

    #[Override]
    public function getCurrentTimePlaceholder(): string
    {
        return __('Current time');
    }

    #[Override]
    public function getDefaultValue(?Question $question): string
    {
        $value = '';
        if ($question !== null) {
            if ($this->isDefaultValueCurrentTime($question)) {
                $value = (new DateTime())->format('H:i:s');
            } else {
                $value = $question->fields['default_value'];
            }
        }

        return $value;
    }

    #[Override]
    public function renderAdministrationTemplate(
        ?Question $question = null
    ): string {
        $template = <<<TWIG
            {% import 'components/form/fields_macros.html.twig' as fields %}

            {% set rand = random() %}

            {{ fields.timeField(
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
                        'placeholder'       : 'Time',
                    }
                }
            ) }}
TWIG;
        $template .= parent::renderAdministrationTemplate($question);

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
