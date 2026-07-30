<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class EventPrime extends IntegrationBase
{
    private $is_register_form;
    private $is_checkout_form;
    public function getDataForChecking($argument)
    {
        $this->is_register_form = isset($_POST['ep-attendee-register-nonce']);
        $this->is_checkout_form = isset($_POST['action']) && $_POST['action'] === 'ep_save_event_booking';
        if ($this->is_register_form || $this->is_checkout_form) {
            /**
             * Filter for POST
             */
            $input_data = $_POST;

            if ($this->is_checkout_form && !empty($input_data['data']) && is_string($input_data['data'])) {
                $parsed_data = array();
                parse_str($input_data['data'], $parsed_data);
                unset($input_data['data']);
                $input_data = array_merge($input_data, $parsed_data);
            }

            if ($this->is_checkout_form && !isset($input_data['ep_rg_field_email'])) {
                return null;
            }

            apbct_form__get_no_cookie_data($input_data);

            $event_token = '';
            if (isset($input_data['ct_bot_detector_event_token'])) {
                $event_token = $input_data['ct_bot_detector_event_token'];
                unset($input_data['ct_bot_detector_event_token']);
            }
            if (isset($input_data['data']['ct_bot_detector_event_token'])) {
                $event_token = $input_data['data']['ct_bot_detector_event_token'];
                unset($input_data['data']['ct_bot_detector_event_token']);
            }

            $processed_post = apply_filters('apbct__filter_post', $input_data);
            $data = ct_gfa_dto($processed_post, $this->is_checkout_form ? $input_data['ep_rg_field_email'] : '')->getArray();

            if (!empty($event_token)) {
                $data['event_token'] = $event_token;
            }


            $data['register'] = $this->is_register_form ? true : '';

            return $data;
        }

        return null;
    }

    public function doBlock($message)
    {
        if ($this->is_register_form) {
            wp_send_json([
                'data'    => [
                    'success' => 0,
                    'msg'     => $message
                ],
                'success' => false,
            ], 200);
        } elseif ($this->is_checkout_form) {
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
}
