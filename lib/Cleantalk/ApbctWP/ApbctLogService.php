<?php

namespace Cleantalk\ApbctWP;

class ApbctLogService
{
    private $callback;
    private $hook_name;
    private $logs_dir;
    private $custom_var_id = 'unset';
    public function prepareData($var_to_log, $custom_var_id = null)
    {
        if (isset($custom_var_id)) {
            $this->custom_var_id = $custom_var_id;
        }
        do_action($this->hook_name, $var_to_log);
    }

    public function enableLogging()
    {
        add_action($this->hook_name, function($var_to_log) {
            $log_entry = call_user_func($this->callback, $var_to_log);
            if (!empty($log_entry) && is_string($log_entry)) {
                $this->write($log_entry);
            }
        },1,1);
    }

    private function write($line_to_log)
    {
        $template = "[%s]CleanTalk Debug: action[%s], variable[%s]:\r\n%s";
        $line_to_log     = sprintf($template, date('Y-m-d H:i:s'), $this->hook_name, $this->custom_var_id, $line_to_log);
        $file_name = sprintf('%s/%s.log', $this->logs_dir, $this->hook_name);
        @file_put_contents($file_name, $line_to_log . "\r\n", FILE_APPEND);
    }

    public function __construct($hook_name, $callback)
    {
        $this->hook_name = $hook_name;
        $this->callback = $callback;
        $this->logs_dir = wp_get_upload_dir()['basedir'] . '/cleantalk_logs';
        if (!is_dir($this->logs_dir)) {
            @mkdir($this->logs_dir, 0755, true);
            @file_put_contents($this->logs_dir . '/index.php', "<?php\n// Silence is golden.\n");
        }
    }
}
