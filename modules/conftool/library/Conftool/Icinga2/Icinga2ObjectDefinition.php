<?php

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
    protected $assigns = array();
    protected $ignores = array();
    protected $imports = array();
    protected $vars = array();

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

    protected function setAttributesFromIcingaObjectDefinition(IcingaObjectDefinition $object, IcingaConfig $config)
    {
        //template, parents and config
        $this->is_template = $object->isTemplate();
        $this->_parents = $object->getParents();

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
                continue;
            }

            //check command arguments
            if ($key == "check_command" && ($object instanceof IcingaService || $object instanceof IcingaHost)) {
                $command_arr = explode("!", $value);

                $this->properties['check_command'] = "\"".$command_arr[0]."\""; //first is always the command name

                for($i = 1; $i < count($command_arr); $i++) {
                    $varname = "ARG".$i;
                    $varvalue = addslashes($command_arr[$i]); //escape the string 
                    //TODO check against legacy macros and replace them
                    $this->vars($varname, $varvalue);
                }
        continue;
            }

            //convert host/service notifications
            if ($key == "contacts" && ($object instanceof IcingaService || $object instanceof IcingaHost)) {
                $arr = $this->splitComma($value);

                //var_dump($object);
                //var_dump($arr);

                foreach ($arr as $contact) {

                    //strip additive character
                    $name = preg_replace('/^\+/', '', $contact);
                    $contact_obj = $config->GetObject($name, 'contact');

                    if ($contact_obj == false) {
                        print("Unknown contact '" . $name . "' referenced by object '" . $this->name . "'.");
                        continue;
                    }

                    //var_dump($contact_obj);

                    //TODO
                    //1. host|service_notification_commands in contact template tree?
                    //2. split array, get commands
                    //3. add new notification objects based on "hostname-servicename-commandname"
                    //4. add attributes: users, converted options, interval
                }

                continue;
            }

            if ($key == "contact_groups" && ($object instanceof IcingaService || $object instanceof IcingaHost)) {
                $arr = $this->splitComma($value);

                foreach ($arr as $contactgroup) {
                    $contactgroup_obj = $config->GetObject($contactgroup, 'contactgroup');

                    //var_dump($contactgroup_obj);
                }

                continue;
            }

            //conversion of different attributes
            $func = 'convert' . ucfirst($key);
            if (method_exists($this, $func)) {
                $this->$func($value);
                continue;
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

        //custom vars
        foreach ($object->getCustomVars() as $key => $value) {
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
                $values[] = $this->migrateValue($value);
            }
            return $values;
        }

        //special handling for address
        if ($key == "address") {
            return $this->migrateLegacyString($value);
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
        if ($this->isTemplate()) {
            $prefix = "template";
        }
        if ($this->isApply()) {
            $prefix = "apply";
        }
        
        return sprintf(
            "%s %s \"%s\" {\n%s%s%s\n%s}\n\n",
            $prefix,
            $this->type,
            $this->name,
            $this->getImportsAsString(),
            $this->getAttributesAsString(),
            $this->getVarsAsString(),
            $this->getAssignmentsAsString()
        );
    }

    public function dump()
    {
        echo $this->__toString();
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
}


