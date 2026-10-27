<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\TT;

class LatePoint extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        // LatePoint 5 processes the customer data on the customer step
        if ( current_action() === 'latepoint_process_step' ) {
            $params = isset($this->hook_arguments[2]) ? TT::toArray($this->hook_arguments[2]) : array();
            if (
                strpos(TT::toString($argument), 'customer') !== 0 ||
                ! isset($params['customer']) ||
                ! is_array($params['customer'])
            ) {
                return null;
            }

            $customer = $params['customer'];
            unset($customer['password'], $customer['password_confirmation']);
            $email = TT::getArrayValueAsString($customer, 'email');
            $nickname = trim(
                TT::getArrayValueAsString($customer, 'first_name') . ' ' . TT::getArrayValueAsString($customer, 'last_name'),
                " \n\r\t\v\x00"
            );

            $data = ct_gfa_dto(apply_filters('apbct__filter_post', $customer), $email, $nickname)->getArray();
            $data['event_token'] = TT::getArrayValueAsString($params, 'ct_bot_detector_event_token');

            return $data;
        }

        //signs
        if (
            ( apbct_is_plugin_active('latepoint/latepoint.php') )
            && !empty($_POST)
            && Post::get('action') === 'latepoint_route_call'
            && Post::get('route_name') === 'steps__get_step'
        ) {
            //collect inner params
            $params = !empty($_POST['params']) && is_string($_POST['params']) ? $_POST['params'] : '';
            //explode with &
            $params = explode('&', $params);
            //map array to find inner values
            $params = array_map(function ($row) {
                $row_array = explode('=', urldecode($row));
                return isset($row_array[0], $row_array[1]) ? array($row_array[0] => $row_array[1]) : array();
            }, $params);
            $out_params = array();
            //restructure array
            foreach ($params as $_key => $value) {
                $value_inner_keys = array_keys($value);
                $value_inner_values = array_values($value);
                $new_key = isset($value_inner_keys[0])
                    ? $value_inner_keys[0]
                    : '';
                $new_value = isset($value_inner_values[0])
                    ? $value_inner_values[0]
                    : '';
                $out_params[$new_key] = $new_value;
            }

            // check current step to know if we should intercept this
            // probably we could use 'verify' instead of 'contact' if issues faced
            if (isset($out_params['current_step']) && $out_params['current_step'] === 'contact') {
                $data = ct_get_fields_any($out_params);
                $data['event_token'] = isset($out_params['ct_bot_detector_event_token']) ? $out_params['ct_bot_detector_event_token'] : null;
                if (!empty($out_params['ct_bot_detector_event_token'])) {
                    $data['event_token'] = $out_params['ct_bot_detector_event_token'];
                }
                if ( isset($data['message']['ct_bot_detector_event_token']) ) {
                    unset($data['message']['ct_bot_detector_event_token']);
                }
                if ( isset($data['message']['ct_no_cookie_hidden_field']) ) {
                    unset($data['message']['ct_no_cookie_hidden_field']);
                }
                return $data;
            }
        }

        return null;
    }

    /**
     * @param $message
     *
     * @psalm-suppress UnusedVariable
     */
    public function doBlock($message)
    {
        //JSON block message in LatePoint format
        wp_send_json(array('status' => 'error', 'message' => $message));
    }

    public function allow()
    {
    }
}
