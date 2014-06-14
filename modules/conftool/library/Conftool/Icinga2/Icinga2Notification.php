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

class Icinga2Notification extends Icinga2ObjectDefinition
{
    protected $type = 'Notification'; //actually an Icinga 1.x escalation

    protected $v1AttributeMap = array(
        //
    );

    protected $v1RejectedAttributeMap = array(
        'hostgroup_name',
        'escalation_condition',
        'first_warning_notification',
        'last_warning_notification',
        'first_critical_notification',
        'last_critical_notification',
        'first_unknown_notification',
        'last_unknown_notification'
    );

    protected function convertContacts($value) {
        $this->users = $this->renderArray($this->splitComma($value));
        $this->command = "\"generic-escalation-dummy\""; //we cannot determine the correct command here
    }

    protected function convertContact_groups($value) {
        $this->user_groups = $this->renderArray($this->splitComma($value));
        $this->command = "\"generic-escalation-dummy\""; //we cannot determine the correct command here
    }

    protected function convertFirst_notification($value) {
        $interval = 60;
        if (array_key_exists('notification_interval', $this->properties)) {
            $interval = $this->notification_interval;
        }
        $this->times['begin'] = ($interval * $value) . 'm';
        $this->command = "\"generic-escalation-dummy\""; //we cannot determine the correct command here
    }

    protected function convertLast_notification($value) {
        $interval = 60;
        if (array_key_exists('notification_interval', $this->properties)) {
            $interval = $this->notification_interval;
        }
        $this->times['end'] = ($interval * $value) . 'm';
        $this->command = "\"generic-escalation-dummy\""; //we cannot determine the correct command here
    }

    protected function convertNotification_interval($value) {
        $this->interval = $value . "m";
        $this->command = "\"generic-escalation-dummy\""; //we cannot determine the correct command here
    }

    protected function convertEscalation_period($value) {
        $this->period = "\"" . $value . "\"";
        $this->command = "\"generic-escalation-dummy\""; //we cannot determine the correct command here
    }

    protected function convertEscalation_options($value) {
        $notification_filter = $this->migrateNotificationOptions($value, false); //default is service for migration

        if (count($notification_filter['state']) > 0) {
            $this->states = $this->arrayToString($notification_filter['state']); //constants, no strings
        }
        if (count($notification_filter['type']) > 0) {
            $this->types = $this->arrayToString($notification_filter['type']); //constants, no strings
        }

        $this->command = "\"generic-escalation-dummy\""; //we cannot determine the correct command here
    }

    //host
    protected function convertHost_name($value) {
        $arr = $this->splitComma($value);
        $this->is_apply = true;

        foreach ($arr as $hostname) {
            if (substr($hostname, 0, 1) === '!') {
                $hostname = substr($hostname, 1);
                $this->ignoreWhere('host.name == '.$this->migrateLegacyString($hostname));
            } else {
                $this->assignWhere('host.name == '.$this->migrateLegacyString($hostname));
            }
        }

        $this->command = "\"generic-escalation-dummy\""; //we cannot determine the correct command here
    }

    //hostgroup
    protected function convertHostgroup_name($value) {
        $arr = $this->splitComma($value);
        $this->is_apply = true;

        foreach ($arr as $hostgroupname) {
            if (substr($hostgroupname, 0, 1) === '!') {
                $hostgroupname = substr($hostgroupname, 1);
                $this->ignoreWhere($this->migrateLegacyString($hostgroupname) . ' in host.groups');
            } else {
                $this->assignWhere($this->migrateLegacyString($hostgroupname) . ' in host.groups');
            }
        }

        $this->command = "\"generic-escalation-dummy\""; //we cannot determine the correct command here
    }

    //service
    protected function convertService_description($value) {
        $arr = $this->splitComma($value);
        $this->is_apply = true;
        $this->apply_target = "Service";

        foreach ($arr as $servicename) {
            if (substr($servicename, 0, 1) === '!') {
                $servicename = substr($servicename, 1);
                $this->ignoreWhere('service.name == '.$this->migrateLegacyString($servicename));
            } else {
                $this->assignWhere('service.name == '.$this->migrateLegacyString($servicename));
            }
        }

        $this->command = "\"generic-escalation-dummy\""; //we cannot determine the correct command here
    }

    //servicegroup
    protected function convertServicegroup_name($value) {
        $arr = $this->splitComma($value);
        $this->is_apply = true;
        $this->apply_target = "Service";

        foreach ($arr as $servicegroupname) {
            if (substr($servicegroupname, 0, 1) === '!') {
                $servicegroupname = substr($servicegroupname, 1);
                $this->ignoreWhere($this->migrateLegacyString($servicegroupname) . ' in service.groups');
            } else {
                $this->assignWhere($this->migrateLegacyString($servicegroupname) . ' in service.groups');
            }
        }

        $this->command = "\"generic-escalation-dummy\""; //we cannot determine the correct command here
    }
}