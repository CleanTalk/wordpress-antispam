<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Request;

class ModernEventsCalendar extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        if ( $apbct->settings['data__protect_logged_in'] == 0 && is_user_logged_in() ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);
            return null;
        }

        if ( Request::get('book') && Request::get('step') == 2 ) {
            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', Request::get('book'));

            return ct_gfa($input_array);
        }

        return null;
    }

    public function doBlock($message)
    {
        $output = array('success' => 0, 'message' => $message);
        wp_send_json($output);
    }
}
