<?php

namespace Icinga\Module\Conftool\Icinga;

class IcingaStatus
{
    protected $status_file;
    protected $status = array();
    protected $known_keys = array(
        // 'info',
        // 'programstatus',
        'hoststatus' => array(
            'host_name',
            'plugin_output',
            'check_command',
            'performance_data',
            'problem_has_been_acknowledged', // 0|1
            'is_flapping', // 0|1
            'last_check', // unix timestamp
            'next_check', // unix timestamp
            'current_state', // 0|1|2|3 (OK, WARNING, CRITICAL, UNKNOWN)
        ),
        'servicestatus' => array(
            'host_name',
            'service_description',
            'check_command',
            'plugin_output',
            // 'long_plugin_output',
            'performance_data',
            'current_state',   // 0123
            'last_hard_state', // 0123
            'last_state_change',       // Unix timestamp
            'last_hard_state_change',  //  "
            'last_time_ok',            //  "
            'last_time_warning',       //  "
            'last_time_unknown',       //  "
            'last_time_critical',      //  "
        ),
        // 'contactstatus',
        // 'servicecomment',
    );

    protected function __construct()
    {
    }

    protected function requireSubArray($key, & $array)
    {
        if (! array_key_exists($key, $array)) {
            $array[$key] = array();
        }
    }

    protected function parseStatusFile($file)
    {
        $this->status_file = $file;
        $fh = @fopen($this->status_file, 'r');
        if ($fh === false) {
            throw new IcingaStatusException(sprintf(
                'Unable to open status file %s',
                $this->status_file
            ));
        }
        $section_type = null;
        $first = false;
        $data = null;
        $current_host = null;
        while ($line = fgets($fh)) {
            $line = rtrim(preg_replace('~#.*$~', '', $line));
            if ($section_type === null) {
                if (preg_match('~^\s*([a-z]+)\s+\{\s*$~', $line, $match)) {
                    $section_type = $match[1];
                    $first = true;
                    continue;
                }
                if (! preg_match('~^\s*$~', $line)) {
                    throw new IcingaStatusException(sprintf(
                        'Got unexpected line out of section: %s',
                        $line
                    ));
                }
            }
            if (preg_match('~^\s*$~', $line)) {
                continue;
            }
            if (preg_match('~^\s*\}\s*$~', $line)) {
                $section_type = null;
                $current_host = null;
                $first = false;
                continue;
            }
            if (! preg_match('~=~', $line)) {
                throw new IcingaStatusException(sprintf(
                    'Expecting key=value line: %s',
                    $line
                ));
            }
            list($key, $val) = preg_split('~\s*=\s*~', $line, 2);
            $key = trim($key);

            if (! $key) {
                throw new IcingaStatusException(sprintf(
                    'Unable to parse line: %s',
                    $line
                ));
            }
            switch($section_type) {
                case 'hoststatus':
                    if ($first) {
                        if ($key !== 'host_name') {
                            throw new IcingaStatusException(sprintf(
                                'host_name needs to be on the first line: %s',
                                $line
                            ));
                        }
                        $this->status[$current_host] = array(
                            'services' => array()
                        );
                        $current_host = $val;
                        $first = false;
                    }
                    if (in_array($key, $this->known_keys[$section_type])) {
                        $this->status[$current_host][$key] = $val;
                    }
                    break;
                case 'servicestatus':
                    if ($first) {
                        if ($current_host === null) {
                            if ($key !== 'host_name') {
                                throw new IcingaStatusException(sprintf(
                                    'host_name needs to be on first service line: %s',
                                    $line
                                ));
                            }
                            $current_host = $val;
                            $status = & $this->status[$current_host]['services'];
                        } else {
                            if ($key !== 'service_description') {
                                throw new IcingaStatusException(sprintf(
                                    'service_description needs to be on second service line: %s',
                                    $line
                                ));
                            }
                            $status[$val] = array(
                                'host_name' => $current_host,
                                'service_description' => $val
                            );
                            $status = & $status[$val];
                            $first = false;
                        }
                    } else {
                        if (in_array($key, $this->known_keys[$section_type])) {
                            $status[$key] = $val;
                        }
                    }
                    break;
            }
        }
        fclose($fh);
    }

    public function dump()
    {
        print_r($this->status);
    }

    public static function parse($file)
    {
        $status = new IcingaStatus();
        $status->parseStatusFile($file);
        return $status;
    }
}
