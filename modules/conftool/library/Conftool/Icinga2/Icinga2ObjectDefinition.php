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

use Icinga\Module\Conftool\Icinga\IcingaConfig;
use Icinga\Module\Conftool\Icinga\IcingaService;
use Icinga\Module\Conftool\Icinga\IcingaHost;
use Icinga\Module\Conftool\Icinga\IcingaObjectDefinition;

class Icinga2ObjectDefinition
{
    protected $properties = array();
    protected $name;
    protected $v1AttributeMap = array();
    protected $v1ArrayProperties = array();
    protected $v1RejectedAttributeMap = array();
    protected $type;
    protected $_parents = array();
    protected $is_template = false;
    protected $is_apply = false;
    protected $apply_target = "Host";
    protected $assigns = array();
    protected $ignores = array();
    protected $imports = array();
    protected $vars = array();
    protected $times = array(); //required for escalation notifications

    //new objects from conversion
    protected $eventcommands = array();
    protected $notificationcommands = array();
    protected $notificationobjects = array();
    protected $notifications = array();
    protected $dependencies = array();

    public function __construct(IcingaObjectDefinition $name)
    {
        $this->name = (string) $name;
    }

    public function __set($key, $val)
    {
        $this->properties[$key] = $val;
    }

    public function __get($key)
    {
        return $this->properties[$key];
    }

    protected function assignWhere($assign)
    {
        $this->assigns[] = $assign;
    }

    protected function ignoreWhere($ignore)
    {
        $this->ignores[] = $ignore;
    }

    protected function imports($import)
    {
        $this->imports[] = $import;
    }

    protected function vars($varname, $varvalue)
    {
        $this->vars[$varname] = $varvalue;
    }

    protected function eventcommands($name, $line)
    {
        $this->eventcommands[$name] = $line;
    }

    protected function notificationcommands($name, $line)
    {
        $this->notificationcommands[$name] = $line;
    }

    protected function notifications($name, $attr)
    {
        $this->notifications[$name] = $attr;
    }

    protected function dependencies($name, $attr)
    {
        $this->dependencies[$name] = $attr;
    }

