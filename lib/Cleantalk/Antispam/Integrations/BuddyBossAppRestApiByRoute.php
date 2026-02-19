<?php

namespace Cleantalk\Antispam\Integrations;

class BuddyBossAppRestApiByRoute extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if (
            apbct_is_plugin_active('buddyboss-app/buddyboss-app.php')
        ) {
            return ct_gfa(apply_filters('apbct__filter_post', $_POST));
        }
        return null;
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
