<?php

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
