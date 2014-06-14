<?php

namespace Icinga\Module\Conftool\Clicommands;

use Icinga\Cli\Command;
use Icinga\Module\Conftool\Icinga\IcingaConfig;
use Icinga\Module\Conftool\Icinga2\Icinga2ObjectDefinition;

class MigrateCommand extends Command
{
    public function v1Action()
    {
        $start = time();

        printf("//---------------------------------------------------\n");
        printf("//Migrate Icinga 1.x configuration to Icinga 2 format\n");
        printf("//Start time: ".date("Y-m-d H:i:s")."\n");
        printf("//---------------------------------------------------\n");

        //parse 1.x objects
        $configfile = $this->params->shift();
        $config = IcingaConfig::parse($configfile);

        //dump default templates for new objects
        Icinga2ObjectDefinition::dumpDefaultTemplates();

        //migrate all objects to 2.x
        printf("//MIGRATE COMMANDS -- BEGIN\n");
        foreach ($config->getDefinitions('command') as $object) {
            Icinga2ObjectDefinition::fromIcingaObjectDefinition($object, $config)->dump();
        }
        printf("//MIGRATE COMMANDS -- END\n");

        printf("//MIGRATE HOSTS -- BEGIN\n");
        foreach ($config->getDefinitions('host') as $object) {
            Icinga2ObjectDefinition::fromIcingaObjectDefinition($object, $config)->dump();

            //direct host->service relation
            if (count($object->getServices()) > 0) {
                printf("//---- MIGRATE HOST SERVICES -- BEGIN\n");
                foreach($object->getServices() as $service) {
                    Icinga2ObjectDefinition::fromIcingaObjectDefinition($service, $config)->dump();
                }
                printf("//---- MIGRATE HOST SERVICES -- END\n");
            }
        }
        printf("//MIGRATE HOSTS -- END\n");

        printf("//MIGRATE SERVICE -- BEGIN\n");
        //TODO only templates should dumped?
        foreach ($config->getDefinitions('service') as $object) {
            Icinga2ObjectDefinition::fromIcingaObjectDefinition($object, $config)->dump();
        }
        printf("//MIGRATE SERVICE -- END\n");

        printf("//MIGRATE CONTACTS (USERS) -- BEGIN\n");
        foreach ($config->getDefinitions('contact') as $object) {
            Icinga2ObjectDefinition::fromIcingaObjectDefinition($object, $config)->dump();
        }
        printf("//MIGRATE CONTACTS (USERS) -- END\n");

        printf("//MIGRATE HOSTGROUPS -- BEGIN\n");
        foreach ($config->getDefinitions('hostgroup') as $object) {
            Icinga2ObjectDefinition::fromIcingaObjectDefinition($object, $config)->dump();

            //indirect hostgroup->service relation
            if (count($object->getServices()) > 0) {
                printf("//---- MIGRATE HOSTGROUP SERVICES -- BEGIN\n");
                foreach($object->getServices() as $service) {
                    Icinga2ObjectDefinition::fromIcingaObjectDefinition($service, $config)->dump();
                }
                printf("//---- MIGRATE HOSTGROUP SERVICES -- END\n");
            }
        }
        printf("//MIGRATE HOSTGROUPS -- END\n");

        printf("//MIGRATE SERVICEGROUPS -- BEGIN\n");
        foreach ($config->getDefinitions('servicegroup') as $object) {
            Icinga2ObjectDefinition::fromIcingaObjectDefinition($object, $config)->dump();
        }
        printf("//MIGRATE SERVICEGROUPS -- END\n");

        printf("//MIGRATE CONTACTGROUPS (USERGROUPS) -- BEGIN\n");
        foreach ($config->getDefinitions('contactgroup') as $object) { // TODO: Find a better way than hardcoded
            Icinga2ObjectDefinition::fromIcingaObjectDefinition($object, $config)->dump();
        }
        printf("//MIGRATE CONTACTGROUPS (USERGROUPS) -- END\n");

        printf("//MIGRATE TIMEPERIODS -- BEGIN\n");
        foreach ($config->getDefinitions('timeperiod') as $object) {
            Icinga2ObjectDefinition::fromIcingaObjectDefinition($object, $config)->dump();
        }
        printf("//MIGRATE TIMEPERIODS -- END\n");

        $end = time();
        $runtime = $end - $start;

        printf("//---------------------------------------------------\n");
        printf("//FINISHED :-)\n");
        printf("//End time: " . date("Y-m-d H:i:s") . "\n");
        printf("//Runtime: " . $runtime . "\n");
        printf("//---------------------------------------------------\n");

    }
}
