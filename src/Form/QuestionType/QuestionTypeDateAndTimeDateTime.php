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

final class QuestionTypeDateAndTimeDateTime extends QuestionTypeDateAndTime
{
    #[Override]
    public function getName(): string
    {
        return __('DateTime');
    }

    #[Override]
    public function getWeight(): int
    {
        return 20;
    }

    #[Override]
    public function formatAnswer($answer): string
    {
        return (new DateTime($answer))->format('Y-m-d H:i:s');
    }

    #[Override]
    public function getCurrentTimeOptionLabel(): string
    {
        return __('Use current date and time as default value');
    }

    #[Override]
    public function getCurrentTimePlaceholder(): string
    {
        return __('Current date and time');
    }

    #[Override]
    public function getDefaultValue(?Question $question): string
    {
        $value = '';
        if ($question !== null) {
            if ($this->isDefaultValueCurrentTime($question)) {
                $value = (new DateTime())->format('Y-m-d H:i:s');
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

            {{ fields.datetimeField(
                'default_value',
                question is not null ? question.fields.default_value : '',
                "",
                {
                    'full_width'            : true,
                    'no_label'              : true,
                    'rand'                  : rand,
                    'disabled'              : is_default_value_current_time,
                    'mb'                    : 'mb-2',
                    'additional_attributes' : {
                        'placeholder'       : 'DateTime',
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

            {{ fields.datetimeField(
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
