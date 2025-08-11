<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class Wpdiscuz extends IntegrationBase
{
    public function getDataForChecking($argument)
    {

        $event_token = Post::get('ct_bot_detector_event_token') ? Post::get('ct_bot_detector_event_token') : null;

        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $_POST);

        $gfa_checked_data =  ct_get_fields_any($input_array);

        $gfa_checked_data['event_token'] = $event_token;

        if (isset($input_array['wc_comment'])) {
            $gfa_checked_data['message'] = $input_array['wc_comment'];
        }

        if (isset($input_array['wc_email'])) {
            $gfa_checked_data['email'] = $input_array['wc_email'];
        }

        if (isset($input_array['wc_name'])) {
            $gfa_checked_data['nickname'] = $input_array['wc_name'];
        }

        if (isset($input_array['wc_website'])) {
            $gfa_checked_data['sender_url'] = $input_array['wc_website'];
        }


        return $gfa_checked_data;
    }

    public function doBlock($message)
    {
        wp_send_json_error('wc_error_email_text');
    }
}
