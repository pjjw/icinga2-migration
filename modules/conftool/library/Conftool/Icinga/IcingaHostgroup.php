<?php

namespace Icinga\Module\Conftool\Icinga;

class IcingaHostgroup extends IcingaGroupDefinition
{
    protected $key = 'hostgroup_name';
    protected $_own_services = array();

    public function addService(IcingaService $service)
    {
        if (isset($this->_own_services[$service->service_description])) {
            throw new IcingaDefinitionException(sprintf(
                'Cannot add service definition twice: %s/%s',
                $this->hostgroup_name,
                $service->service_description
            ));
        }
        $this->_own_services[$service->service_description] = $service;

        return $this;
    }

    public function hasServices()
    {
        return count($this->_own_services) > 0;
    }

    public function getServices()
    {
        return $this->_own_services;
    }

}
