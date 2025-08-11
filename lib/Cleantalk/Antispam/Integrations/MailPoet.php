<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class MailPoet extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( !(apbct_is_plugin_active('mailpoet/mailpoet.php')
            && !empty($_POST)
            && Post::get('token')
            && Post::get('method') === 'subscribe')
        ) {
            return null;
        }

        if ( isset($_POST['data'], $_POST['data']['ct_bot_detector_event_token']) ) {
            $event_token = $_POST['data']['ct_bot_detector_event_token'];
        }

        $data = apply_filters('apbct__filter_post', $_POST);
        $data = ct_gfa($data);
        $data['event_token'] = isset($event_token) ? $event_token : '';

        //clean message from service fields
        if ( isset($data['message']) && is_array($data['message']) ) {
            $data['message'] = array_filter($data['message'], function ($_msg_string, $msg_value) {
                if ( in_array($msg_value, array(
                    'data_ct_no_cookie_hidden_field',
                    'data_ct_bot_detector_event_token',
                    'action',
                    'token',
                    'endpoint',
                    'method',
                    'api_version',
                )) ) {
                    return false;
                }
                return true;
            }, ARRAY_FILTER_USE_BOTH);
        }

        return $data;
    }

    public function doBlock($message)
    {
        wp_send_json(
            array(
                'errors' => [
                    [
                        'error' => 'bad_request',
                        'message' => $message
                    ]
                ],
            ),
            403
        );
        die();
    }
}
