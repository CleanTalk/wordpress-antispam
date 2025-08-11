<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Cookie;

class BookingPress extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        Cookie::$force_alt_cookies_global = true;
        return ct_gfa(apply_filters('apbct__filter_post', $_POST));
    }

    public function doBlock($message)
    {
        wp_send_json([
            'variant'  => 'error',
            'title'  => 'Error',
            'msg' => $message
        ], 403);
    }
}
