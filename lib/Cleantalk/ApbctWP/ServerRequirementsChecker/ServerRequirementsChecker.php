<?php

namespace Cleantalk\ApbctWP\ServerRequirementsChecker;

class ServerRequirementsChecker
{
    public $requirements = [
        'php_version' => '5.6',
        'curl_support' => true,
        'allow_url_fopen' => true,
        'memory_limit' => '128M',
        'max_execution_time' => 30,
    ];

    public $requirement_items = [
        'php_version' => [
            'label' => 'PHP version: %s+',
            'pattern' => 'PHP version',
        ],
        'curl_support' => [
            'label' => 'cURL support: %s',
            'pattern' => 'cURL',
        ],
        'allow_url_fopen' => [
            'label' => 'allow_url_fopen: %s',
            'pattern' => 'allow_url_fopen',
        ],
        'memory_limit' => [
            'label' => 'PHP memory_limit: %s+',
            'pattern' => 'memory_limit',
        ],
        'max_execution_time' => [
            'label' => 'max_execution_time: %s+ seconds',
            'pattern' => 'max_execution_time',
        ],
    ];

    private $warnings = [];

    /**
     * Check server requirements for the plugin.
     * @return array|null
     */
    public function checkRequirements()
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, $this->requirements['php_version'], '<')) {
            $this->warnings[] = sprintf(
                __('PHP version must be at least %s, current is %s', 'cleantalk-spam-protect'),
                $this->requirements['php_version'],
                esc_html(PHP_VERSION)
            );
        }

        $curl_available = function_exists('curl_version');
        if (!$curl_available) {
            $this->warnings[] = __('cURL support is required', 'cleantalk-spam-protect');
            // If cURL is not available, check allow_url_fopen
            if (!ini_get('allow_url_fopen')) {
                $this->warnings[] = __('allow_url_fopen must be enabled if cURL is not available', 'cleantalk-spam-protect');
            }
        }

        $current_memory_limit = ini_get('memory_limit');
        $is_memory_unset = -1 == $current_memory_limit;
        $current_max_exec_time = ini_get('max_execution_time');
        $is_exec_time_unset = 0 == $current_max_exec_time;

        // Check memory_limit
        $current_limit = $this->normalizeMemoryLimit($current_memory_limit);
        $required_limit = $this->normalizeMemoryLimit($this->requirements['memory_limit']);
        if (!$is_memory_unset && $current_limit < $required_limit) {
            $this->warnings[] = sprintf(
                __('PHP memory_limit must be at least %s, ini_get() returns %s', 'cleantalk-spam-protect'),
                $this->requirements['memory_limit'],
                esc_html($current_memory_limit)
            );
        }

        // Check max_execution_time
        if (!$is_exec_time_unset && intval($current_max_exec_time) < intval($this->requirements['max_execution_time'])) {
            $this->warnings[] = sprintf(
                __('max_execution_time must be at least %d seconds, ini_get() returns %s', 'cleantalk-spam-protect'),
                $this->requirements['max_execution_time'],
                esc_html($current_max_exec_time)
            );
        }

        if (empty($this->warnings)) {
            return null;
        }

        return $this->warnings;
    }

    /**
     * Normalize memory limit to workable int/float.
     * @param $val
     *
     * @return float|int
     */
    private function normalizeMemoryLimit($val)
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (int)$val;
        switch ($last) {
            case 'g':
                $val *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $val *= 1024 * 1024;
                break;
            case 'k':
                $val *= 1024;
                break;
        }
        return $val;
    }
}
