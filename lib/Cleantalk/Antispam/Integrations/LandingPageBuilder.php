<?php


namespace Cleantalk\Antispam\Integrations;


class LandingPageBuilder extends IntegrationBase
{

    function getDataForChecking( $argument )
    {
        if( isset( $_POST ) ) {
            return ct_get_fields_any( $_POST );
        }
        return null;
    }

    function doBlock( $message )
    {
        $return['Error'] = $message;
        $return['database'] = 'false';
        echo json_encode( $return );
        exit;
    }
}