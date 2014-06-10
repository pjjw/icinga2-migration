<?php

namespace Icinga\Module\Conftool\Icinga2;

class Icinga2Servicegroup extends Icinga2ObjectDefinition
{
    protected $type = 'ServiceGroup';

    protected $v1ArrayProperties = array(
        'servicegroup_members',
    );

    protected $v1AttributeMap = array(
        'alias'             => 'display_name',
        'notes'             => 'notes',
        'action_url'        => 'action_url',
        'notes_url'         => 'notes_url',
        'servicegroup_members' => 'groups',
    );

    protected function convertMembers($members)
    {
        $count = 0;
        $sg_members = $this->splitComma($members);
        $length = count($sg_members);

        if ($length % 2 != 0) {
            throw new Icinga2ConfigMigrationException(
                sprintf('Odd number of servicegroup members for group ' . $this->name)
            );
        }

        while ($count < $length) {
            $host = $sg_members[$count++];
            $service = $sg_members[$count++];
            if (substr($host, 0, 1) === '!') {
                $host = substr($host, 1);
                $this->ignoreWhere('host.name == ' . $this->migrateLegacyString($host) . ' && service.name == ' . $this->migrateLegacyString($service));
            } else {
                $this->assignWhere('host.name == ' . $this->migrateLegacyString($host) . ' && service.name == ' . $this->migrateLegacyString($service));
            }
        }
    }
}
