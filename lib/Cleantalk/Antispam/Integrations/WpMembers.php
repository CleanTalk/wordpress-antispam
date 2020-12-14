<?php


namespace Cleantalk\Antispam\Integrations;


class WpMembers extends IntegrationBase
{

    function getDataForChecking( $argument )
    {
        $data = ct_get_fields_any( $argument );
        $data['register'] = true;
        return $data;
    }

    function doBlock( $message )
    {
        global $wpmem_themsg;
        $wpmem_themsg = $message;
    }
}