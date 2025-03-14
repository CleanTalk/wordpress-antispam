<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Cookie;

class UserRegistrationPro extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        $ct_post_temp = apply_filters('apbct__filter_post', $_POST);
        if (isset($ct_post_temp['form_data']) && is_string($ct_post_temp['form_data'])) {
            $decoded = json_decode($ct_post_temp['form_data'], true);
            $ct_post_temp['form_data'] = $decoded !== null ? $decoded : $ct_post_temp['form_data'];
        }
        $data             = ct_gfa($ct_post_temp);
        $data['register'] = true;

        Cookie::$force_alt_cookies_global = true;
        $data['event_token'] = Cookie::get('ct_bot_detector_event_token');

        return $data;
    }

    /**
     * @param $message
     *
     * @psalm-suppress UnusedVariable
     */
    public function doBlock($message)
    {
        wp_send_json_error(
            array(
                'message' => $message
            )
        );
        die();
    }
}
