<?php

namespace Cleantalk\Antispam\Integrations;

class Rafflepress extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $_POST);

        return ct_get_fields_any($input_array);
    }

    public function doBlock($message)
    {
        wp_send_json(
            array(
                'status' => false,
                'errors' => $message,
                'contestant' => array(),
            )
        );
    }
}
