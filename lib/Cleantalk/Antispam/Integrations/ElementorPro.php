<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\TT;

class ElementorPro extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        if (
            ($apbct->settings['data__protect_logged_in'] != 1 && is_user_logged_in()) || // Skip processing for logged in users.
            Post::get('form_fields_password') ||
            Post::get('form-field-password') || // Skip processing for login form.
            apbct_exclusions_check__url()
        ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);
            return null;
        }

        apbct_form__get_no_cookie_data($_POST);

        $event_token = '';

        if ( Post::get('ct_bot_detector_event_token') ) {
            $event_token = Post::get('ct_bot_detector_event_token');
        }

        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $_POST);

        $ct_temp_msg_data = ct_gfa($input_array);

        $sender_email    = isset($ct_temp_msg_data['email']) ? $ct_temp_msg_data['email'] : '';
        $sender_nickname = isset($ct_temp_msg_data['nickname']) ? $ct_temp_msg_data['nickname'] : '';
        $subject         = isset($ct_temp_msg_data['subject']) ? $ct_temp_msg_data['subject'] : '';
        $message         = isset($ct_temp_msg_data['message']) ? $ct_temp_msg_data['message'] : array();
        if ( $subject !== '' ) {
            $message = array_merge(array('subject' => $subject), $message);
        }

        //Doboard 6583 - skip this to avoid repeats
        unset($message['referer_title']);

        $form_data = TT::toArray(Post::get('form_fields'));
        if ( $form_data ) {
            if ( ! $sender_nickname ) {
                $sender_nickname = !empty($form_data['name']) ? $form_data['name'] : '';
                if ( !$sender_nickname ) {
                    $re = '/[fF]irst_[nN]ame|[lL]ast_[nN]ame/m';
                    preg_match_all($re, implode(' ', array_keys($form_data)), $matches, PREG_SET_ORDER, 0);
                    if ( !empty($matches) ) {
                        foreach ( $matches as $match ) {
                            $sender_nickname .= isset($match[0]) && isset($form_data[$match[0]]) ? $form_data[$match[0]] . ' ' : '';
                        }
                        $sender_nickname = trim($sender_nickname);
                    }
                }
            }
        }

        $data = array(
            'message'         => $message,
            'email'    => $sender_email,
            'nickname' => $sender_nickname,
            'event_token' => $event_token,
        );

        return $data;
    }

    public function doBlock($message)
    {
        wp_send_json_error(array(
            'message' => $message,
            'data'    => array()
        ));
    }

    public function allow()
    {
        return true;
    }
}
