<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\Common\TT;

class WpBookingSystem extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        $event_token = '';

        // Do not change this $_POST to Post::get()
        if ( isset($_POST['form_data']) && is_string($_POST['form_data']) ) {
            // Do not change this $_POST to Post::get()
            parse_str($_POST['form_data'], $data);
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

        return null;
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
