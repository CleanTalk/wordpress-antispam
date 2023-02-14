<?php

namespace Cleantalk\Antispam\Integrations;

class Supsystic extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( ! apbct_is_plugin_active('contact-form-by-supsystic/cfs.php') ) {
            return null;
        }
        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $_POST);

        return ct_get_fields_any($input_array);
    }

    public function doBlock($message)
    {
        $out = [
            'error' => true,
            'errors' => [
                $message
            ]
        ];
        die(json_encode($out, JSON_FORCE_OBJECT));
    }
}
