<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Request;

class FluentBookingPro extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        // Fluent Booking reads the booking fields from the request
        if ( Request::get('action') === 'fluent_cal_schedule_meeting' ) {
            $input_array = apply_filters('apbct__filter_post', $_REQUEST);
            $data_to_spam_check = ct_gfa($input_array, Request::getString('email'));

            // It is a service field. Need to be deleted before the processing.
            if ( isset($input_array['apbct_visible_fields']) ) {
                unset($input_array['apbct_visible_fields']);
            }
            return $data_to_spam_check;
        }

        return null;
    }

    public function doBlock($message)
    {
        wp_send_json_error([
            'status'  => 'failed',
            'message' => $message
        ], 422);
    }
}
