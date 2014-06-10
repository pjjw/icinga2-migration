<?php

namespace Icinga\Module\Conftool\Icinga;

class IcingaUtil
{
    public static function getHostStateName($state)
    {
        $states = array(
            0 => 'UP',
            1 => 'DOWN',
            2 => 'UNREACHABLE',
            3 => 'UNKNOWN'
        );
        if (isset($states[$state])) {
            return $states[$state];
        }
        return sprintf('OUT OF BOUND (%d)', (int) $state);
    }

    public static function getServiceStateName($state)
    {
        $states = array(
            0 => 'OK',
            1 => 'WARNING',
            2 => 'CRITICAL',
            3 => 'UNKNOWN'
        );
        if (isset($states[$state])) {
            return $states[$state];
        }
        return sprintf('OUT OF BOUND (%d)', (int) $state);
    }
}
