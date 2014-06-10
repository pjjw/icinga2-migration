# <a id="Migration"></a> Migration

## Icinga 1.x to 2.x Migration

Install the Icinga CLI.

> **Note**
>
> configure/make will be dropped soon.

 $ ./configure --prefix=/usr/share/icingaweb --datadir=/usr/share/icingaweb --with-icingaweb-config-path=/etc/icingaweb && sudo make install-basic

Then call the migrate command action:

 $ sudo icingacli conftool migrate v1 /etc/icinga/icinga.cfg

