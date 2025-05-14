<?php

namespace Cleantalk\ApbctWP\ServerChecker;

class ServerChecker
{
    private $requirements = [
        'php_version' => '5.6',
        'curl_support' => true,
        'allow_url_fopen' => true,
        'wp_memory_limit' => '128M',
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

        // Check cURL support
        if (!function_exists('curl_version')) {
            $this->warnings[] = __('cURL support is required', 'cleantalk-spam-protect');
        }

        // Check allow_url_fopen
        if (!ini_get('allow_url_fopen')) {
            $this->warnings[] = __('allow_url_fopen must be enabled', 'cleantalk-spam-protect');
        }

        // Check WP_MEMORY_LIMIT
        if (intval(ini_get('memory_limit')) < intval($this->requirements['wp_memory_limit'])) {
            $this->warnings[] = sprintf(
                __('WP_MEMORY_LIMIT must be at least %s', 'cleantalk-spam-protect'),
                $this->requirements['wp_memory_limit']
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
}
