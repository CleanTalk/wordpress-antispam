<?php

namespace Cleantalk\Antispam\Integrations;

class ThriveLeads extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        $sender_email              = ! empty($_POST['email']) ? $_POST['email'] : '';
        $custom_fields             = ! empty($_POST['custom_fields']) ? $_POST['custom_fields'] : array();
        $event_token               = '';
        $message                   = array();
        $sender_nickname           = '';

        if ( ! empty($custom_fields) ) {
            if ( isset($custom_fields['ct_no_cookie_hidden_field']) ) {
                apbct_form__get_no_cookie_data(
                    ['ct_no_cookie_hidden_field' => $custom_fields['ct_no_cookie_hidden_field']]
                );
            }

            if ( isset($custom_fields['ct_bot_detector_event_token']) ) {
                $event_token = $custom_fields['ct_bot_detector_event_token'];
            }
            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $custom_fields);

            $ct_temp_msg_data = ct_gfa($input_array);
            if ( empty($sender_email) ) {
                $sender_email = ! empty($ct_temp_msg_data['email']) ? $ct_temp_msg_data['email'] : '';
            }
            $sender_nickname = isset($ct_temp_msg_data['nickname']) && $ct_temp_msg_data['nickname'] ?: '';
            $subject         = isset($ct_temp_msg_data['subject']) && $ct_temp_msg_data['subject'] ?: '';
            $message         = isset($ct_temp_msg_data['message']) && $ct_temp_msg_data['message'] ?: array();
            if ( $subject !== '' && is_array($message) ) {
                $message = array_merge(array('subject' => $subject), $message);
            }
        }

        $data = array(
            'message'  => $message,
            'email'    => $sender_email,
            'nickname' => $sender_nickname,
        );
        if ( ! empty($event_token) ) {
            $data['event_token'] = $event_token;
        }

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked'     => true,
                        'comment'     => $message,
                        'stop_script' => apbct__stop_script_after_ajax_checking()
                    )
                )
            )
        );
    }
}
