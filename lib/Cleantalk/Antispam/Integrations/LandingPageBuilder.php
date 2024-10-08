<?php

namespace Cleantalk\Antispam\Integrations;

class LandingPageBuilder extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( ! empty($_POST) ) {
            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $_POST);

            return ct_get_fields_any($input_array);
        }

        return null;
    }

    public function doBlock($message)
    {
        $return = [];
        $return['Error']    = $message;
        $return['database'] = 'false';
        echo json_encode($return);
        exit;
    }
}
