<?php
// {{{ICINGA_LICENSE_HEADER}}}
/**
 * This file is part of Icinga Web 2.
 *
 * Icinga Web 2 - Head for multiple monitoring backends.
 * Copyright (C) 2014 Icinga Development Team
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @copyright  2013 Icinga Development Team <info@icinga.org>
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GPL, version 2
 * @author     Icinga Development Team <info@icinga.org>
 *
 */
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Module\Conftool\Icinga2;

class Icinga2User extends Icinga2ObjectDefinition
{
    protected $type = 'User';

    protected $v1ArrayProperties = array(
        'contactgroups',
        'service_notification_commands',
        'host_notification_commands'
    );
    protected $v1AttributeMap = array(
        'alias' => 'display_name',
        'contactgroups' => 'groups',
        'email' => 'email',
        'pager' => 'pager',
    );

    protected $v1RejectedAttributeMap = array(
        'service_notification_period',
        'host_notification_period',
        'host_notification_options',
        'service_notification_commands',
        'host_notification_commands',
        'host_notifications_enabled',
        'service_notifications_enabled',
        'address1',
        'address2',
        'address3',
        'address4',
        'address5',
        'address6',
    );

    protected function convertEmail($value)
    {
        $this->email = "\"".$value."\"";
    }

    protected function convertPager($value)
    {
        $this->pager = "\"".$value."\"";
    }

    protected function convertService_notification_options($value)
    {
        $notification_filter = $this->migrateNotificationOptions($value, false); //default is service for migration

        if (count($notification_filter['state']) > 0) {
            $this->states = $this->arrayToString($notification_filter['state']); //constants, no strings
        }
        if (count($notification_filter['type']) > 0) {
            $this->types = $this->arrayToString($notification_filter['type']); //constants, no strings
        }
    }
}
