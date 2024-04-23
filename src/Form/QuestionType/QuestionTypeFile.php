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

use DocumentType;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Form\Question;
use Override;

/**
 * Long answers are multiple lines inputs used to answer questions with as much details as needed.
 */
final class QuestionTypeFile implements QuestionTypeInterface
{
    #[Override]
    public function __construct()
    {
    }

    #[Override]
    public static function loadJavascriptFiles(): array
    {
        return [];
    }

    #[Override]
    public static function formatDefaultValueForDB(mixed $value): ?string
    {
        return $value;
    }

    #[Override]
    public static function validateExtraDataInput(array $input): bool
    {
        $allowed_keys = [
            'multiple_files',
            'filter_types',
            'allowed_types'
        ];

        return empty(array_diff(array_keys($input), $allowed_keys))
            && array_reduce(array_keys($input), function ($carry, $key) use ($input) {
                return $carry && ($key === 'allowed_types' || preg_match('/^[01]$/', $input[$key]));
            }, true);
    }

    #[Override]
    public static function prepareExtraData(array $input): array
    {
        // Allowed types are stored as an array, join them to store them as a string
        if (isset($input['allowed_types'])) {
            $input['allowed_types'] = implode(',', $input['allowed_types']);
        }

        return $input;
    }

    /**
     * Check if the question allows multiple files to be uploaded.
     *
     * @param Question|null $question
     * @return bool
     */
    public static function isMultipleFiles(?Question $question): bool
    {
        if ($question === null) {
            return false;
        }

        return $question->getExtraDatas()['multiple_files'] ?? false;
    }

    /**
     * Check if the question allows filtering the types of files that can be uploaded.
     *
     * @param Question|null $question
     * @return bool
     */
    public static function isFilterTypes(?Question $question): bool
    {
        if ($question === null) {
            return false;
        }

        return $question->getExtraDatas()['filter_types'] ?? false;
    }

    /**
     * Get the list of allowed types of files that can be uploaded.
     *
     * @param Question|null $question
     * @return array
     */
    public static function getAllowedTypes(?Question $question): array
    {
        if ($question === null) {
            return [];
        }

        return explode(',', $question->getExtraDatas()['allowed_types'] ?? '');
    }

    /**
     * Get the list of extensions that can be uploaded.
     *
     * @return array
     */
    public static function getUploadableExtensions(): array
    {
        $extensions = [];
        $documentType = new DocumentType();
        foreach ($documentType->find(['is_uploadable' => 1]) as $type) {
            $extensions[$type['ext']] = sprintf('%s (%s)', $type['name'], $type['ext']);
        }

        return $extensions;
    }

    #[Override]
    public function renderAdministrationTemplate(?Question $question): string
    {
        $template = <<<TWIG
            {% import 'components/form/fields_macros.html.twig' as fields %}

            {{ fields.fileField(
                'default_value',
                '',
                '',
                {
                    'init'           : question is not null ? true: false,
                    'no_label'       : true,
                    'full_width'     : true,
                    'multiple'       : false,
                    'add_field_class': multiple_files ? 'd-none'  : '',
                    'mb'             : 'mb-1'
                }
            ) }}

            {{ fields.fileField(
                'default_value',
                '',
                '',
                {
                    'init'           : question is not null ? true: false,
                    'disabled'       : true,
                    'simple'         : true,
                    'no_label'       : true,
                    'full_width'     : true,
                    'multiple'       : true,
                    'add_field_class': multiple_files ? ''        : 'd-none',
                    'mb'             : 'mb-1'
                }
            ) }}

            <div data-glpi-form-editor-specific-question-options data-glpi-form-editor-question-extra-details
                class="pb-2">
                {{ fields.dropdownArrayField(
                    'allowed_types',
                    '',
                    types,
                    'Allowed types',
                    {
                        'init'                 : question is not null ? true : false,
                        'is_horizontal'        : false,
                        'values'               : allowed_types,
                        'multiple'             : true,
                        'add_field_class'      : filter_types ? '' : 'd-none',
                        'additional_attributes': 'data-glpi-form-editor-specific-question-extra-data',
                    }
                ) }}
            </div>
TWIG;

        $twig = TemplateRenderer::getInstance();
        return $twig->renderFromStringTemplate($template, [
            'question'       => $question,
            'multiple_files' => self::isMultipleFiles($question),
            'filter_types'   => self::isFilterTypes($question),
            'allowed_types'  => self::getAllowedTypes($question),
            'types'          => self::getUploadableExtensions()
        ]);
    }

