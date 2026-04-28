<?php

namespace Cleantalk\Antispam\Integrations;

class BloomForms extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        $filtered_post = apply_filters('apbct__filter_post', $_POST);
        $nickname = '';

        // Extract nickname from subscribe_data_array if it exists
        if ( ! empty($_POST['subscribe_data_array']) && is_string($_POST['subscribe_data_array']) ) {
            // Remove escaping from quoted JSON
            $data_to_decode = wp_unslash($_POST['subscribe_data_array']);
            if ( is_string($data_to_decode) ) {
                $subscribe_data = json_decode($data_to_decode, true);
                if ( is_array($subscribe_data) && ! empty($subscribe_data['name']) ) {
                    $nickname = sanitize_text_field($subscribe_data['name']);
                }
            }
        }

        return ct_gfa_dto($filtered_post, '', $nickname)->getArray();
    }

    public function doBlock($message)
    {
        $data = [
            'error' => $message,
        ];

        wp_send_json($data);
    }
}
