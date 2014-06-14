<?php

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
