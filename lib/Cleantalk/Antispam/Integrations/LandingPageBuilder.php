<?php

namespace Cleantalk\Antispam\Integrations;

class LandingPageBuilder extends IntegrationBase
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
        $return['Error']    = $message;
        $return['database'] = 'false';
        echo json_encode($return);
        exit;
    }
}
