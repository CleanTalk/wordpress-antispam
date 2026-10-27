<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Request;
use Cleantalk\Common\TT;

class EventsManager extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        /**
         * Filter for POST
         * Events Manager reads the booking fields from the request
         */
        $input_array = apply_filters('apbct__filter_post', $_REQUEST);

        return ct_gfa_dto($input_array, Request::getString('user_email'))->getArray();
    }

    public function doBlock($message)
    {
        if ( Request::hasString('action', 'em_booking_validate_after') ) {
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
            Request::hasString('action', 'booking_add') ||
            Request::hasString('action', 'em_booking_add')
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
