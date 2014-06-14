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

class Icinga2Servicegroup extends Icinga2ObjectDefinition
{
    protected $type = 'ServiceGroup';

    protected $v1ArrayProperties = array(
        'servicegroup_members',
    );

    protected $v1AttributeMap = array(
        'alias'             => 'display_name',
        'servicegroup_members' => 'groups',
    );

    protected $v1RejectedAttributeMap = array(
        'servicegroup_name',
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
