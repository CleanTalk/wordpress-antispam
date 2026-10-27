<?php

namespace Cleantalk\Antispam\Integrations;

class BackInStockNotifier extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        // The subscription data validated by the plugin, the same for the AJAX and REST API requests
        $post_data = is_array($argument) ? $argument : $_POST;
        $email = isset($post_data['user_email']) && is_string($post_data['user_email']) ? sanitize_email($post_data['user_email']) : '';
        $nickname = isset($post_data['subscriber_name']) && is_string($post_data['subscriber_name']) ? sanitize_text_field($post_data['subscriber_name']) : '';
        $data = array();
        if ( empty($email) || empty($nickname) ) {
            $input_array = apply_filters('apbct__filter_post', $post_data);
            $data = ct_get_fields_any($input_array, is_string($email) ? $email : '', is_array($nickname) ? $nickname : array());
        } else {
            $data['email'] = $email;
            $data['nickname'] = $nickname;
        }

        if ( isset($_REQUEST['data']['ct_bot_detector_event_token']) ) {
            $data['event_token'] = $_REQUEST['data']['ct_bot_detector_event_token'];
            unset($_REQUEST['data']['ct_bot_detector_event_token']);
        }

        $data['sender_info']['form_without_tags'] = true;

        return $data;
    }

    public function doBlock($message)
    {
        wp_send_json(['msg' => "<div class='cwginstockerror' style='color:red;'>" . $message . "</div"]);
    }
}
