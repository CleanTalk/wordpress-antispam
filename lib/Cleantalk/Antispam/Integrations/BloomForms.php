<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Cookie;

class BloomForms extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        Cookie::$force_alt_cookies_global = true;

        return ct_gfa(apply_filters('apbct__filter_post', $_POST));
    }

    public function doBlock($message)
    {
        $data = [
            'error' => $message,
        ];

        wp_send_json($data);
    }
}
