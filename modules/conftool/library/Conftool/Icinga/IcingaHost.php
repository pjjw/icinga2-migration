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

namespace Icinga\Module\Conftool\Icinga;

class IcingaHost extends IcingaObjectDefinition
{
    protected $key = 'host_name';
    protected $_own_services = array();
    protected $_not_services = array();

    public function blacklistService(IcingaService $service)
    {
        $this->_not_services[$service->service_description] = $service;
        return $this;
    }

    public function hasBlacklistedService(IcingaService $service)
    {
        return isset($this->_not_services[$service->service_description]);
    }

    public function addService(IcingaService $service)
    {
        if (! is_string($service->service_description)) {
            echo "Skipping invalid service definition:\n";
            print_r($service);
            return $this;
        }
        if (isset($this->_own_services[$service->service_description])) {
            throw new IcingaDefinitionException(sprintf(
                'Cannot add service definition twice: %s/%s',
                $this->host_name,
                $service->service_description
            ));
        }
        $this->_own_services[$service->service_description] = $service;

        return $this;
    }

    // Compares only the name, this is dangerous and should better fail
    public function hasService(IcingaService $service)
    {
        return isset($this->_own_services[$service->service_description]);
    }

    public function getServices()
    {
        return $this->_own_services;
    }
}
