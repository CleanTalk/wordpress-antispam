<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Request;
use Cleantalk\Common\TT;

class WordpressFileUpload extends IntegrationBase
{
    private $return_argument;

    public function getDataForChecking($argument)
    {
        $this->return_argument = $argument;
        if ( Request::get('userdata') && function_exists('wfu_plugin_decode_string') ) {
            $userdata = explode(";", TT::toString(Request::get('userdata')));
            $parsed_userdata = [];
            foreach ($userdata as $_user) {
                $parsed_userdata[] = strip_tags(wfu_plugin_decode_string(trim(substr($_user, 1))));
            }

            $input_array = apply_filters('apbct__filter_post', $parsed_userdata);

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
    }

    public function doBlock($message)
    {
        return ["error_message" => wp_strip_all_tags($message)];
    }

    public function allow()
    {
        return $this->return_argument;
    }
}
