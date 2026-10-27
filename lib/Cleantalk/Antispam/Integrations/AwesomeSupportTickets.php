<?php

namespace Cleantalk\Antispam\Integrations;

class AwesomeSupportTickets extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        if ($apbct->settings['data__protect_logged_in'] == 1) {
            $email = wp_get_current_user() ? wp_get_current_user()->user_email : '';
            // The reply data is passed by the plugin
            $input_array = is_array($argument) ? $argument : $_POST;
            return ct_gfa_dto(apply_filters('apbct__filter_post', $input_array), $email)->getArray();
        }
    }

    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }
}
