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
