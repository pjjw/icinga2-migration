# Icinga 2 Migration Script

This standalone script allows you to migrate the basic Icinga 1.x
object configuration into native Icinga 2 configuration objects.

> **Note**
>
> You are highly advised to read the Icinga 2 Migration documentation
> and understand the new dynamic configuration syntax.
>
> Check the manual migration hints and also manually migrate the
> configuration.

## General Information

This script is a bootstrapped standalone version of the Icinga Web 2 CLI
module for configuration migration from Icinga 1.x to 2.x.

> **Note**
>
> This project will be merged into the upstream CLI once there is
> a stable release. Meanwhile it acts as standalone helper tool
> for migrating to Icinga 2.

## Requirements

* Apache2 with PHP >= 5.3.0 enabled
* PHP Zend Framework

Debian requires the `zendframework` package installed.
RHEL/CentOS requires the EPEL repository enabled (which provides the `php-ZendFramework`
package). OpenSUSE requires the [server monitoring](https://build.opensuse.org/project/show/server:monitoring) repository (which provides the `php5-ZendFramework` package) enabled.

## Icinga 1.x to 2.x Migration

Call the migrate command action pointing to your Icinga 1.x main
configuration file and pipe the output to a file included in your
Icinga 2 configuration:

 $ sudo bin/icinga-conftool migrate v1 /etc/icinga/icinga.cfg > /tmp/migrate.conf

An example for validating the generated objects against a bootstrapped icinga2
config is located in `bin/run`.


## Manual Migration Required

Currently the following Icinga 1.x objects must be migrated manually:

* Escalations
* Dependencies

These objects are either not directly compatible, or would lead into
(logic) errors without knowing about your configuration strategy.

Furthermore, these Icinga 1.x specifics are not supported either:

* Regular expressions and wildcard matching
* Invalid objects ('name' instead of 'service_description' for service objects)
* Invalid templates (missing 'name' attribute)


## Automatic Migration

This is by far an imcomplete list, but shows the general approach of
this migration script:

* All compatible object attributes
* Keep the template tree (`register 0` -> `template`) and import these templates (`use` -> `import`)
* Keep service relation ship to `host_name` and `hostgroup_name`
* Use the apply rules wherever possible (e.g. for service with `hostgroup_name` attribute)
* Assign group members directly
* Migrate host/service contacts for notifications into the new Notification logic
* Convert the notification options to state/type filters
* Create additional `EventCommand` objects for event handler migration
* Create additional `NotificationCommand` objects for notification migration
* Migrate the ARGn command arguments into custom attributes for commands
* Replace runtime macros in command line for check, event, notification commands
* Replace runtime macros in notes, notes_url, action_url, icon_image attributes
* Migrate host parents into simple host dependencies
* Treat all intervals as minute duration literal - append `m`
* Exclusions in service->hostgroup and group membership assignments
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


## Known Caveats

### Notification Migration

The contact-to-notification migration generates a new notification object
per unique host/service contact (also in groups) and its
{host,service}_notification_command.

* Better: Get an idea how the new Icinga 2 notifications work, and
apply them based on your group memberships, custom attributes, or host
relations!

## Commands

Event and Notification commands will be added once used. The Icinga 1.x
command objects serve as Check command, and may require cleanup afterwards.

## Dependencies

The parent `hostgroup_name` is not supported. Migrate this dependency
manually. The `host_name` attribute cannot contrain multiple entries, only
the first one will be processed. `inherits_parent` is ignored, default
is enabled.

## Escalations

The `generic-escalation-dummy` command acts as placeholder for a proper
notification command. There is no way to automagically get the right
notification command from the assigned contacts and contactgroups.

## Timeperiods

Complex timeperiod strings are not processed and will result in a warning.
