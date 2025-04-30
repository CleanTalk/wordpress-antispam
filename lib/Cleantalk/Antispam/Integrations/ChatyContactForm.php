<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Request;
use Cleantalk\ApbctWP\Variables\Post;

class ChatyContactForm extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( Request::get('nonce') ) {
            $event_token = Post::getString('ct_bot_detector_event_token');
            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $_POST);

            unset($input_array['apbct_visible_fields']);

            $base_call_data = ct_gfa_dto($input_array)->getArray();

            $base_call_data['event_token'] = $event_token;

            if (isset($base_call_data['message']['message'])) {
                $base_call_data['message'] = $base_call_data['message']['message'];
            }

            return $base_call_data;
        }

        return null;
    }

    public function doBlock($message)
    {
        $response = [
            'status'  => 0,
            'error'   => '',
            'errors'  => [],
            'message' => $message,
        ];
        wp_send_json($response);
        exit;
    }
}
