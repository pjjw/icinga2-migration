# <a id="Migration"></a> Migration

This is a bootstrapped standalone version of the Icinga Web 2 CLI
module for configuration migration from Icinga 1.x to 2.x.

> **Note**
>
> This project will be merged into the upstream CLI once there is
> a stable release. Meanwhile it acts as standalone helper tool
> for migrating to Icinga 2.

## Requirements

* php5.3+
* Zend Framework

## Icinga 1.x to 2.x Migration

Call the migrate command action pointing to your Icinga 1.x main
configuration file:

 $ sudo bin/icinga-conftool migrate v1 /etc/icinga/icinga.cfg

