<?php

namespace Cleantalk\Antispam\Integrations;

class ElfsightContactForm extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( isset($_POST['fields']) ) {
            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $_POST['fields']);

            return ct_get_fields_any($input_array);
        }

        return null;
    }

    public function doBlock($message)
    {
        header('Content-type: application/json; charset=utf-8');
        exit(json_encode(array(400, $message)));
    }
}
