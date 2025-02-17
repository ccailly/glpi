<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
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

namespace Glpi\Form\Migration;

use Glpi\DBAL\QuerySubQuery;
use Glpi\DBAL\QueryUnion;
use Glpi\Form\Category;
use Glpi\Form\Comment;
use Glpi\Form\Form;
use Glpi\Form\Question;
use Glpi\Form\QuestionType\QuestionTypeCheckbox;
use Glpi\Form\QuestionType\QuestionTypeDateTime;
use Glpi\Form\QuestionType\QuestionTypeDropdown;
use Glpi\Form\QuestionType\QuestionTypeEmail;
use Glpi\Form\QuestionType\QuestionTypeFile;
use Glpi\Form\QuestionType\QuestionTypeItem;
use Glpi\Form\QuestionType\QuestionTypeItemDropdown;
use Glpi\Form\QuestionType\QuestionTypeLongText;
use Glpi\Form\QuestionType\QuestionTypeNumber;
use Glpi\Form\QuestionType\QuestionTypeRadio;
use Glpi\Form\QuestionType\QuestionTypeRequester;
use Glpi\Form\QuestionType\QuestionTypeRequestType;
use Glpi\Form\QuestionType\QuestionTypeShortText;
use Glpi\Form\QuestionType\QuestionTypeUrgency;
use Glpi\Form\Section;
use LogicException;

final class FormMigration
{
    private MigrationManager $migrationManager;
    private FormMigrationResult $result;

    public function __construct(MigrationManager $migrationManager, FormMigrationResult $result)
    {
        $this->migrationManager = $migrationManager;
        $this->result = $result;
    }

    /**
     * Retrieve the map of types to convert
     *
     * @return array
     */
    public function getTypesConvertMap(): array
    {
        return [
            // TODO: We do not have a question of type "Actor",
            // we have more specific types: "Assignee", "Requester" and "Observer"
            'actor'       => QuestionTypeRequester::class,

            'checkboxes'  => QuestionTypeCheckbox::class,
            'date'        => QuestionTypeDateTime::class,
            'datetime'    => QuestionTypeDateTime::class,
            'dropdown'    => QuestionTypeItemDropdown::class,
            'email'       => QuestionTypeEmail::class,
            'file'        => QuestionTypeFile::class,
            'float'       => QuestionTypeNumber::class,
            'glpiselect'  => QuestionTypeItem::class,
            'integer'     => QuestionTypeNumber::class,
            'multiselect' => QuestionTypeDropdown::class,
            'radios'      => QuestionTypeRadio::class,
            'requesttype' => QuestionTypeRequestType::class,
            'select'      => QuestionTypeDropdown::class,
            'textarea'    => QuestionTypeLongText::class,
            'text'        => QuestionTypeShortText::class,
            'time'        => QuestionTypeDateTime::class,
            'urgency'     => QuestionTypeUrgency::class,

            // Description is replaced by a new block : Comment
            'description' => null,

            // TODO: Must be implemented
            'fields'      => null,
            'tag'         => null,

            // TODO: This types are not supported by the new form system
            // we need to define alternative ways to handle them
            'hidden'      => null,
            'hostname'    => null,
            'ip'          => null,
            'ldapselect'  => null,
            'undefined'   => null,
        ];
    }

    public function processMigrationOfForms(): void
    {
        $this->processMigrationOfFormCategories();
        $this->processMigrationOfBasicProperties();
        $this->processMigrationOfSections();
        $this->processMigrationOfQuestions();
        $this->processMigrationOfComments();
        $this->updateBlockHorizontalRank();
    }

    public function processMigrationOfFormCategories(): void
    {
        // Retrieve data from glpi_plugin_formcreator_categories table
        $raw_form_categories = $this->migrationManager->getDB()->request([
            'SELECT' => ['id', 'name', 'plugin_formcreator_categories_id'],
            'FROM'   => 'glpi_plugin_formcreator_categories'
        ]);

        // Sort items by their parent dependencies
        $raw_form_categories = $this->migrationManager->sortItems(
            iterator_to_array($raw_form_categories),
            'id',
            'plugin_formcreator_categories_id'
        );

        foreach ($raw_form_categories as $raw_form_category) {
            $form_category = new Category();
            $id = $form_category->add([
                'name'                => $raw_form_category['name'],
                'forms_categories_id' => $this->migrationManager->getKeyMap(
                    'glpi_plugin_formcreator_categories',
                    $raw_form_category['plugin_formcreator_categories_id']
                )
            ]);

            $this->migrationManager->addKeyMap('glpi_plugin_formcreator_categories', $raw_form_category['id'], $id);
        }
    }