    protected function setAttributesFromIcingaObjectDefinition(IcingaObjectDefinition $object, IcingaConfig $config)
    {
        //template, parents and config
        $this->is_template = $object->isTemplate();
        $this->_parents = $object->getParents();

        $all_contacts = array();
        $all_contactgroups = array();
        $all_parents = array();

        //---- LOOP ATTRIBUTES BEGIN
        foreach ($object->getAttributes() as $key => $value) {

            //rejects
            if ($key !== null && in_array($key, $this->v1RejectedAttributeMap)) {
                continue;
            }

            //ugly 1.x hacks
            //these values must be resolved earlier already
            if($this->is_template && ($key == "service_description" || $key == "host_name")) {
                continue; //skip invalid template attributes
            }
            if (!$this->is_template && $key == "name") {
                continue; //skip invalid object attributes
            }

            // template imports
            if ($key == "use") {
                $this->imports = $this->migrateUseImport($value, $key);
                //DONE
                continue;
            }

            //check command arguments
            if ($key == "check_command") {
                $command_arr = explode("!", $value);

                $this->properties['check_command'] = "\"".$command_arr[0]."\""; //first is always the command name

                for($i = 1; $i < count($command_arr); $i++) {
                    $varname = "ARG".$i;
                    $varvalue = addslashes($command_arr[$i]); //escape the string
                    //check against legacy macros and replace them
                    $varvalue = $this->migrateLegacyMacros($varvalue);
                    $this->vars($varname, $varvalue);
                }
                //DONE
                continue;
            }

            //event handler translation
            if ($key == "event_handler") {
                $eventcommand_prefix = (string) $object;

                if ($object instanceof IcingaHost) {
                    $eventcommand_prefix = "host-" . $eventcommand_prefix;
                } else if ($object instanceof IcingaService) {
                    $eventcommand_prefix = "service-" . $eventcommand_prefix;
                }

                $eventcommand_obj = $config->getObject($value, 'command');
                $eventcommand_name = $eventcommand_prefix . "-" . $value;
                $eventcommand_line = addslashes($eventcommand_obj->command_line);
                $eventcommand_line = $this->migrateLegacyMacros($eventcommand_line);
                $this->eventcommands($eventcommand_name, $eventcommand_line);
                $this->event_command = "\"" . $eventcommand_name . "\"";
                //DONE
                continue;
            }

            //parents to dependencies
            if ($key == "parents") {
                //skip templates
                if ($this->isTemplate()) {
                    continue;
                }

                $parents = $this->splitComma($value);

                foreach($parents as $parent) {
                    $all_parents[] = trim($parent);
                }

                continue;
            }

            //convert host/service notifications
            if ($key == "contacts" && ($object instanceof IcingaService || $object instanceof IcingaHost)) {

                //skip templates
                if ($this->isTemplate()) {
                    continue;
                }

                $contacts = $this->splitComma($value);

                foreach($contacts as $contact) {
                    $all_contacts[] = $contact;
                }

                //TODO recursive lookup in templates
                printf("//Found contacts attribute: %s on object %s\n", $value, $object);

                /*
                $lookup = $this->collectObjectAttributeRecursive($object, 'contacts');

                if (count($lookup) > 0) {
                    foreach($lookup as $l_contact) {
                        preg_replace('/^\+\w+/', '', $l_contact); //drop additive
                        $all_contacts[] = $l_contact;
                    }
                }
                */

                continue;
            }

            if ($key == "contact_groups" && ($object instanceof IcingaService || $object instanceof IcingaHost)) {

                //skip templates
                if ($this->isTemplate()) {
                    continue;
                }

                $contactgroups = $this->splitComma($value);

                foreach ($contactgroups as $contactgroup) {
                    $all_contactgroups[] = $contactgroup;
                }

                printf("//Found contact_groups attribute: %s on object %s\n", $value, $object);

                //TODO recursive lookup in templates
                /*
                $lookup = $this->collectObjectAttributeRecursive($object, 'contactgroups');

                if (count($lookup) > 0) {
                    foreach($lookup as $l_contactgroup) {
                        preg_replace('/^\+\w+/', '', $l_contactgroup); //drop additive
                        $all_contactgroups[] = $l_contactgroup;
                    }
                }
                */

                continue;
            }

            //conversion of different attributes
            $func = 'convert' . ucfirst($key);
            if (method_exists($this, $func)) {
                $this->$func($value);
                continue;
            }

            if ($object->getDefinitionType() == "timeperiod") {
                if (preg_match('/^\d+/', $key)) {
                    print_r("//ERROR: Timeperiod property invalid. Skipping it.\n");
                    continue;
                }
                $key = "ranges.".$key;
                $this->$key = "\"".$value."\"";
                continue; //allow remaining items
            }

            //mapping
            if (! array_key_exists($key, $this->v1AttributeMap)) {
                throw new Icinga2ConfigMigrationException(
                    sprintf('Cannot convert the "%s" property of given v1 object: ', $key) . print_r($object, 1)
                );
            }

            //migrate
            $value = $this->migrateValue($value, $key);

            if ($value !== null) {
                $this->{ $this->v1AttributeMap[$key] } = $value;
            }
        }
        //---- LOOP ATTRIBUTES END

        //---- NOTIFICATIONS BEGIN
        if ($object instanceof IcingaService || $object instanceof IcingaHost) {

            //var_dump($all_contactgroups);
            //var_dump($all_contacts);

            //contacts -> notifications
            $all_contactgroups = array_unique($all_contactgroups);

            if (count($all_contactgroups) > 0) {
                printf("//Found contact_groups: %s\n", $this->arrayToString($all_contactgroups));
            }

            //fetch all contactgroup members as string
            foreach ($all_contactgroups as $contactgroup) {
                trim($contactgroup);
                $contact_objs = $config->getObjectsByAttributeValue('contactgroups', $contactgroup, 'contact');
                //gosh i love these attributes. contact->contactgroups, but host->contact_groups - WTF?!?

                //no contacts with 'contactgroups' attribute, try the group members instead.
                if (count($contact_objs) == 0) {
                    $contactgroup_obj = $config->getObject($contactgroup, 'contactgroup');
                    $contact_objs = $this->splitComma($contactgroup_obj->members);
                }

                foreach ($contact_objs as $contact_obj) {
                    $all_contacts[] = (string) $contact_obj;
                }
            }

            //make sure that only unique contacts exist, we do not need too much duplicated notifications
            $all_contacts = array_unique($all_contacts);

            if (count($all_contacts) > 0) {
                printf("//Found contacts: %s\n", $this->arrayToString($all_contacts));
            }

            foreach ($all_contacts as $contact) {

                //strip additive character
                $name = preg_replace('/^\+/', '', $contact);
                trim($name);

                $contact_obj = $config->GetObject($name, 'contact');

                if ($contact_obj == false) {
                    print("//ERROR: Unknown contact '" . $name . "' referenced by object '" . $object . "'.");
                    continue;
                }

                //HOST NOTIFICATIONS
                if ($object instanceof IcingaHost) {
                    $host_notification_commands = $this->splitComma($contact_obj->host_notification_commands);

                    foreach ($host_notification_commands as $host_notification_command) {
                        trim($host_notification_command);

                        //get the command line
                        $host_notification_command_line = $config->getObject($host_notification_command, 'command');
                        //generate a unique notification command name
                        $host_notification_command_name = "host-" . (string) $object . "-notification-command-" . $host_notification_command;

                        //create a new notification command
                        $this->notificationcommands($host_notification_command_name, $host_notification_command_line);

                        $notification_filter = $this->migrateNotificationOptions($object->notification_options, true);

                        //create a new host notification object
                        $notification_object_name = "notification-".$host_notification_command_name;
                        $notification_object_attr = array (
                            'users' => array( $contact ),
                            'import' => 'generic-host-notification',
                            'period' => $object->notification_period,
                            'interval' => $object->notification_interval,
                            'states' => $notification_filter['state'],
                            'types' => $notification_filter['type'],
                            'command' => $host_notification_command_name,
                            'host_name' => (string) $object
                        );
                        //TODO migrate notification_options

                        $this->notifications($notification_object_name, $notification_object_attr);

                    }
                }

                //SERVICE NOTIFICATION
                if ($object instanceof IcingaService) {
                    $service_notification_commands = $this->splitComma($contact_obj->service_notification_commands);

                    foreach ($service_notification_commands as $service_notification_command) {
                        trim($service_notification_command);

                        if ($object->host_name) {
                            $prefix = "host-" . $object->host_name;
                        } else { //some random madness
                            $prefix = substr(str_shuffle(md5(time())),0,10); //length 10 is sufficient
                        }

                        //get the command line
                        $service_notification_command_line = $config->getObject($service_notification_command, 'command');
                        //generate a unique notification command name //TODO that's not really clear for services only, being non unique by their name - get hostname/group?
                        $service_notification_command_name = $prefix . "-service-" . (string) $object . "-notification-command-" . $service_notification_command;

                        //create a new notification command
                        $this->notificationcommands($service_notification_command_name, $service_notification_command_line);

                        //create a new host notification object
                        $notification_object_name = "notification-".$service_notification_command_name;

                        $notification_filter = $this->migrateNotificationOptions($object->notification_options, false);

                        $notification_object_attr = array (
                            'users' => array( $contact ),
                            'import' => 'generic-service-notification',
                            'period' => $object->notification_period,
                            'interval' => $object->notification_interval,
                            'states' => $notification_filter['state'],
                            'types' => $notification_filter['type'],
                            'command' => $service_notification_command_name
                        );

                        if ($object->host_name) {
                            $notification_object_attr['host_name'] = $object->host_name;
                            $notification_object_attr['service_name'] = (string) $object;
                        } else {
                            //if there is no direct relation, we need to specify the service.name as additional assign rule
                            $notification_object_attr['service_assign'] = (string) $object;
                        }

                        //TODO migrate notification_options

                        $this->notifications($notification_object_name, $notification_object_attr);

                    }
                }
            }
        }

        //make sure we have unique notification commands (and also notifications, even if not that accurate TODO)
        $this->notificationcommands = array_unique($this->notificationcommands);
        $this->notifications = array_unique($this->notifications);

        //---- NOTIFICATIONS END

        //---- HOST PARENTS BEGIN
        if ($object instanceof IcingaHost) {

            $all_parents = array_unique($all_parents);

            if (count($all_parents) > 0) {
                printf("//Found parents: %s\n", $this->arrayToString($all_parents));
            }

            foreach ($all_parents as $parent) {
                trim($parent);

                $host_obj = $config->GetObject($parent, 'host');

                if ($host_obj == false) {
                    print("//ERROR: Unknown parent host '" . $parent . "' referenced by object '" . $object . "'.");
                    continue;
                }

                $dep_name = "child-" . $object . "-parent-" . $parent . "-host-dependency";

                $dep_attrs = array (
                    'child_host_name' => (string) $object,
                    'parent_host_name' => $parent
                );

                $this->dependencies($dep_name, $dep_attrs);
            }
        }
        //---- HOST PARENTS END

        //itl required imports
        if ($object->getDefinitionType() == "timeperiod") {
            $this->imports("legacy-timeperiod");
        }

        if ($object->getDefinitionType() == "command") {
            $this->imports("plugin-check-command");
        }

        //custom vars
        foreach ($object->getCustomVars() as $key => $value) {
            $key = substr($key, 1); //drop _
            $this->vars($key, $value);
        }
    }

