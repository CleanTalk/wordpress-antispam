<?php

namespace Cleantalk\Antispam\ScriptsIntegration;

use Cleantalk\ApbctWP\BotDetectorService;

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
        $wrapper_url = add_query_arg('ver', APBCT_VERSION, BotDetectorService::getWrapperUrl());
        echo '<script src="' . esc_url($wrapper_url) . '" async id="ct_bot_detector-js" data-wp-strategy="async"></script>';
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
