<?php


namespace Cleantalk\Antispam\Integrations;


class WpMembers extends IntegrationBase
{

    function getDataForChecking( $argument )
    {
        return ct_get_fields_any( $argument );
    }

    function doBlock( $message )
    {
        global $wpmem_themsg;
        $wpmem_themsg = $message;
    }
}