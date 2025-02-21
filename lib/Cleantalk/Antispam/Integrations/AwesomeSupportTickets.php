<?php

namespace Cleantalk\Antispam\Integrations;

class AwesomeSupportTickets extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        if ($apbct->settings['data__protect_logged_in'] == 1) {
            $email = wp_get_current_user() ? wp_get_current_user()->user_email : '';
            return ct_gfa_dto(apply_filters('apbct__filter_post', $_POST), $email)->getArray();
        }
    }

    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }
}
