<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\Common\TT;

class Hustle extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if (
            (apbct_is_plugin_active('wordpress-popup/popover.php') || apbct_is_plugin_active('hustle/opt-in.php'))
            && !empty($_POST)
            && Post::get('data')
        ) {
            $data = TT::toArray(Post::get('data'));

            if (isset($data['form'])) {
                $form_data = Sanitize::cleanTextField(urldecode(TT::toString($data['form'])));
                parse_str($form_data, $form_data_parsed);
                apbct_form__get_no_cookie_data($form_data_parsed);

                $input_array = apply_filters('apbct__filter_post', $form_data_parsed);
                $data = ct_get_fields_any($input_array);
                $data['register'] = true;

                return $data;
            }
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
        $data = [
            'success' => false,
            'data' => [
                'success' => false,
                'message' => '',
                'errors' => [
                    $message
                ]
            ]
        ];

        wp_send_json($data);
    }
}
