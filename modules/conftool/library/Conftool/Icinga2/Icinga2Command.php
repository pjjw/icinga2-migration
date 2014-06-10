<?php

namespace Icinga\Module\Conftool\Icinga2;

class Icinga2Command extends Icinga2ObjectDefinition
{
    protected $type = 'CheckCommand'; // FIXME

    protected $v1AttributeMap = array(
        'command_line' => 'command',
    );

    protected function convertCommand_line($line) {
        //TODO migrate changed runtime macros

        $line = addslashes($line);

        //TODO
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

        //TODO same for $_HOSTFOO$ and $_SERVICEBAR$ as $host.vars.FOO$ and $service.vars.BAR$
        $line = preg_replace('/\$_HOST(\w+)\$/', '\$host.vars.$1\$', $line);
        $line = preg_replace('/\$_SERVICE(\w+)\$/', '\service.vars.$1\$', $line);

        //TODO if there is still a $...$ string in there, warn the user.
        //EVENTSTARTTIME

        $this->properties['command'] = "\"".$line."\"";
        return $line;
    }
}