    //generic conversion functions
    protected function convertCheck_interval($value)
    {
        $this->check_interval = $value.'m';
    }

    protected function convertCheck_period($value)
    {
        $this->check_period = "\"".$value."\"";
    }

    protected function convertRetry_interval($value)
    {
        $this->retry_interval = $value.'m';
    }

    protected function convertDisplay_name($value)
    {
        $this->display_name = "\"".$value."\"";
    }

    protected function convertAlias($value)
    {
        $this->display_name = "\"".$value."\"";
    }

    protected function convertAction_url($value)
    {
        $this->action_url = "\"".$this->migrateLegacyMacros($value)."\"";
    }

    protected function convertNotes_url($value)
    {
        $this->notes_url = "\"".$this->migrateLegacyMacros($value)."\"";
    }

    protected function convertNotes($value)
    {
        $this->notes = "\"".$this->migrateLegacyMacros($value)."\"";
    }

    protected function convertIcon_image($value)
    {
        $this->icon_image = "\"".$this->migrateLegacyMacros($value)."\"";
    }

    protected function migrateUseImport($value, $key = null)
    {
        if ($key != "use") {
                throw new Icinga2ConfigMigrationException(
                    sprintf('Wrong key "%s" as template property of given v1 object: ', $key) . print_r($object, 1)
                );
        }

        return $this->splitComma($value);
    }

