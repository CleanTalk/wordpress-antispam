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
        'curl_multi_funcs_array' => true,
    ];

    public $curl_multi_funcs_array = array(
        'curl_multi_exec',
        'curl_multi_init',
        'curl_multi_getcontent',
        'curl_multi_add_handle',
    );

    public $requirement_items = [
        'php_version' => [
            'label' => 'PHP version: %s+',
            'pattern' => 'PHP version',
        ],
        'curl_support' => [
            'label' => 'cURL support: %s',
            'pattern' => 'cURL support',
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
        'curl_multi_funcs_array' => [
            'label' => 'cURL multi-request functions: %s',
            'pattern' => 'At least one of cURL multiple request',
        ],
    ];

    private $warnings = [];

    /**
     * This wrapper allow to generate a mock for the testing
     * @param string|string[] $function_names
     * @return bool
     */
    protected function functionsExist($function_names)
    {
        if ( is_string($function_names)) {
            $function_names = array($function_names);
        }

        foreach ( $function_names as $function) {
            if (!function_exists($function)) {
                return false;
            }
        }
        return true;
    }

    /**
     * This wrapper allow to generate a mock for the testing
     *
     * @param string|string[] $function_names
     *
     * @return bool
     */
    protected function functionsCallable($function_names)
    {
        if ( is_string($function_names)) {
            $function_names = array($function_names);
        }

        foreach ( $function_names as $function) {
            if (!is_callable($function)) {
                return false;
            }
        }
        return true;
    }

    /**
     * This wrapper allow to generate a mock for the testing
     * @param string $setting
     * @return string
     */
    protected function getIniValue($setting)
    {
        return ini_get($setting);
    }

    /**
     * Get existing parameter value.
     *
     * @param string $param_name
     *
     * @return bool|string|void
     */
    public function getRequiredParameterValue($param_name)
    {
        if ( array_key_exists($param_name, $this->requirement_items) ) {
            switch ($param_name) {
                case 'php_version':
                    return PHP_VERSION;
                case 'curl_support':
                    return $this->functionsExist('curl_version');
                case 'allow_url_fopen':
                    return $this->getIniValue('allow_url_fopen');
                case 'memory_limit':
                    return $this->getIniValue('memory_limit');
                case 'max_execution_time':
                    return $this->getIniValue('max_execution_time');
                case 'curl_multi_funcs_array':
                    return $this->functionsExist($this->curl_multi_funcs_array) && $this->functionsCallable($this->curl_multi_funcs_array);
            }
        }
    }

    /**
     * Check server requirements for the plugin.
     * @return array|null
     * @psalm-suppress InvalidScalarArgument
     */
    public function checkRequirements()
    {
        // Check PHP version
        if (version_compare($this->getRequiredParameterValue('php_version'), $this->requirements['php_version'], '<')) {
            $this->warnings[] = sprintf(
                __('PHP version must be at least %s, current is %s', 'cleantalk-spam-protect'),
                $this->requirements['php_version'],
                esc_html(PHP_VERSION)
            );
        }

        $curl_available = $this->getRequiredParameterValue('curl_support');
        if (!$curl_available) {
            $this->warnings[] = __('cURL support is required', 'cleantalk-spam-protect');
            // If cURL is not available, check allow_url_fopen
            if (!$this->getRequiredParameterValue('allow_url_fopen')) {
                $this->warnings[] = __('allow_url_fopen must be enabled if cURL is not available', 'cleantalk-spam-protect');
            }
        }

        $current_memory_limit = $this->getRequiredParameterValue('memory_limit');
        $is_memory_unset = -1 == $current_memory_limit;
        $current_max_exec_time = $this->getRequiredParameterValue('max_execution_time');
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

        if ( ! $this->getRequiredParameterValue('curl_multi_funcs_array') ) {
            $funcs_list = esc_html(implode(', ', $this->curl_multi_funcs_array));
            $template = __('At least one of cURL multiple request functions (%s) is not available, but required for SpamFireWall features', 'cleantalk-spam-protect');
            $this->warnings[] = sprintf($template, $funcs_list);
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
