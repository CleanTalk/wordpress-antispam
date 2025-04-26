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

        $nickname = '';
        if ( isset($_POST['fields']['name']) && is_string($_POST['fields']['name']) ) {
            $nickname .= sanitize_text_field($_POST['fields']['name']);
        }
        if ( isset($_POST['fields']['first_name']) && is_string($_POST['fields']['first_name']) ) {
            $nickname .= ! empty($nickname) ? ' ' : '';
            $nickname .= sanitize_text_field($_POST['fields']['first_name']);
        }
        if ( isset($_POST['fields']['last_name']) && is_string($_POST['fields']['last_name']) ) {
            $nickname .= ! empty($nickname) ? ' ' : '';
            $nickname .= sanitize_text_field($_POST['fields']['last_name']);
        }

        return ct_gfa($input_array, '', $nickname);
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
