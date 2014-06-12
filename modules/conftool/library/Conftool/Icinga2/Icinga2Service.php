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
        'icon_image_alt' => 'icon_image_alt',
        'max_check_attempts' => 'max_check_attempts',
        //rename
        'alias' => 'display_name',
        'servicegroups' => 'groups',
        'active_checks_enabled' => 'enable_active_checks',
        'passive_checks_enabled' => 'enable_passive_checks',
        'event_handler_enabled' => 'enable_event_handler',
        'low_flap_threshold' => 'flapping_threshold',
        'high_flap_threshold' => 'flapping_threshold',
        'flap_detection_enabled' => 'enable_flapping',
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
        $hosts = array();
        $this->is_apply = false;

        foreach ($arr as $hostname) {
            if (substr($hostname, 0, 1) === '!') {
                $hostname = substr($hostname, 1);
                $this->ignoreWhere('host.name == ' . $this->migrateLegacyString($hostname));
                $this->is_apply = true;
            } else if (substr($hostname, 0, 1) === '*') {
                $this->assignWhere('match("*", host.name)');
                $this->is_apply = true;
            } else {
                $hosts[] = $hostname;
            }
        }

        if (substr($name, 0, 1) === '*') {
            return; //no more actions
        }

        //assign rule applies?
        if (count($hosts) > 1) {
            $this->assignWhere('host.name in ' . $this->renderArray($hosts));
            $this->is_apply = true;
        } else {
            $this->host_name = "\"".$name."\"";
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
