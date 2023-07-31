<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class CleantalkRegisterWidget extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        return [
            'email' => Post::get('email'),
            'sender_url' => Post::get('current_url'),
        ];
    }

    public function doBlock($message)
    {
        wp_send_json_error($message);
    }
}