    protected function migrateValue($value, $key = null)
    {
        if ($key !== null && in_array($key, $this->v1ArrayProperties)) {
            $values = array();
            foreach ($this->splitComma($value) as $value) {
                //additive is always enabled
                if (substr($value, 0, 1) === '+') {
                    $value = substr($value, 1);
                }
                //TODO: strip exclusions, but fix them somewhere later as blacklisted host
                if (substr($value, 0, 1) === '!') {
                    //$value = substr($value, 1);
                    return;
                }
                $values[] = $this->migrateValue($value);
            }
            return $values;
        }

        if (preg_match('/^\d+/', $value)) {
            return $value;
        }
        return $this->migrateLegacyString($value);
    }

    public static function fromIcingaObjectDefinition(IcingaObjectDefinition $object, IcingaConfig $config)
    {
        switch ($object->getDefinitionType()) {
            case 'command':
                $new = new Icinga2Command($object);
                $new->setAttributesFromIcingaObjectDefinition($object, $config);
                break;

            case 'host':
                $new = new Icinga2Host($object);
                $new->setAttributesFromIcingaObjectDefinition($object, $config);
                break;

            case 'service':
                //print "Found service ".$object;
                $new = new Icinga2Service($object);
                $new->setAttributesFromIcingaObjectDefinition($object, $config);
                break;

            case 'contact':
                $new = new Icinga2User($object);
                $new->setAttributesFromIcingaObjectDefinition($object, $config);
                break;

            case 'hostgroup':
                $new = new Icinga2Hostgroup($object);
                $new->setAttributesFromIcingaObjectDefinition($object, $config);
                break;

            case 'servicegroup':
                $new = new Icinga2Servicegroup($object);
                $new->setAttributesFromIcingaObjectDefinition($object, $config);
                break;

            case 'contactgroup': // TODO: find a better rename way
                $new = new Icinga2Usergroup($object);
                $new->setAttributesFromIcingaObjectDefinition($object, $config);
                break;

            case 'timeperiod':
                $new = new Icinga2Timeperiod($object);
                $new->setAttributesFromIcingaObjectDefinition($object, $config);
                break;

            case 'hostdependency':
                $new = new Icinga2Dependency($object);
                $new->setAttributesFromIcingaObjectDefinition($object, $config);
                break;

            case 'servicedependency':
                $new = new Icinga2Dependency($object);
                $new->setAttributesFromIcingaObjectDefinition($object, $config);
                break;

            case 'hostescalation':
                $new = new Icinga2Notification($object);
                $new->setAttributesFromIcingaObjectDefinition($object, $config);
                break;

            case 'serviceescalation':
                $new = new Icinga2Notification($object);
                $new->setAttributesFromIcingaObjectDefinition($object, $config);
                break;

            default:
                throw new Icinga2ConfigMigrationException(
                    sprintf(
                        'Cannot convert unknown object "%s" of type "%s"',
                        $object,
                        $object->getDefinitionType()
                    )
                );
        }

        return $new;
    }

    protected function collectObjectAttributeRecursive($object, $attr)
    {
        static $attrs = array();
        if ($object->$attr) {
            $attrs[] = $object->$attr;
            return $attrs;
        }

        $templates = $object->getParents();

        foreach ($templates as $template) {
            if (!$template->$attr) {
                $this->collectObjectAttributeRecursive($template, $attr);
            } else {
                $attrs[] = $template->$attr;
                return $attrs;
            }
        }
    }

