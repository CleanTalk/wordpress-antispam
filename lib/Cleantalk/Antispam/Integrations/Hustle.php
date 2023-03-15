<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Sanitize;

class Hustle extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if (
            (apbct_is_plugin_active('wordpress-popup/popover.php') || apbct_is_plugin_active('hustle/opt-in.php'))
            && !empty($_POST)
            && Post::get('data')
        ) {
            $formData = Post::get('data');

            if (isset($_POST['data']['form'])) {
                $formData = Sanitize::cleanTextField(urldecode($_POST['data']['form']));
                parse_str($formData, $formData);
                apbct_form__get_no_cookie_data($formData);
            }

            $input_array = apply_filters('apbct__filter_post', $formData);
            $data = ct_get_fields_any($input_array);
            $data['register'] = true;

            return $data;
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
