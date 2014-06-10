<?php

namespace Icinga\Module\Conftool\Icinga2;

class Icinga2Usergroup extends Icinga2ObjectDefinition
{
    protected $type = 'UserGroup';

    protected $v1ArrayProperties = array(
        'contactgroup_members',
    );

    protected $v1AttributeMap = array(
        'alias'             => 'display_name',
        'contactgroup_members' => 'groups',
    );

    protected function convertMembers($members)
    {
        foreach ($this->splitComma($members) as $member) {
            if (substr($member, 0, 1) === '!') {
                $member = substr($member, 1);
                $this->ignoreWhere('user.name == ' . $this->migrateLegacyString($member));
            } else {
                $this->assignWhere('user.name == ' . $this->migrateLegacyString($member));
            }
        }
    }
}