    protected function splitComma($string)
    {
        return preg_split('/\s*,\s*/', $string, null, PREG_SPLIT_NO_EMPTY);
    }

    protected function str2Arr($string, $delim, $unique = false, $sort = false)
    {
        $arr = preg_split('/\s*'.$delim.'\s*/', $string, null, PREG_SPLIT_NO_EMPTY);

        if ($unique == true) {
            $arr = array_unique($arr);
        }

        if ($sort == true) {
            sort($arr);
        }

        return $arr;
    }

    protected function migrateLegacyString($string)
    {
        $string = preg_replace('~\\\~', '\\\\', $string);
        $string = preg_replace('~"~', '\\"', $string);
        return '"' . $string . '"';
    }

    protected function renderArray($array)
    {
        $parts = array();
        foreach ($array as $val) {
            $parts[] = $this->migrateLegacyString($val);
        }
        return '[ ' . implode(', ', $parts) . ' ]';
    }

    protected function arrayToString($array)
    {
        $string = '[ ' . implode(', ', $array) . ' ]';
        return $string;
    }

    protected function getAttributesAsString()
    {
        $str = '';
        foreach ($this->properties as $key => $val) {
            if (is_array($val)) {
                $val = $this->renderArray($val);
            }
            $str .= sprintf("    %s = %s\n", $key, $val);
        }
        return $str;
    }

    protected function getVarsAsString() {
        $str = '';
        foreach ($this->vars as $key => $val) {
            $str .= sprintf("    vars.%s = \"%s\"\n", $key, $val);
        }
        return $str;
    }

    protected function getTimesAsString() {
        $str = '';
        foreach ($this->times as $key => $val) {
            $str .= sprintf("    times.%s = %s\n", $key, $val);
        }
        return $str;
    }

    protected function getImportsAsString() {
        $str = '';
        foreach ($this->imports as $import) {
            $str .= sprintf("    import \"%s\"\n", $import);
        }
        return $str;
    }

    protected function getAssignmentsAsString()
    {
        $str = '';
        foreach ($this->assigns as $assign) {
            $str .= sprintf("    assign where %s\n", $assign);
        }
        foreach ($this->ignores as $ignore) {
            $str .= sprintf("    ignore where %s\n", $ignore);
        }
        return $str;
    }

    protected function getEventCommandsAsString() {
        $str = '';
        foreach ($this->eventcommands as $command => $line) {
            $str .= sprintf("\nobject EventCommand \"%s\" {\n", $command);
            $str .= sprintf("    import \"plugin-event-command\"\n");
            $str .= sprintf("    command = \"%s\"\n", $line);
            $str .= sprintf("}\n");
        }
        return $str;
    }

    protected function getNotificationCommandsAsString() {
        $str = '';
        foreach ($this->notificationcommands as $command => $line) {
            $str .= sprintf("\nobject NotificationCommand \"%s\" {\n", $command);
            $str .= sprintf("    import \"plugin-notification-command\"\n");
            $str .= sprintf("    command = \"%s\"\n", $line);
            $str .= sprintf("}\n\n");
        }
        return $str;
    }

    protected function getNotificationsAsString() {
        $str = '';

        if (count($this->notifications) > 0) {
            $str .= sprintf("//MIGRATION - NOTIFICATIONS (NEW) -- BEGIN\n");
        }

        foreach ($this->notifications as $notification_name => $notification_attr) {

            //workaround for unspecified services (no direct host_name)
            $apply_target = "";
            if (array_key_exists('host_name', $notification_attr)) {
                $obj_type = "object";
            } else {
                $obj_type = "apply";
                $apply_target = "to Service ";
            }

            $str .= sprintf("\n%s Notification \"%s\" %s{\n", $obj_type, $notification_name, $apply_target);
            $str .= sprintf("    import \"%s\"\n", $notification_attr['import']);
            $str .= sprintf("    command = \"%s\"\n", $notification_attr['command']);
            $str .= sprintf("    period = \"%s\"\n", $notification_attr['period']);
            $str .= sprintf("    interval = %s\n", ((int)$notification_attr['interval']).'m');

            $str .= sprintf("    states = %s\n", $this->arrayToString($notification_attr['states'])); //constants, no strings
            $str .= sprintf("    types = %s\n", $this->arrayToString($notification_attr['types'])); //constants, no strings

            $str .= sprintf("    users = %s\n", $this->renderArray($notification_attr['users'])); //double quoted strings

            $str .= $this->getTimesAsString();

            //host notification
            if (array_key_exists('host_name', $notification_attr) && !array_key_exists('service_name', $notification_attr)) {
                $str .= sprintf("    host_name = \"%s\"\n", $notification_attr['host_name']);
            }

            //service notification
            else if (array_key_exists('host_name', $notification_attr) && array_key_exists('service_name', $notification_attr)) {
                $str .= sprintf("    host_name = \"%s\"\n", $notification_attr['host_name']);
                $str .= sprintf("    service_name = \"%s\"\n", $notification_attr['service_name']);
            }

            //no direct specification, do something magic with apply
            else {
                if ($obj_type == "apply") {
                    if (array_key_exists('service_assign', $notification_attr)) {
                        $str .= sprintf("    assign where %s\n", $notification_attr['service_assign']);
                    }
                    $this->getAssignmentsAsString();
                }
            }

            $str .= sprintf("}\n\n");
        }

        if (count($this->notifications) > 0) {
            $str .= sprintf("//MIGRATION - NOTIFICATIONS (NEW) -- END\n");
        }

        return $str;
    }

