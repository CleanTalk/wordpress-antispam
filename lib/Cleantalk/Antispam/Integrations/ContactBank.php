<?php


namespace Cleantalk\Antispam\Integrations;


class ContactBank extends IntegrationBase
{

    function getDataForChecking( $argument )
    {
        if( isset( $_REQUEST['param'] ) ) {
            parse_str( isset( $_REQUEST['data'] ) ? base64_decode( $_REQUEST['data'] ) : '', $form_data );
            return ct_get_fields_any($form_data);
        }
        return null;
    }

    function doBlock( $message )
    {
        die(json_encode(array('apbct' => array('blocked' => true, 'comment' => $message,))));
    }
}