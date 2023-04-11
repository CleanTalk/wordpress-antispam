<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\Variables\Post;

class FluentForm extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        if ( isset($_POST['data']) ) {
            parse_str($_POST['data'], $form_data);
            foreach ($form_data as $param => $param_value) {
                if (strpos((string)$param, 'ct_no_cookie_hidden_field') !== false || (is_string($param_value) && strpos($param_value, '_ct_no_cookie_data_') !== false)) {
                    if ($apbct->data['cookies_type'] === 'none') {
                        \Cleantalk\ApbctWP\Variables\NoCookie::setDataFromHiddenField($form_data[$param]);
                        $apbct->stats['no_cookie_data_taken'] = true;
                        $apbct->save('stats');
                    }

                    unset($form_data[$param]);
                }
            }

            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $form_data);

            return ct_get_fields_any($input_array);
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
