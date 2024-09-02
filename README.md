# check_md
Nagios check script to get status of md devices from mdstat

## Requires
Nothing special

## Setup
Download check_md zip file or clone it into your target server, then
```$ php composer.phar install```
```$ php composer.phar dump-autoload -o```

and call it remotely with `check_by_ssh`

## Usage
Nagios configuration:
```
define command {
        command_name    check_by_ssh_check_md
        command_line    $USER1$/check_by_ssh -H $HOSTADDRESS$ -p 143 -C "php /home/nagios/check_systemd/check_md.php --device $ARG1$"
}

define service {
        use                             generic-service
        host_name                       myhost
        service_description             my-service
        check_command                   check_md!md2
}
```

## Checks performed
Currently, checks if the device has any down disks (critical) and also if some check/recovery/resync is ongoing (warning) and reports its progress and finished estimation.
