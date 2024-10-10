<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class TourMasterOrder extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        if (
            apbct_is_plugin_active('tourmaster/tourmaster.php') &&
            !empty($_POST) &&
            Post::get('action') === 'tourmaster_payment_template' &&
            Post::get('booking_detail')
        ) {
           /**
             * Filter for POST
             */
            $booking_detail = Post::get('booking_detail');
            $input_array = apply_filters('apbct__filter_post', $booking_detail);
            $data = ct_gfa($input_array);

            if ( isset($booking_detail['ct_bot_detector_event_token']) ) {
                $data['event_token'] = $booking_detail['ct_bot_detector_event_token'];
            }

            return $data;
        }

        return null;
    }

    public function doBlock($message)
    {
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked'     => true,
                        'comment'     => $message,
                        'stop_script' => apbct__stop_script_after_ajax_checking()
                    )
                )
            )
        );
    }
}
