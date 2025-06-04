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
        $email = $processed_post['booked_appt_email'];
        return ct_gfa_dto($processed_post, $email, $fullname)->getArray();
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
