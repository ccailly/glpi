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

/**
 * Following variables have to be defined before inclusion of this file:
 * @var RuleCollection $rulecollection
 */

if (!isset($_GET["id"])) {
    $_GET["id"] = "";
}

$rulecollection->checkGlobal(READ);

if (isset($_POST["action"])) {
    $rulecollection->checkGlobal(UPDATE);
    $rulecollection->changeRuleOrder($_POST["id"], $_POST["action"], $_POST['condition']);
    Html::back();
} else if (isset($_POST["reinit"]) || isset($_GET['reinit'])) {
   //reinitialize current rules
    $ruleclass = $rulecollection->getRuleClass();
    if ($ruleclass->initRules()) {
        Session::addMessageAfterRedirect(
            htmlspecialchars(sprintf(
            //TRANS: first parameter is the rule type name
                __('%1$s has been reset.'),
                $rulecollection->getTitle()
            ))
        );
    } else {
        Session::addMessageAfterRedirect(
            htmlspecialchars(sprintf(
                //TRANS: first parameter is the rule type name
                __('%1$s reset failed.'),
                $rulecollection->getTitle()
            )),
            false,
            ERROR
        );
    }
    Html::back();
} else if (isset($_POST["replay_rule"]) || isset($_GET["replay_rule"])) {
   // POST and GET needed to manage reload
    $rulecollection->checkGlobal(UPDATE);

   // Current time
    $start = microtime(true);

   // Limit computed from current time
    $max = (int) get_cfg_var("max_execution_time");
    $max = $start + ($max > 0 ? $max / 2.0 : 30.0);

    Html::header(
        Rule::getTypeName(Session::getPluralNumber()),
        $_SERVER['PHP_SELF'],
        "admin",
        $rulecollection->menu_type,
        $rulecollection->menu_option
    );

    if (
        !(isset($_POST['replay_confirm']) || isset($_GET['offset']))
        && $rulecollection->warningBeforeReplayRulesOnExistingDB($_SERVER['PHP_SELF'])
    ) {
        Html::footer();
        exit();
    }

    echo "<table class='tab_cadrehov'>";

    echo "<tr><th><div class='relative b'>" . htmlspecialchars($rulecollection->getTitle()) . "<br>" .
         __s('Replay the rules dictionary') . "</div></th></tr>";
    echo "<tr><td class='center'>";
    Html::progressBar('doaction_progress', [
        'create' => true,
        'message' => __s('Work in progress...')
    ]);
    echo "</td></tr>";
    echo "</table>";

    if (!isset($_GET['offset'])) {
       // First run
        $offset       = $rulecollection->replayRulesOnExistingDB(0, $max, [], $_POST);
        $manufacturer = (isset($_POST["manufacturer"]) ? $_POST["manufacturer"] : 0);
    } else {
       // Next run
        $offset       = $rulecollection->replayRulesOnExistingDB(
            $_GET['offset'],
            $max,
            [],
            $_GET
        );
        $manufacturer = $_GET["manufacturer"];

       // global start for stat
        $start = $_GET["start"];
    }

    if ($offset < 0) {
       // Work ended
        $duree = round(microtime(true) - $start);
        Html::changeProgressBarMessage(sprintf(
            __('Task completed in %s'),
            Html::timestampToString($duree)
        ));
        echo "<a href='" . $_SERVER['PHP_SELF'] . "'>" . __s('Back') . "</a>";
    } else {
       // Need more work
        Html::redirect($_SERVER['PHP_SELF'] . "?start=$start&replay_rule=1&offset=$offset&manufacturer=" .
                     "$manufacturer");
    }

    Html::footer();
    exit();
}

Html::header(
    Rule::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    'admin',
    $rulecollection->menu_type,
    $rulecollection->menu_option
);

$rulecollection->display([
    'display_criterias' => true,
    'display_actions'   => true,
]);
Html::footer();
