<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\TT;

class FluentForm extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;
        $event_token = '';

        /**
         * Do not use Post:get() there - it uses sanitize_textarea and drops special symbols,
         * including whitespaces - this could concatenate parts of data in single string!
         **/
        if ( isset($_POST['data']) && is_string($_POST['data']) ) {
            parse_str($_POST['data'], $form_data);
            foreach ($form_data as $param => $param_value) {
                if (strpos((string)$param, 'ct_no_cookie_hidden_field') !== false || (is_string($param_value) && strpos($param_value, '_ct_no_cookie_data_') !== false)) {
                    if ($apbct->data['cookies_type'] === 'none') {
                        \Cleantalk\ApbctWP\Variables\NoCookie::setDataFromHiddenField($param_value);
                        $apbct->stats['no_cookie_data_taken'] = true;
                        $apbct->save('stats');
                    }
                    unset($form_data[$param]);
                }
                if ($param === 'ct_bot_detector_event_token') {
                    $event_token = $param_value;
                    unset($form_data[$param]);
                }
            }

            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $form_data);

            $gfa_checked_data = ct_get_fields_any($input_array);

            $gfa_checked_data['event_token'] = $event_token;

            if (isset($gfa_checked_data['message'], $gfa_checked_data['message']['apbct_visible_fields'])) {
                unset($gfa_checked_data['message']['apbct_visible_fields']);
            }

            return $gfa_checked_data;
        }

        return null;
    }

    public function doBlock($message)
    {
        wp_send_json(
            array(
                'errors' => array(
                    'restricted' => array(
                        $message
                    )
                )
            ),
            422
        );
    }
}
