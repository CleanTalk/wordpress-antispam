<?php

namespace Cleantalk\Antispam\Integrations;

class Forminator extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( ! empty($_POST) ) {
            return ct_get_fields_any($_POST);
        }

        return null;
    }

    public function doBlock($message)
    {
        wp_send_json_error(
            array(
                'message' => $message,
                'success' => false,
                'errors'  => array(),
                'behav'   => 'behaviour-thankyou',
            )
        );
    }
}