    protected function getDependenciesAsString() {
        $str = '';

        if (count($this->dependencies) > 0) {
            $str .= sprintf("//MIGRATION - DEPENDENCIES (NEW) -- BEGIN\n");
        }

        foreach ($this->dependencies as $dep_name => $dep_attr) {
            $obj_type = "object";

            $str .= sprintf("\n%s Dependency \"%s\" {\n", $obj_type, $dep_name);

            if (array_key_exists('child_host_name', $dep_attr)) {
                $str .= sprintf("    child_host_name = \"%s\"\n", $dep_attr['child_host_name']);
            }
            if (array_key_exists('parent_host_name', $dep_attr)) {
                $str .= sprintf("    parent_host_name = \"%s\"\n", $dep_attr['parent_host_name']);
            }

            $str .= sprintf("}\n\n");
        }

        if (count($this->dependencies) > 0) {
            $str .= sprintf("//MIGRATION - DEPENDENCIES (NEW) -- END\n");
        }

        return $str;
    }

    public function isTemplate()
    {
        return $this->is_template;
    }

    public function isApply()
    {
        return $this->is_apply;
    }

    public function hasImport()
    {
        return $this->has_import;
    }

 // inherits "plugin-check-command"

    public function __toString()
    {
        $prefix = "object";
        $target = "";
        if ($this->isTemplate()) {
            $prefix = "template";
        }
        if ($this->isApply()) {
            $prefix = "apply";
            $target = "to ". $this->apply_target . " ";
        }

        //print_r("prefix: ".$prefix."\n");
        //var_dump($this);

        $str = '';
        $str .= sprintf(
            "\n%s %s \"%s\" %s{\n%s%s%s\n%s\n%s}\n",
            $prefix,
            $this->type,
            $this->name,
            $target,
            $this->getImportsAsString(),
            $this->getAttributesAsString(),
            $this->getVarsAsString(),
            $this->getTimesAsString(),
            $this->getAssignmentsAsString()
        );

        //additional objects newly created
        $str .= sprintf(
            "%s\n%s\n%s\n%s\n",
            $this->getEventCommandsAsString(),
            $this->getNotificationCommandsAsString(),
            $this->getNotificationsAsString(),
            $this->getDependenciesAsString()
        );

        return $str;
    }

    public function dump()
    {
        echo $this->__toString();
    }

