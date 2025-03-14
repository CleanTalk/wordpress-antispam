<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Cookie;

class BackInStockNotifier extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        Cookie::$force_alt_cookies_global = true;

        $email = isset($_POST['user_email']) && is_string($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';
        $nickname = isset($_POST['subscriber_name']) && is_string($_POST['subscriber_name']) ? sanitize_text_field($_POST['subscriber_name']) : '';
        $data = array();
        if ( empty($email) || empty($nickname) ) {
            $input_array = apply_filters('apbct__filter_post', $_POST);
            $data = ct_get_fields_any($input_array, is_string($email) ? $email : '', is_array($nickname) ? $nickname : array());
        } else {
            $data['email'] = $email;
            $data['nickname'] = $nickname;
        }

        $data['event_token'] = Cookie::get('ct_bot_detector_event_token');
        $data['sender_info']['form_without_tags'] = true;

        return $data;
    }

    public function doBlock($message)
    {
        wp_send_json(['msg' => "<div class='cwginstockerror' style='color:red;'>" . $message . "</div"]);
    }
}
