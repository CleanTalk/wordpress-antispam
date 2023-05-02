<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Request;

class ModernEventsCalendar extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
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
