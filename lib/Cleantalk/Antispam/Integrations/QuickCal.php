<?php

namespace Cleantalk\Antispam\Integrations;

class QuickCal extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        $processed_post = apply_filters('apbct__filter_post', $_POST);
        $name = esc_attr($processed_post['booked_appt_name']);
        $surname = ( isset($processed_post['booked_appt_surname']) && $processed_post['booked_appt_surname'] ? esc_attr($processed_post['booked_appt_surname']) : false );
        $fullname = ( $surname ? $name . ' ' . $surname : $name );
        /** @psalm-suppress PossiblyUndefinedStringArrayOffset */
        $email = $processed_post['booked_appt_email'];
        $data = ct_gfa_dto($processed_post, $email, $fullname)->getArray();

        if ( isset($_REQUEST['data']['ct_bot_detector_event_token']) ) {
            $data['event_token'] = $_REQUEST['data']['ct_bot_detector_event_token'];
        }
        if ( isset($_REQUEST['data']['ct_no_cookie_hidden_field']) ) {
            $data['ct_no_cookie_hidden_field'] = $_REQUEST['data']['ct_no_cookie_hidden_field'];
        }

        unset($_REQUEST['data']);

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        echo 'error###' . $message;
        die();
    }
}
