<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2023 Teclib' and contributors.
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

/**
 * @since 9.1
 */

use Glpi\Http\Response;

$AJAX_INCLUDE = 1;

include('../inc/includes.php');
header("Content-Type: application/json; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

// Mandatory parameter: validationtemplates_id
$validationtemplates_id = $_POST['validationtemplates_id'] ?? null;
if ($validationtemplates_id === null) {
    Response::sendError(400, "Missing or invalid parameter: 'validationtemplates_id'");
} else if ($validationtemplates_id == 0) {
    // Reset form
    echo json_encode([
        'content' => ""
    ]);
    die;
}

// Mandatory parameter: items_id
$parents_id = $_POST['items_id'] ?? 0;
if (!$parents_id) {
    Response::sendError(400, "Missing or invalid parameter: 'items_id'");
}

// Mandatory parameter: itemtype
$parents_itemtype = $_POST['itemtype'] ?? '';
if (empty($parents_itemtype) || !is_subclass_of($parents_itemtype, CommonITILObject::class)) {
    Response::sendError(400, "Missing or invalid parameter: 'itemtype'");
}

// Load validation template
$template = new ITILValidationTemplate();
if (!$template->getFromDB($validationtemplates_id)) {
    Response::sendError(400, "Unable to load template: $validationtemplates_id");
}

// Load parent item
$parent = new $parents_itemtype();
if (!$parent->getFromDB($parents_id)) {
    Response::sendError(400, "Unable to load parent item: $parents_itemtype $parents_id");
}

$targets = ITILValidationTemplate_Target::getTargets($template->getID());
$itemtype = array_key_first($targets);
$items_id_target = array_map(function ($item) {
    return $item['items_id'];
}, $targets[$itemtype]);

if (count($targets[$itemtype]) > 0 && isset($targets[$itemtype][0]['groups_id'])) {
    $itemtype = 'Group_User';
}

// Return json response with the template fields
echo json_encode(array_merge($template->fields, [
    'validatortype'   => $itemtype,
    'groups_id'       => $itemtype == 'Group_User' ? $targets['User'][0]['groups_id'] : null,
    'items_id_target' => ($itemtype == 'Group' || $itemtype == 'User') && count($items_id_target) == 1 ?
        $items_id_target[0] : $items_id_target
]));
