<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\Common\TT;
use Cleantalk\Variables\Post;

class WpBookingSystem extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        $event_token = '';

        if ( ! Post::get('form_data') ) {
            return null;
        }
        parse_str(TT::toString(Post::get('form_data')), $data);
        $input_array = apply_filters('apbct__filter_post', $data);

        if ( ! $apbct->stats['no_cookie_data_taken'] ) {
            apbct_form__get_no_cookie_data($data);
        }

        if ( isset($data['ct_bot_detector_event_token']) ) {
            $event_token = $data['ct_bot_detector_event_token'];
        }

        $data_for_checking = ct_gfa_dto($input_array)->getArray();
        if ( ! empty($event_token) ) {
            $data_for_checking['event_token'] = $event_token;
        }

        return $data_for_checking;
    }

    public function doBlock($message)
    {
        $out = array(
            'success' => false,
            'html' => $message
        );
        die(json_encode($out, JSON_FORCE_OBJECT));
    }
}
