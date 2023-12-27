<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class MailPoet extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        $data = null;

        if (apbct_is_plugin_active('mailpoet/mailpoet.php')
            && !empty($_POST)
            && Post::get('token')
            && Post::get('method') === 'subscribe'
        ) {
            $data = apply_filters('apbct__filter_post', $_POST);
            $data = ct_gfa($data);
        }

        return $data;
    }

    public function doBlock($message)
    {
        wp_send_json(
            array(
                'errors' => [
                    [
                        'error' => 'bad_request',
                        'message' => $message
                    ]
                ],
            ),
            403
        );
        die();
    }
}
