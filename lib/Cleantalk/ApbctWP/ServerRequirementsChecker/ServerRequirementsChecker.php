<?php

namespace Cleantalk\ApbctWP\ServerRequirementsChecker;

class ServerRequirementsChecker
{
    private $requirements = [
        'php_version' => '5.6',
        'curl_support' => true,
        'allow_url_fopen' => true,
        'memory_limit' => '128M',
        'max_execution_time' => 30,
    ];

    private $warnings = [];

    public function checkRequirements()
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, $this->requirements['php_version'], '<')) {
            $this->warnings[] = sprintf(
                __('PHP version must be at least %s', 'cleantalk-spam-protect'),
                $this->requirements['php_version']
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

        // Check memory_limit
        $current_limit = $this->normalizeMemoryLimit(ini_get('memory_limit'));
        $required_limit = $this->normalizeMemoryLimit($this->requirements['memory_limit']);
        if ($current_limit < $required_limit) {
            $this->warnings[] = sprintf(
                __('PHP memory_limit must be at least %s', 'cleantalk-spam-protect'),
                $this->requirements['memory_limit']
            );
        }

        // Check max_execution_time
        if (intval(ini_get('max_execution_time')) < intval($this->requirements['max_execution_time'])) {
            $this->warnings[] = sprintf(
                __('max_execution_time must be at least %d seconds', 'cleantalk-spam-protect'),
                $this->requirements['max_execution_time']
            );
        }

        if (empty($this->warnings)) {
            return;
        }

        return $this->warnings;
    }

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
