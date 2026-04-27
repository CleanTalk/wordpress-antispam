<?php

namespace Cleantalk\Antispam\ScriptsIntegration;

class FluentFormScript extends ScriptIntegrationPlugin
{
    public $hook_name = 'wp_head';
    public $plugin_file = 'fluentformpro/fluentformpro.php';
    public $uri_chunk = 'ff_landing=';

    public function integrate()
    {
        echo '<script data-pagespeed-no-defer="" src="'
            . APBCT_URL_PATH
            . '/js/apbct-public-bundle.min.js'
            . '?ver=' . APBCT_VERSION . '" id="ct_public_functions-js"></script>';
        echo '<script src="' . APBCT_BOT_DETECTOR_SCRIPT_URL . '?ver='
            . APBCT_VERSION . '" async id="ct_bot_detector-js" data-wp-strategy="async"></script>';
    }

    public function additionalChecks()
    {
        return $this->is_in_uri || (
                function_exists('apbct_is_user_logged_in') &&
                apbct_is_user_logged_in() &&
                (defined('APBCT_FF_JS_SCRIPTS_LOAD') && APBCT_FF_JS_SCRIPTS_LOAD == true)
            );
    }
}
