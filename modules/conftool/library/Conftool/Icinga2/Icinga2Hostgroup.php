<?php

namespace Icinga\Module\Conftool\Icinga2;

class Icinga2Hostgroup extends Icinga2ObjectDefinition
{
    protected $type = 'HostGroup';

    protected $v1ArrayProperties = array(
        'hostgroup_members',
    );

    protected $v1AttributeMap = array(
        'alias'             => 'display_name',
        'notes'             => 'notes',
        'action_url'        => 'action_url',
        'notes_url'         => 'notes_url',
        'hostgroup_members' => 'groups',
    );

    protected function convertMembers($members)
    {
        foreach ($this->splitComma($members) as $member) {
            if (substr($member, 0, 1) === '!') {
                $member = substr($member, 1);
                $this->ignoreWhere('host.name == ' . $this->migrateLegacyString($member));
            } else {
                $this->assignWhere('host.name == ' . $this->migrateLegacyString($member));
            }
        }
    }
}