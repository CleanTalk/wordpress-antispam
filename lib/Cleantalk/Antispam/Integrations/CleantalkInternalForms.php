<?php

namespace Cleantalk\Antispam\Integrations;

class CleantalkInternalForms extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( ! empty($_POST) ) {
            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $_POST);

            return ct_gfa($input_array);
        }

        return null;
    }

    public function doBlock($message)
    {
        wp_send_json_error(
            wp_kses(
                $message,
                array(
                    'a' => array(
                        'href'  => true,
                        'title' => true,
                    ),
                    'br'     => array(),
                    'p'     => array()
                )
            )
        );
    }

    public function allow()
    {
        wp_send_json_success();
    }
}
