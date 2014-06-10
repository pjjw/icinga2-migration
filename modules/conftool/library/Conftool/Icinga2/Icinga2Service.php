<?php

namespace Icinga\Module\Conftool\Icinga2;

use Icinga\Module\Conftool\Icinga\IcingaConfig;

class Icinga2Service extends Icinga2ObjectDefinition
{
    protected $type = 'Service';

    protected $v1ArrayProperties = array(
        'servicegroups',
    );

    protected $v1AttributeMap = array(
        //keep
        'display_name' => 'display_name',
        'notes' => 'notes',
        'notes_url' => 'notes_url',
        'action_url' => 'action_url',
        'icon_image' => 'icon_image',
        'icon_image_alt' => 'icon_image_alt',
        'check_interval' => 'check_interval',
        'retry_interval' => 'retry_interval',
        'check_period' => 'check_period',
        'max_check_attempts' => 'max_check_attempts',
        'check_command' => 'check_command',
        //rename
        'alias' => 'display_name',
        'servicegroups' => 'groups',
        'active_checks_enabled' => 'enable_active_checks',
        'passive_checks_enabled' => 'enable_passive_checks',
        'event_handler_enabled' => 'enable_event_handler',
        'event_handler' => 'event_command',
        'low_flap_threshold' => 'flapping_threshold',
        'high_flap_threshold' => 'flapping_threshold',
        'flap_detection_enabled' => 'enaple_flapping',
        'process_perf_data' => 'enable_perfdata',
        'notifications_enabled' => 'enable_notifications',
        'is_volatile' => 'volatile',
        //ugly hacks
        'name' => 'service_description',
        //legacy attributes (nagios2)
        'normal_check_interval' => 'check_interval',
        'retry_check_interval' => 'retry_interval',
    );

    protected $v1RejectedAttributeMap = array(
        //ignore
        'initial_state',
        'obsess_over_service',
        'check_freshness',
        'freshness_threshold',
        'flap_detection_options',
        'failure_prediction_enabled',
        'retain_status_information',
        'retain_nonstatus_information',
        'stalking_options',
        'parallelize_check',
        //ignore,
        'notification_interval',
        'first_notification_delay',
        'notification_period',
        'notification_options'
    );

    // TODO: Figure out how to handle
    // - notification_interval, first_notification_delay, notification_period, notification_options
    // in a new notification object

    // TODO
    protected function convertContacts($contacts)
    {
    }

    protected function convertContact_groups($contactgroups)
    {
    }

    protected function convertCheck_command($command) {
        //TODO migrate command arguments
        //bla!$SERVICEDESC$... - fix vars as runtime macros
    }

    protected function convertHost_name($name)
    {
        $arr = $this->splitComma($name);
        $this->is_apply = true;

        foreach ($arr as $hostname) {
            if (substr($hostname, 0, 1) === '!') {
                $hostname = substr($hostname, 1);
                $this->ignoreWhere('host.name == ' . $this->migrateLegacyString($hostname));
            } else {
                $this->assignWhere('host.name == ' . $this->migrateLegacyString($hostname));
            }
        }
    }

    protected function convertHostgroup_name($name)
    {
        $arr = $this->splitComma($name);
        $this->is_apply = true;

        foreach ($arr as $hostgroupname) {
            if (substr($hostgroupname, 0, 1) === '!') {
                $hostgroupname = substr($hostgroupname, 1);
                $this->ignoreWhere($this->migrateLegacyString($hostgroupname) . ' in host.groups');
            } else {
                $this->assignWhere($this->migrateLegacyString($hostgroupname) . ' in host.groups');
            }
        }
    }
}
