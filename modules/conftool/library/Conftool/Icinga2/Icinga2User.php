<?php

namespace Icinga\Module\Conftool\Icinga2;

class Icinga2User extends Icinga2ObjectDefinition
{
    protected $type = 'CheckCommand';

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
        'service_notification_options',
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
}
