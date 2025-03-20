<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;

class SmartQuizBuilder extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        Cookie::$force_alt_cookies_global = true;
        apbct_form__get_no_cookie_data($_POST);
        $input_array = apply_filters('apbct__filter_post', $_POST);
        $input_array['event_token'] = Cookie::getString('ct_bot_detector_event_token');
        return ct_gfa_dto($input_array, $input_array['email'])->getArray();
    }

    public function doBlock($message)
    {
        echo json_encode(
            array(
                'apbct' => array(
                    'blocked'     => true,
                    'comment'     => $message,
                    'stop_script' => apbct__stop_script_after_ajax_checking()
                )
            )
        );
        die();
    }
}
