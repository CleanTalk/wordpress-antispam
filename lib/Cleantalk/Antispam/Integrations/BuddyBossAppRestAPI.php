<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Server;

class BuddyBossAppRestAPI extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if (
            apbct_is_plugin_active('buddyboss-app/buddyboss-app.php') &&
            Server::getString('REQUEST_URI') === '/wp-json/buddyboss-app/v1/signup'
        ) {
            return ct_gfa(apply_filters('apbct__filter_post', $_POST));
        }
    }

    public function doBlock($message)
    {
        $data = [
            'code' => 'bp_rest_register_errors',
            'message' => [
                'signup_email' => $message
            ],
            'data' => [
                'status' => 403,
            ],
        ];
        wp_send_json($data);
    }
}
