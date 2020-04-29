<?php


namespace Cleantalk\Antispam\Integrations;


class ElfsightContactForm extends IntegrationBase
{

    function getDataForChecking( $argument )
    {
        if( isset( $_POST ) && isset( $_POST['fields'] ) ) {
            return ct_get_fields_any( $_POST['fields'] );
        }
        return null;
    }

    function doBlock( $message )
    {
        header( 'Content-type: application/json; charset=utf-8' );
        exit( json_encode( array( 400, $message ) ) );
    }
}