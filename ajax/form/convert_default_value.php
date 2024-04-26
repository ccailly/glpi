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

use Glpi\Form\Form;
use Glpi\Form\QuestionType\QuestionTypesManager;
use Glpi\Http\Response;

include('../../inc/includes.php');

/**
 * AJAX endpoint used to transform the question data when the question type is changed.
 */

// TODO: check that the current user is allowed to update the forms

// Validate forms_forms_id parameter
$forms_id = $_POST['forms_id'] ?? 0;
if (!$forms_id) {
    Response::sendError(400, __('Missing form id'));
}

// Load form
$form = Form::getById($forms_id);
if (!$form) {
    Response::sendError(404, __('Form not found'));
}

// Validate old_type parameter
$old_type = $_POST['old_type'] ?? '';
if (!$old_type) {
    Response::sendError(400, __('Missing old type'));
}

// Check if the old type is a valid question type
if (
    !in_array(
        $old_type,
        array_map(fn($type) => $type::class, QuestionTypesManager::getInstance()->getQuestionTypes())
    )
) {
    Response::sendError(400, __('Invalid old type'));
}

// Validate new_type parameter
$new_type = $_POST['new_type'] ?? '';
if (!$new_type) {
    Response::sendError(400, __('Missing new type'));
}

// Check if the new type is a valid question type
if (
    !in_array(
        $new_type,
        array_map(fn($type) => $type::class, QuestionTypesManager::getInstance()->getQuestionTypes())
    )
) {
    Response::sendError(400, __('Invalid new type'));
}

// Validate value parameter
$value = $_POST['value'] ?? '';
if (!is_string($value)) {
    Response::sendError(400, __('Invalid value'));
}

$new_value = (new $new_type())->onQuestionTypeChange($old_type, $new_type, $value);

// Success response
$response = new Response(
    200,
    ['Content-Type' => 'application/json'],
    json_encode([
        'new_value' => $new_value,
    ]),
);
$response->send();
