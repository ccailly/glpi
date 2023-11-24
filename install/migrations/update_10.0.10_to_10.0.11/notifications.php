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
 * @var DB $DB
 * @var Migration $migration
 */

/** Add new notification for AutoBump */
if (countElementsInTable('glpi_notifications', ['itemtype' => 'Ticket', 'event' => 'auto_reminder']) === 0) {
    $DB->insertOrDie('glpi_notificationtemplates', [
        'name' => 'Automatic reminder',
        'itemtype' => 'Ticket'
    ]);

    $notificationtemplate_id = $DB->insertId();

    $DB->insertOrDie('glpi_notificationtemplatetranslations', [
        'notificationtemplates_id' => $notificationtemplate_id,
        'language' => '',
        'subject' => '##ticket.action## ##ticket.name##',
        'content_text' => '##lang.ticket.title##: ##ticket.title##

##lang.ticket.reminder.bumpcounter##: ##ticket.reminder.bumpcounter##
##lang.ticket.reminder.bumpremaining##: ##ticket.reminder.bumpremaining##
##lang.ticket.reminder.bumptotal##: ##ticket.reminder.bumptotal##
##lang.ticket.reminder.deadline##: ##ticket.reminder.deadline##

##lang.ticket.reminder.text##: ##ticket.reminder.text##',
        'content_html' => '&lt;p&gt;##lang.ticket.title##: ##ticket.title##&lt;/p&gt;
            &lt;p&gt;##lang.ticket.reminder.bumpcounter##: ##ticket.reminder.bumpcounter##&lt;/a&gt;&lt;br /&gt;
            ##lang.ticket.reminder.bumpremaining##: ##ticket.reminder.bumpremaining##&lt;/a&gt;&lt;br /&gt;
            ##lang.ticket.reminder.bumptotal##: ##ticket.reminder.bumptotal##&lt;/a&gt;&lt;br /&gt;
            ##lang.ticket.reminder.deadline##: ##ticket.reminder.deadline##&lt;/p&gt;
            &lt;p&gt;##lang.ticket.reminder.text##: ##ticket.reminder.text##&lt;/p&gt;',
    ]);

    $DB->insertOrDie(
        'glpi_notifications',
        [
            'name'            => 'Automatic reminder',
            'itemtype'        => 'Ticket',
            'event'           => 'auto_reminder',
            'comment'         => null,
            'is_recursive'    => 0,
            'is_active'       => 0,
        ],
        'Add automatic reminder notification'
    );
    $notification_id = $DB->insertId();

    $targets = [
        [
            'items_id' => 3,
            'type' => 1,
        ],
        [
            'items_id' => 1,
            'type' => 1,
        ],
        [
            'items_id' => 21,
            'type' => 1,
        ],
    ];

    foreach ($targets as $target) {
        $DB->insertOrDie('glpi_notificationtargets', [
            'items_id'         => $target['items_id'],
            'type'             => $target['type'],
            'notifications_id' => $notification_id,
        ]);
    }

    $DB->insertOrDie('glpi_notifications_notificationtemplates', [
        'notifications_id'         => $notification_id,
        'mode'                     => Notification_NotificationTemplate::MODE_MAIL,
        'notificationtemplates_id' => $notificationtemplate_id,
    ]);
}
/** /Add new notification for AutoBump */
