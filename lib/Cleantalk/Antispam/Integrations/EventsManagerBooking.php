<?php

namespace Cleantalk\Antispam\Integrations;

class EventsManagerBooking extends IntegrationBase
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

    public function allow()
    {
        return true;
    }
}