    #[Override]
    public function renderAdministrationOptionsTemplate(?Question $question): string
    {
        $template = <<<TWIG
            <div class="d-flex gap-2">
                <label class="form-check form-switch mb-0">
                    <input type="hidden" name="multiple_files" value="0"
                    data-glpi-form-editor-specific-question-extra-data>
                    <input class="form-check-input" type="checkbox" name="multiple_files"
                        value="1" {{ multiple_files ? 'checked' : '' }}
                        data-glpi-form-editor-specific-question-extra-data
                        data-glpi-form-editor-specific-question-extra-data-multiple-files
                        onchange="glpi_form_editor_file_question_multiple_handler(this)">
                    <span class="form-check-label">{{ multiple_files_label }}</span>
                </label>

                <label class="form-check form-switch mb-0">
                    <input type="hidden" name="filter_types" value="0"
                    data-glpi-form-editor-specific-question-extra-data>
                    <input class="form-check-input" type="checkbox" name="filter_types"
                        value="1" {{ filter_types ? 'checked' : '' }}
                        data-glpi-form-editor-specific-question-extra-data
                        data-glpi-form-editor-specific-question-extra-data-filter-types
                        onchange="glpi_form_editor_file_question_filter_handler(this)">
                    <span class="form-check-label">{{ filter_types_label }}</span>
                </label>
            </div>

            {% if question is null %}
                <script>
                    if (typeof window.glpi_form_editor_file_question_multiple_handler === 'undefined') {
                        window.glpi_form_editor_file_question_multiple_handler = function(input) {
                            $(input).closest('[data-glpi-form-editor-question-details]')
                                .find('div[data-glpi-form-editor-question-type-specific]')
                                .children('div').filter(':not([data-glpi-form-editor-specific-question-options])')
                                .toggleClass('d-none');
                        };
                    }

                    if (typeof window.glpi_form_editor_file_question_filter_handler === 'undefined') {
                        window.glpi_form_editor_file_question_filter_handler = function(input) {
                            $(input).closest('[data-glpi-form-editor-question-details]')
                                .find('div[data-glpi-form-editor-question-type-specific]')
                                .children('[data-glpi-form-editor-specific-question-options]')
                                .children('div')
                                .toggleClass('d-none');
                        };
                    }
                </script>
            {% endif %}
TWIG;

        $twig = TemplateRenderer::getInstance();
        return $twig->renderFromStringTemplate($template, [
            'question'             => $question,
            'multiple_files'       => self::isMultipleFiles($question),
            'multiple_files_label' => __('Allow multiple files'),
            'filter_types'         => self::isFilterTypes($question),
            'filter_types_label'   => __('Filter allowed types')
        ]);
    }

    #[Override]
    public function renderEndUserTemplate(Question $question): string
    {
        $template = <<<TWIG
            {% import 'components/form/fields_macros.html.twig' as fields %}

            {{ fields.fileField(
                "answers[" ~ question.fields.id ~ "]",
                "",
                "",
                {
                    'init'                 : true,
                    'no_label'             : true,
                    'full_width'           : true,
                }
            ) }}
TWIG;

        $twig = TemplateRenderer::getInstance();
        return $twig->renderFromStringTemplate($template, [
            'question' => $question,
            'multiple_files' => self::isMultipleFiles($question),
            'allowed_types' => implode(',', preg_filter('/^/', '.', self::getAllowedTypes($question)))
        ]);
    }

    #[Override]
    public function renderAnswerTemplate($answer): string
    {
        $template = <<<TWIG
            <div class="form-control-plaintext">{{ answer|safe_html }}</div>
TWIG;

        $twig = TemplateRenderer::getInstance();
        return $twig->renderFromStringTemplate($template, [
            'answer' => $answer,
        ]);
    }

    #[Override]
    public function getName(): string
    {
        return __("File");
    }

    #[Override]
    public function getCategory(): QuestionTypeCategory
    {
        return QuestionTypeCategory::FILE;
    }

    #[Override]
    public function getWeight(): int
    {
        return 10;
    }
}
