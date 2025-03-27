<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\TT;

class EventsManager extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $_POST);

        return ct_gfa_dto($input_array)->getArray();
    }

    public function doBlock($message)
    {
        if ( Post::hasString('action', 'em_booking_validate_after') ) {
            if ( Get::get('callback') ) {
                $callback = htmlspecialchars(TT::toString(Get::get('callback')), ENT_QUOTES);
                $output = array(
                    'result' => false,
                    'message' => '',
                    'errors' => $message,
                );
                die($callback . '(' . json_encode($output) . ')');
            }
            return false;
        }

        // Events Manager Booking Form Integration
        if (
            Post::hasString('action', 'booking_add') ||
            Post::hasString('action', 'em_booking_add')
        ) {
            die(
                json_encode(
                    array(
                        'success' => false,
                        'result' => false,
                        'message' => $message,
                    )
                )
            );
        }
    }

    public function allow()
    {
        return true;
    }
}
