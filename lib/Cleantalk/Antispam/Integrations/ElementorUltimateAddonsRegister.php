<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Cookie;

class ElementorUltimateAddonsRegister extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        Cookie::$force_alt_cookies_global = true;

        $input_array = apply_filters('apbct__filter_post', $_POST);
        $data = ct_get_fields_any($input_array);

        $data['event_token'] = Cookie::get('ct_bot_detector_event_token');
        $data['register'] = true;

        return $data;
    }

    public function doBlock($message)
    {
        wp_send_json(
            array('success' => false,
                'error'   => array(
                    'email' => $message
                )
            )
        );
        return false;
    }

    public function allow()
    {
        return true;
    }
}
