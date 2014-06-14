# Icinga 2 Migration Script

This standalone script allows you to migrate the basic Icinga 1.x
object configuration into native Icinga 2 configuration objects.

> **Note**
>
> You are highly advised to read the Icinga 2 Migration documentation
> and understand the new dynamic configuration syntax.
>
> Check the manual migration hints and also manually migrate the
> configuration at https://docs.icinga.org/icinga2/latest/

## General Information

This script is a bootstrapped standalone version of the Icinga Web 2 CLI
module for configuration migration from Icinga 1.x to 2.x.

> **Note**
>
> This project will be merged into the upstream CLI once there is
> a stable release. Meanwhile it acts as standalone helper tool
> for migrating to Icinga 2.

### Requirements

* PHP CLI >= 5.3.x
* PHP Zend Framework

Debian requires the `zendframework` package installed.
RHEL/CentOS requires the EPEL repository which provides the `php-ZendFramework`
package.
OpenSUSE requires the [server monitoring](https://build.opensuse.org/project/show/server:monitoring)
repository which provides the `php5-ZendFramework` package.

### Run

Call the migrate command action pointing to your Icinga 1.x main
configuration file and pipe the output to a file included in your
Icinga 2 configuration:

 $ bin/icinga-conftool migrate v1 /etc/icinga/icinga.cfg > /etc/icinga2/migrate.conf

Make sure that your user can access the `icinga.cfg` file and all included
directories and files (`cfg_file`, `cfg_dir` attributes in `icinga.cfg`).

An example for validating the generated objects with a bootstrapped icinga2
config is located in `bin/run`.

### Support

If you encounter bugs, or have patches, please join the community
support channels at https://support.icinga.org

## Additional Migration Hints

### Manual Migration Required

These Icinga 1.x specifics are not supported:

* Regular expressions and wildcard matching
* Invalid objects ('name' instead of 'service\_description' for service objects)
* Invalid templates (missing 'name' attribute)
* More unknown (not documented) object tricks
* Special macros
* Deprecated attributes and objects ({host,service}extinfo, etc)


### Automatic Migration

This is by far an imcomplete list, but shows the general approach of
this migration script:

* All compatible object attributes
* Keep the template tree (`register 0` -> `template`) and import these templates (`use` -> `import`)
* Keep service relation ship to `host_name` and `hostgroup_name`
* Use the apply rules wherever possible (e.g. for service with `hostgroup_name` attribute, Dependencies, Escalation Notifications, etc)
* Assign group members directly
* Migrate host/service contacts for notifications into the new Notification logic
* Convert the notification options to state/type filters
* Create additional `EventCommand` objects for event handler migration
* Create additional `NotificationCommand` objects for notification migration
* Migrate the ARGn command arguments into custom attributes for commands
* Convert custom variables into custom attributes
* Replace runtime macros in command line for check, event, notification commands
* Replace runtime macros in notes, notes\_url, action\_url, icon\_image attributes
* Migrate host parents into simple host dependencies
* Treat all intervals as minute duration literal - append `m`
* Exclusions in service => hostgroup mappings and group membership assignments
* Turn * (all) into a match function in expressions
* All comma separated lists are converted into arrays, if possible (except contacts for notifications)

  Icinga 1.x 	| Icinga 2.x						| Specialities
  --------------|-------------------------------------------------------|---------------
  host		| Host							| .
  service	| Service						| object or apply rule
  . (contacts)  | Notification						| object or apply rule
  hostgroup     | HostGroup						| membership assign
  servicegroup  | ServiceGroup						| membership assign
  contact	| User							| .
  contactgroup  | UserGroup						| membership assign
  timeperiod    | TimePeriod						| only basic
  command       | CheckCommand, NotificationCommand, EventCommand	| cleanup required

#### Templates

  Template / Command		| Purpose
  ------------------------------|------------------------------
  generic-host-notification	| generated notifications
  generic-mail-notification	| generated notifications
  generic-escalation-dummy	| escalations w/o command
  migration-check-command	| generic check command with `vars.USER1 = PluginDir` mapping
  migration-event-command	| generic event command with `vars.USER1 = PluginDir` mapping
  migration-notification-command | generic notification command with `vars.USER1 = PluginDir` mapping


## Known Caveats

### Notification Migration

The contact-to-notification migration generates a new notification object
per unique host/service contact (also in groups) and its
{host,service}_notification_command.
The `first_notification_delay` attribute is not automatically converted.

* Better: Get an idea how the new Icinga 2 notifications work, and
apply them based on your group memberships, custom attributes, or host
relations!

### Escalations

The `generic-escalation-dummy` command acts as placeholder for a proper
notification command. There is no way to automagically get the right
notification command from the assigned contacts and contactgroups.

## Commands

Event and Notification commands will be added once used. The Icinga 1.x
command objects serve as Check command, and may require cleanup afterwards.

## Dependencies

The parent `hostgroup_name` is not supported. Migrate this dependency
manually. The `host_name` attribute cannot contrain multiple entries, only
the first one will be processed. `inherits_parent` is ignored, default
is enabled.
By default all dependencies will disable notifications and checks.
`notification_failure_criteria` and `execution_failure_criteria` are not
1:1 the same for the migration process.

## Timeperiods

Complex timeperiod strings are not processed and will result in a warning.
