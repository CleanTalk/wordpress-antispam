<?php

namespace Cleantalk\Antispam\Integrations;

class WordpressFileUpload extends IntegrationBase
{
    private $return_argument;

    public function getDataForChecking($argument)
    {
        $this->return_argument = $argument;

        // Skip forms without the user data fields
        if (
            ! is_array($argument) ||
            ! empty($argument['error_message']) ||
            empty($argument['user_data']) ||
            ! is_array($argument['user_data'])
        ) {
            return null;
        }

        // The user data the plugin is going to save
        $userdata = [];
        foreach ( $argument['user_data'] as $key => $field ) {
            if ( isset($field['value']) && is_string($field['value']) ) {
                $label = isset($field['label']) && is_string($field['label']) && $field['label'] !== ''
                    ? $field['label']
                    : 'userdata_' . $key;
                $userdata[$label] = $field['value'];
            }
        }

        $input_array = apply_filters('apbct__filter_post', $userdata);

        $data = ct_gfa($input_array);

        if ( isset($_REQUEST['data']['ct_bot_detector_event_token']) ) {
            $data['event_token'] = $_REQUEST['data']['ct_bot_detector_event_token'];
        }
        if ( isset($_REQUEST['data']['ct_no_cookie_hidden_field']) ) {
            $data['ct_no_cookie_hidden_field'] = $_REQUEST['data']['ct_no_cookie_hidden_field'];
        }

        unset($_REQUEST['data']);

        return $data;
    }

    public function doBlock($message)
    {
        $this->return_argument['error_message'] = wp_strip_all_tags($message);

        return $this->return_argument;
    }

    public function allow()
    {
        return $this->return_argument;
    }
}
