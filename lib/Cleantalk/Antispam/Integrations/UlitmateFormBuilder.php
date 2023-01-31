<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class UlitmateFormBuilder extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        $ct_post_temp = $_POST;

        $direct_no_cookie_data = null;

        //message clearance
        if ( isset($ct_post_temp['form_data']) && is_array($ct_post_temp['form_data']) && !empty($ct_post_temp['form_data']) ) {
            foreach ( $ct_post_temp['form_data'] as $_key => $value ) {
                //parse nocookie data
                if ( isset($value['name']) && $value['name'] === 'ct_no_cookie_hidden_field' ) {
                    unset($ct_post_temp['form_data'][$_key]);
                }
                // prepare POST data to get parameters
                $direct_no_cookie_data[$value['name']] = $value['value'];
            }
        }

        if ( ! $apbct->stats['no_cookie_data_taken'] ) {
            apbct_form__get_no_cookie_data($direct_no_cookie_data);
        }

        if ( $direct_no_cookie_data ) {
            add_filter('apbct_preprocess_post_to_vf_check', function () use ($direct_no_cookie_data) {
                return $direct_no_cookie_data;
            });
        }

        //unset action
        if ( isset($ct_post_temp['action']) ) {
            unset($ct_post_temp['action']);
        }

        foreach ( $ct_post_temp as $key => $_value ) {
            if ( preg_match('/form_data_\d_name/', (string)$key) ) {
                unset($ct_post_temp[$key]);
            }
        }

        return ct_gfa($ct_post_temp);
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
