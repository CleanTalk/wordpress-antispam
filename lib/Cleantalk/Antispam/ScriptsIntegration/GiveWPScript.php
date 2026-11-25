<?php

namespace Cleantalk\Antispam\ScriptsIntegration;

use Cleantalk\ApbctWP\BotDetectorService;

class GiveWPScript extends ScriptIntegrationPlugin
{
    public $hook_name = 'givewp_donation_form_enqueue_scripts';
    public $plugin_file = 'give/give.php';
    public $uri_chunk = 'givewp-route=donation-form-view';

    /**
     * @return void
     * @psalm-suppress InvalidArgument
     */
    public function integrate()
    {
        // Bot detector
        if ( apbct__is_bot_detector_enabled() && ! apbct_bot_detector_scripts_exclusion()) {
            // Attention! Skip old enqueue way for external script.
            wp_enqueue_script(
                'ct_bot_detector',
                BotDetectorService::getWrapperUrl(),
                [],
                APBCT_VERSION,
                array(
                    'in_footer' => true,
                    'strategy' => 'async'
                )
            );
        }
    }
}
