<?php

namespace Cleantalk\Antispam\Integrations;

class WpBookingSystem extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( ! isset($_POST['form_data']) ) {
            return null;
        }
        $data = $_POST['form_data'];
        parse_str($_POST['form_data'], $data);
        $input_array = apply_filters('apbct__filter_post', $data);

        return ct_get_fields_any($input_array);
    }

    public function doBlock($message)
    {
        $out = array(
            'success' => false,
            'html' => $message
        );
        die(json_encode($out, JSON_FORCE_OBJECT));
    }
}
