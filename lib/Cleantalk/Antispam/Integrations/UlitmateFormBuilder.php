<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class UlitmateFormBuilder extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        $ct_post_temp = apply_filters('apbct__filter_post', $_POST);

        $direct_no_cookie_data = null;
        $event_token = '';

        //message clearance
        if ( !empty($ct_post_temp['form_data']) && is_array($ct_post_temp['form_data']) ) {
            foreach ( $ct_post_temp['form_data'] as $_key => $value ) {
                //parse nocookie data
                if ( isset($value['name'], $value['value']) && $value['name'] === 'ct_no_cookie_hidden_field' ) {
                    unset($ct_post_temp['form_data'][$_key]);
                    // prepare POST data to get parameters
                    $direct_no_cookie_data[$value['name']] = $value['value'];
                }
                //apbct_visible_fields
                if ( isset($value['name']) && $value['name'] === 'apbct_visible_fields' ) {
                    unset($ct_post_temp['form_data'][$_key]);
                }

                if ( isset($value['name'], $value['value']) && $value['name'] === 'ct_bot_detector_event_token' ) {
                    $event_token = $value['value'];
                    unset($ct_post_temp['form_data'][$_key]);
                }
                //ct_bot_detector_event_token
            }
        }

        if ( ! $apbct->stats['no_cookie_data_taken'] ) {
            apbct_form__get_no_cookie_data($direct_no_cookie_data);
        }

        //unset action
        if ( isset($ct_post_temp['action']) ) {
            unset($ct_post_temp['action']);
        }

        $output = ct_gfa($ct_post_temp);

        if ( isset($output['message']) ) {
            $reformatted_message = array();
            foreach ( $output['message'] as $key => $_value ) {
                if ( preg_match('/form_data_\d_name/', (string)$key) ) {
                    /**
                    * replace keys<=>values
                    */
                    $new_value_index = str_replace('_name', '_value', $key);
                    if ( !empty($output['message'][$key]) ) {
                        $new_key_index = $output['message'][$key];
                        if ( isset($output['message'][$new_value_index]) ) {
                            //glue the same index values
                            $reformatted_message[$new_key_index][] = $output['message'][$new_value_index];
                        }
                    }
                }
            }
            $output['message'] = $reformatted_message;
        }

        if ( ! empty($event_token) ) {
            $output['event_token'] = $event_token;
        }

        return $output;
    }

    /**
     * @param $message
     *
     * @return void
     */
    public function doBlock($message)
    {
        $result = array(
            'error_keys'       => array(),
            'error_flag'       => 1,
            'response_message' => $message
        );
        print json_encode($result);
        die();
    }
}