    public function processMigrationOfBasicProperties(): void
    {
        // Retrieve data from glpi_plugin_formcreator_forms table
        $raw_forms = $this->migrationManager->getDB()->request([
            'SELECT' => [
                'id',
                'description AS header',
                'name',
                'plugin_formcreator_categories_id',
                'entities_id',
                'is_recursive',
                'is_visible AS is_active'
            ],
            'FROM'   => 'glpi_plugin_formcreator_forms'
        ]);

        foreach ($raw_forms as $raw_form) {
            $form = new Form();
            $id = $form->add([
                'name'                  => $raw_form['name'],
                'header'                => $raw_form['header'],
                'forms_categories_id'   => $this->migrationManager->getKeyMap(
                    'glpi_plugin_formcreator_categories',
                    $raw_form['plugin_formcreator_categories_id']
                ) ?? 0,
                'entities_id'           => $raw_form['entities_id'],
                'is_recursive'          => $raw_form['is_recursive'],
                'is_active'             => $raw_form['is_active'],
                '_do_not_init_sections' => true
            ]);

            $this->migrationManager->addKeyMap('glpi_plugin_formcreator_forms', $raw_form['id'], $id);
        }
    }

    public function processMigrationOfSections(): void
    {
        // Retrieve data from glpi_plugin_formcreator_sections table
        $raw_sections = $this->migrationManager->getDB()->request([
            'SELECT' => ['id', 'name', 'plugin_formcreator_forms_id', 'order'],
            'FROM'   => 'glpi_plugin_formcreator_sections'
        ]);

        foreach ($raw_sections as $raw_section) {
            $section = new Section();
            $id = $section->add([
                Form::getForeignKeyField() => $this->migrationManager->getKeyMap(
                    'glpi_plugin_formcreator_forms',
                    $raw_section['plugin_formcreator_forms_id']
                ),
                'name'                     => $raw_section['name'],
                'rank'                     => $raw_section['order'] - 1 // New rank is 0-based
            ]);

            $this->migrationManager->addKeyMap('glpi_plugin_formcreator_sections', $raw_section['id'], $id);
        }
    }

