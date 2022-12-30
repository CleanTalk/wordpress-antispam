<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class UlitmateFormBuilder extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;
        if ( Post::get('action') === 'ufbl_front_form_action') {
            $ct_post_temp = $_POST;

            //message clearance
            if ( isset($ct_post_temp['form_data']) && is_array($ct_post_temp['form_data']) && !empty($ct_post_temp['form_data']) ) {
                foreach ( $ct_post_temp['form_data'] as $_key => $value ) {
                    //parse nocookie data
                    if ( isset($value['name']) && $value['name'] === 'ct_no_cookie_hidden_field' ) {
                        $direct_no_cookie_data = $value['value'];
                        unset($ct_post_temp['form_data'][$_key]);
                    }
                    //unset apbct_visible_fields
                    if ( isset($value['name']) && $value['name'] === 'apbct_visible_fields' ) {
                        unset($ct_post_temp['form_data'][$_key]);
                    }
                }
            }

            //unset action
            if ( isset($ct_post_temp['action']) ) {
                unset($ct_post_temp['action']);
            }

            if ( !$apbct->stats['no_cookie_data_taken'] && !empty($direct_no_cookie_data) ) {
                apbct_form__get_no_cookie_data($direct_no_cookie_data);
            }

            foreach ( $ct_post_temp as $key => $_value ) {
                if ( preg_match('/form_data_\d_name/', (string)$key) ) {
                    unset($ct_post_temp[$key]);
                }
            }

            return ct_gfa($ct_post_temp);
        }

        return null;
    }

    /**
     * @param $message
     *
     * @return void
     */
    public function doBlock($message)
    {
        if ( Post::get('action') === 'ufbl_front_form_action' ) {
            $result = array(
                'error_keys'       => array(),
                'error_flag'       => 1,
                'response_message' => $message
            );
            print json_encode($result);
            die();
        }
    }
}
