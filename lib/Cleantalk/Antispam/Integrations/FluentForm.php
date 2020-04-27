<?php


namespace Cleantalk\Antispam\Integrations;


class FluentForm extends IntegrationBase
{

    function getDataForChecking( $argument )
    {
        if( isset( $_POST['data'] ) ) {
            parse_str( $_POST['data'], $form_data );
            return ct_get_fields_any($form_data);
        }
        return null;
    }

    function doBlock($message)
    {
        wp_send_json(
            array(
                'errors' => array(
                    'restricted' => array(
                        $message
                    )
                )
            ), 422
        );
    }
}