<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\Variables\Post;

class FluentForm extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( Post::get('data') ) {
            parse_str(Post::get('data'), $form_data);

            parse_str($_POST['data'], $form_data_dirty);
            $email = $form_data_dirty['email'] ? Sanitize::cleanEmail($form_data_dirty['email']) : null;

            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $form_data);

            return ct_get_fields_any($input_array, $email);
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
