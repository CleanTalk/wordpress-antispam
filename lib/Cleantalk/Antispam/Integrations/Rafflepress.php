<?php

namespace Cleantalk\Antispam\Integrations;

class Rafflepress extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        return ct_get_fields_any($_POST);
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
