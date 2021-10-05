<?php

namespace Cleantalk\Antispam\Integrations;

class ContactBank extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( isset($_REQUEST['param']) ) {
            parse_str(isset($_REQUEST['data']) ? base64_decode($_REQUEST['data']) : '', $form_data);

            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $form_data);

            return ct_get_fields_any($input_array);
        }

        return null;
    }

    public function doBlock($message)
    {
        die(
            json_encode(
                array('apbct' => array('blocked' => true, 'comment' => $message,)),
                JSON_HEX_QUOT | JSON_HEX_TAG
            )
        );
    }
}