    public function processMigrationOfQuestions(): void
    {
        $types_convert_map = $this->getTypesConvertMap();
        $forms_questions = [];

        // Initialize stats for all forms
        $raw_forms = $this->migrationManager->getDB()->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => 'glpi_plugin_formcreator_forms'
        ]);
        foreach ($raw_forms as $raw_form) {
            $forms_questions[$raw_form['id']] = [
                'name' => $raw_form['name'],
                'total' => 0,
                'migrated' => 0,
                'partial' => 0,
                'skipped' => 0,
            ];
        }

        // Process questions
        $raw_questions = array_values(iterator_to_array($this->migrationManager->getDB()->request([
            'SELECT' => [
                'id',
                'name',
                'plugin_formcreator_sections_id',
                'fieldtype',
                'required',
                'default_values',
                'itemtype',
                'values',
                'description',
                'row',
                'col'
            ],
            'FROM'   => 'glpi_plugin_formcreator_questions',
            'WHERE'  => ['NOT' => ['fieldtype' => 'description']],
            'ORDER'  => ['plugin_formcreator_sections_id', 'row', 'col']
        ])));

        foreach ($raw_questions as $raw_question) {
            $section_id = $raw_question['plugin_formcreator_sections_id'];
            $form_id = $this->getFormIdFromSectionId($section_id);
            $forms_questions[$form_id]['total']++;

            $fieldtype = $raw_question['fieldtype'];
            $type_class = $types_convert_map[$fieldtype] ?? null;

            if (empty($type_class)) {
                $forms_questions[$form_id]['skipped']++;
                $this->result->addSkippedQuestion(
                    $forms_questions[$form_id]['name'],
                    $raw_question['name'],
                    $fieldtype,
                    'Question type not supported'
                );
                continue;
            }

            try {
                $default_value = null;
                $extra_data = null;
                if (is_a($type_class, 'Glpi\Form\Migration\FormQuestionDataConverterInterface', true)) {
                    $converter     = new $type_class();
                    $default_value = $converter->convertDefaultValue($raw_question);
                    $extra_data    = $converter->convertExtraData($raw_question);
                }

                $question = new Question();
                $data = array_filter([
                    Section::getForeignKeyField() => $this->migrationManager->getKeyMap(
                        'glpi_plugin_formcreator_sections',
                        $raw_question['plugin_formcreator_sections_id']
                    ),
                    'name'                        => $raw_question['name'],
                    'type'                        => $type_class,
                    'is_mandatory'                => $raw_question['required'],
                    'vertical_rank'               => $raw_question['row'],
                    'horizontal_rank'             => $raw_question['col'],
                    'description'                 => !empty($raw_question['description'])
                                                      ? $raw_question['description']
                                                      : null,
                    'default_value'               => $default_value,
                    'extra_data'                  => $extra_data
                ], fn ($value) => $value !== null);
                $id = $question->add($data); // Filter array to remove null values

                $this->migrationManager->addKeyMap('glpi_plugin_formcreator_questions', $raw_question['id'], $id);
                $forms_questions[$form_id]['migrated']++;
            } catch (\Exception $e) {
                $forms_questions[$form_id]['skipped']++;
                $this->result->addSkippedQuestion(
                    $forms_questions[$form_id]['name'],
                    $raw_question['name'],
                    $fieldtype,
                    $e->getMessage()
                );
            }
        }

        // Update form status based on questions migration
        foreach ($forms_questions as $form_id => $stats) {
            if ($stats['total'] === 0) {
                $this->result->addFormStatus(
                    $stats['name'],
                    FormMigrationResult::STATUS_SUCCESS,
                    'No questions in this form'
                );
                continue;
            }

            if ($stats['migrated'] === $stats['total']) {
                $this->result->addFormStatus(
                    $stats['name'],
                    FormMigrationResult::STATUS_SUCCESS
                );
            } elseif ($stats['migrated'] === 0) {
                $this->result->addFormStatus(
                    $stats['name'],
                    FormMigrationResult::STATUS_FAILED,
                    'No questions were successfully migrated'
                );
            } else {
                $this->result->addFormStatus(
                    $stats['name'],
                    FormMigrationResult::STATUS_PARTIAL,
                    sprintf(
                        'Migrated: %d, Skipped: %d',
                        $stats['migrated'],
                        $stats['skipped']
                    )
                );
            }
        }
    }

    private function getFormIdFromSectionId(int $section_id): int
    {
        $raw_section = $this->migrationManager->getDB()->request([
            'SELECT' => ['plugin_formcreator_forms_id'],
            'FROM'   => 'glpi_plugin_formcreator_sections',
            'WHERE'  => ['id' => $section_id]
        ])->current();

        return $raw_section['plugin_formcreator_forms_id'];
    }

    public function processMigrationOfComments(): void
    {
        // Retrieve data from glpi_plugin_formcreator_questions table
        $raw_comments = $this->migrationManager->getDB()->request([
            'SELECT' => [
                'id',
                'name',
                'plugin_formcreator_sections_id',
                'fieldtype',
                'required',
                'default_values',
                'description',
                'row',
                'col'
            ],
            'FROM'   => 'glpi_plugin_formcreator_questions',
            'WHERE'  => ['fieldtype' => 'description']
        ]);

        foreach ($raw_comments as $raw_comment) {
            $comment = new Comment();
            $id = $comment->add([
                Section::getForeignKeyField() => $this->migrationManager->getKeyMap(
                    'glpi_plugin_formcreator_sections',
                    $raw_comment['plugin_formcreator_sections_id']
                ),
                'name'                        => $raw_comment['name'],
                'description'                 => $raw_comment['description'],
                'vertical_rank'               => $raw_comment['row'],
                'horizontal_rank'             => $raw_comment['col']
            ]);

            $this->migrationManager->addKeyMap('glpi_plugin_formcreator_questions', $raw_comment['id'], $id);
        }
    }

    /**
     * Update horizontal rank of questions and comments to be consistent with the new form system
     *
     * @return void
     */
    public function updateBlockHorizontalRank(): void
    {
        $tables = [Question::getTable(), Comment::getTable()];

        $getSubQuery = function (string $column) {
            return new QuerySubQuery([
                'SELECT' => '*',
                'FROM'   => new QuerySubQuery([
                    'SELECT' => $column,
                    'FROM'   => new QueryUnion([
                        [
                            'SELECT' => ['forms_sections_id', 'vertical_rank', 'horizontal_rank'],
                            'FROM'   => Question::getTable(),
                        ],
                        [
                            'SELECT' => ['forms_sections_id', 'vertical_rank', 'horizontal_rank'],
                            'FROM'   => Comment::getTable(),
                        ]
                    ]),
                    'WHERE'   => ['NOT' => ['horizontal_rank' => null]],
                    'GROUPBY' => ['forms_sections_id', 'vertical_rank'],
                    'HAVING'  => ['COUNT(*) = 1']
                ], 'sub_query')
            ]);
        };

        foreach ($tables as $table) {
            $this->migrationManager->getDB()->update(
                $table,
                ['horizontal_rank' => null],
                [
                    'forms_sections_id' => $getSubQuery('forms_sections_id'),
                    'vertical_rank'     => $getSubQuery('vertical_rank'),
                ]
            );
        }
    }
}
