<?php

namespace Cleantalk\Antispam\Integrations;

class Wpdiscuz extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $_POST);

        if ( isset($input_array['wc_name'], $input_array['wc_email'], $input_array['wc_comment']) ) {
            return array(
                'message' => $input_array['wc_comment'],
                'email' => $input_array['wc_email'],
                'nickname' => $input_array['wc_name'],
                'sender_url' => isset($input_array['wc_website']) ? $input_array['wc_website'] : ''
            );
        }

        return ct_get_fields_any($input_array);
    }

    public function doBlock($message)
    {
        wp_send_json_error('wc_error_email_text');
    }
}