    public static function dumpDefaultTemplates()
    {
            printf('
//MIGRATION DEFAULT TEMPLATES -- BEGIN
template Notification "generic-host-notification" {
  states = [ Up, Down ]
  types = [ Problem, Acknowledgement, Recovery, Custom,
            FlappingStart, FlappingEnd,
            DowntimeStart, DowntimeEnd, DowntimeRemoved ]

  period = "24x7"
}

template Notification "generic-service-notification" {
  states = [ OK, Warning, Critical, Unknown ]
  types = [ Problem, Acknowledgement, Recovery, Custom,
            FlappingStart, FlappingEnd,
            DowntimeStart, DowntimeEnd, DowntimeRemoved ]

  period = "24x7"
}

object NotificationCommand "generic-escalation-dummy" {
    import "plugin-notification-command"
    command = "echo \"This escalation needs a proper notification command. Please FIXME.\""
}
//MIGRATION DEFAULT TEMPLATES -- END

');
    }

    public function migrateLegacyMacros($line)
    {
        $patterns = array (
            //user
            "\$CONTACTNAME\$" => "\$user.name\$",
            "\$CONTACTALIAS\$" => "\$user.display_name\$",
            "\$CONTACTEMAIL\$" => "\$user.email\$",
            "\$CONTACTPAGER\$" => "\$user.pager\$",
            "\$CONTACTADDRESS1\$" => "\$user.vars.address1\$",
            "\$CONTACTADDRESS2\$" => "\$user.vars.address2\$",
            "\$CONTACTADDRESS3\$" => "\$user.vars.address3\$",
            "\$CONTACTADDRESS4\$" => "\$user.vars.address4\$",
            "\$CONTACTADDRESS5\$" => "\$user.vars.address5\$",
            "\$CONTACTADDRESS6\$" => "\$user.vars.address6\$",
            //service
            "\$SERVICEDESC\$" => "\$service.description\$",
            "\$SERVICEDISPLAYNAME\$" => "\$service.display_name\$",
            "\$SERVICECHECKCOMMAND\$" => "\$service.check_command\$",
            "\$SERVICESTATE\$" => "\$service.state\$",
            "\$SERVICESTATEID\$" => "\$service.state_id\$",
            "\$SERVICESTATETYPE\$" => "\$service.state_type\$",
            "\$SERVICEATTEMPT\$" => "\$service.check_attempt\$",
            "\$MAXSERVICEATTEMPT\$" => "\$service.max_check_attempts\$",
            "\$LASTSERVICESTATE\$" => "\$service.last_state\$",
            "\$LASTSERVICESTATEID\$" => "\$service.last_state_id\$",
            "\$LASTSERVICESTATETYPE\$" => "\$service.last_state_type\$",
            "\$LASTSERVICESTATECHANGE\$" => "\$service.last_state_change\$",
            "\$SERVICEDURATIONSEC\$" => "\$service.duration_sec\$",
            "\$SERVICELATENCY\$" => "\$service.latency\$",
            "\$SERVICEEXECUTIONTIME\$" => "\$service.execution_time\$",
            "\$SERVICEOUTPUT\$" => "\$service.output\$",
            "\$SERVICEPERFDATA\$" => "\$service.perfdata\$",
            "\$LASTSERVICECHECK\$" => "\$service.last_check\$",
            "\$SERVICENOTES\$" => "\$service.notes\$",
            "\$SERVICENOTESURL\$" => "\$service.notes_url\$",
            "\$SERVICEACTIONURL\$" => "\$service.action_url\$",
            //host
            "\$HOSTADDRESS\$" => "\$address\$", //special case, no fallback for $host.name$
            "\$HOSTADDRESS6\$" => "\$address6\$", //special case, no fallback for $host.name$
            "\$HOSTNAME\$" => "\$host.name\$",
            "\$HOSTDISPLAYNAME\$" => "\$host.display_name\$",
            "\$HOSTCHECKCOMMAND\$" => "\$host.check_command\$",
            "\$HOSTALIAS\$" => "\$host.display_name\$",
            "\$HOSTSTATE\$" => "\$host.state\$",
            "\$HOSTSTATEID\$" => "\$host.state_id\$",
            "\$HOSTSTATETYPE\$" => "\$host.state_type\$",
            "\$HOSTATTEMPT\$" => "\$host.check_attempt\$",
            "\$MAXHOSTATTEMPT\$" => "\$host.max_check_attempts\$",
            "\$LASTHOSTSTATE\$" => "\$host.last_state\$",
            "\$LASTHOSTSTATEID\$" => "\$host.last_state_id\$",
            "\$LASTHOSTSTATETYPE\$" => "\$host.last_state_type\$",
            "\$LASTHOSTSTATECHANGE\$" => "\$host.last_state_change\$",
            "\$HOSTDURATIONSEC\$" => "\$host.duration_sec\$",
            "\$HOSTLATENCY\$" => "\$host.latency\$",
            "\$HOSTEXECUTIONTIME\$" => "\$host.execution_time\$",
            "\$HOSTOUTPUT\$" => "\$host.output\$",
            "\$HOSTPERFDATA\$" => "\$host.perfdata\$",
            "\$LASTHOSTCHECK\$" => "\$host.last_check\$",
            "\$HOSTNOTES\$" => "\$host.notes\$",
            "\$HOSTNOTESURL\$" => "\$host.notes_url\$",
            "\$HOSTACTIONURL\$" => "\$host.action_url\$",
            "\$TOTALSERVICES\$" => "\$host.num_services\$",
            "\$TOTALSERVICESOK\$" => "\$host.num_services_ok\$",
            "\$TOTALSERVICESWARNING\$" => "\$host.num_services_warning\$",
            "\$TOTALSERVICESUNKNOWN\$" => "\$host.num_services_unknown\$",
            "\$TOTALSERVICESCRITICAL\$" => "\$host.num_services_critical\$",
            //command
            "\$COMMANDNAME\$" => "\$command.name\$",
            //notification
            "\$NOTIFICATIONTYPE\$" => "\$notification.type\$",
            "\$NOTIFICATIONAUTHOR\$" => "\$notification.author\$",
            "\$NOTIFICATIONCOMMENT\$" => "\$notification.comment\$",
            "\$NOTIFICATIONAUTHORNAME\$" => "\$notification.author\$",
            "\$NOTIFICATIONAUTHORALIAS\$" => "\$notification.author\$",
            //global runtime
            "\$TIMET\$" => "\$icinga.timet\$",
            "\$LONGDATETIME\$" => "\$icinga.long_date_time\$",
            "\$SHORTDATETIME\$" => "\$icinga.short_date_time\$",
            "\$DATE\$" => "\$icinga.date\$",
            "\$TIME\$" => "\$icinga.time\$",
            "\$PROCESSSTARTTIME\$" => "\$icinga.uptime\$",
            //global stats
            "\$TOTALHOSTSUP\$" => "\$icinga.num_hosts_up\$",
            "\$TOTALHOSTSDOWN\$" => "\$icinga.num_hosts_down\$",
            "\$TOTALHOSTSUNREACHABLE\$" => "\$icinga.num_hosts_unreachable\$",
            "\$TOTALSERVICESOK\$" => "\$icinga.num_services_ok\$",
            "\$TOTALSERVICESWARNING\$" => "\$icinga.num_services_warning\$",
            "\$TOTALSERVICESCRITICAL\$" => "\$icinga.num_services_critical\$",
            "\$TOTALSERVICESUNKNOWN\$" => "\$icinga.num_services_unknown\$",
        );

        foreach ($patterns as $match => $replace) {
            $line = str_replace($match, $replace, $line);
        }

        //same for $_HOSTFOO$ and $_SERVICEBAR$ as $host.vars.FOO$ and $service.vars.BAR$
        $line = preg_replace('/\$_HOST(\w+)\$/', '\$host.vars.$1\$', $line);
        $line = preg_replace('/\$_SERVICE(\w+)\$/', '\service.vars.$1\$', $line);

        //TODO if there is still a $...$ string in there, warn the user.
        //EVENTSTARTTIME

        return $line;
    }

    public function migrateNotificationOptions($options, $is_host)
    {
        $filter = array (
            'state' => array(),
            'type' => array()
        );

        $filter_names = array (
            'o' => 'OK', //Up for hosts
            'w' => 'Warning',
            'c' => 'Critical',
            'u' => 'Unknown',
            'd' => 'Down',
            's' => 'DowntimeStart, DowntimeEnd, DowntimeRemoved',
            'r' => 'Recovery',
            'f' => 'FlappingStart, FlappingEnd'
        );
        $filter_by = array (
            'o' => 'state',
            'w' => 'state',
            'c' => 'state',
            'u' => 'state',
            'd' => 'state',
            's' => 'type',
            'r' => 'type',
            'f' => 'type'
        );

        $options_arr = $this->str2Arr($options, ",", true, true);

        # n means nothing, bail early
        if (array_key_exists('n', $options_arr)) {
            $filter['state'] = 0;
            $filter['type'] = 0;
            return $filter;
        }

        # recovery requires the Ok/Up state
        if (array_key_exists('r', $options_arr)) {
            if ($is_host) {
                $filter['state'][] = 'Up';
            } else {
                $filter['state'][] = 'OK';
            }
        }

        # always add Problem|Custom|OK
        $filter['type'][] = 'Problem';
        $filter['type'][] = 'Custom';
        if ($is_host) {
            $filter['state'][] = 'Up';
        } else {
            $filter['state'][] = 'OK';
        }

        //user wants all options
        if (array_key_exists('a', $options_arr)) {
            foreach ($filter_by as $by => $types) {
                $value = $filter_names[$by];

                if ($value == "OK" && $is_host == true) {
                    $value = "Up";
                }

                if ($value == "Unknown" && $is_host == true) {
                    printf("//WARNING: Skipping unreachable host filter.\n");
                    continue;
                }

                $filter[$filter_by[$by]][] = $value;
            }
            return $filter;
        }

        //the selective way
        foreach ($options_arr as $option) {
            trim($option);

            if (array_key_exists($option, $filter_names)) {
                $value = $filter_names[$option];

                if ($value == "OK" && $is_host == true) {
                    $value = "Up";
                }

                if ($value == "Unknown" && $is_host == true) {
                    printf("//WARNING: Skipping unreachable host filter.\n");
                    continue;
                }

                $filter[$filter_by[$option]][] = $value;
            } else {
                sprintf("//ERROR: Unknown filter option: '%s'", $option);
            }
        }

        return $filter;

    }
}
