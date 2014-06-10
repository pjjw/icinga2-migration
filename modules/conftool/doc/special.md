
# <a id="special-config"></a> Special configuration

Currently not supported.

## Template without 'name' attribute

define servicegroup {
        servicegroup_name               3441domain-escalation
        alias                           3441ServiceGroup Name
        register                       0
}

## Service objects without service_description

define service {
        use                             test-generic-service
        hostgroup_name                  3961grpA,!3961grpB
}

define service {
        use                             proc:httpd
        host_name                       1066localhost
}


## Old macros in notes, notes_url, action_url

Must be converted.

## Old macros in commands

Must be converted.

