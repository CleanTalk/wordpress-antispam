<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\TT;

class MailPoet2 extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        if ( Post::get('task') !== 'send_preview' && Post::get('task') !== 'send_test_mail' && Post::get('data') ) {
            $data = TT::toArray(Post::get('data'));
            $prepared_data = [];
            $event_token = '';

            foreach ( $data as $value ) {
                if ( isset($value['name'], $value['value'])) {
                    if ( $value['name'] === 'ct_bot_detector_event_token' ) {
                        $event_token = $value['value'];
                        continue;
                    }
                    if ( $value['name'] === 'ct_no_cookie_hidden_field' && ! $apbct->stats['no_cookie_data_taken'] ) {
                        apbct_form__get_no_cookie_data(['ct_no_cookie_hidden_field' => $value['value']]);
                        continue;
                    }
                    $prepared_data[$value['name']] = $value['value'];
                }
            }
            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $prepared_data);
            $request_parameters = ct_get_fields_any($input_array);
            $request_parameters['event_token'] = $event_token;
            return $request_parameters;
        }
    }

    public function doBlock($message)
    {
        $result = array('result' => false, 'msgs' => array('updated' => array($message)));
        print htmlspecialchars(TT::toString(Get::get('callback')), ENT_QUOTES) . '(' . json_encode($result) . ');';
        die();
    }
}
