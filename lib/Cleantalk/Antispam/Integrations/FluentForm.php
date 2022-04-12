<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\Variables\Post;

class FluentForm extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( Post::get('data') ) {
            parse_str(Post::get('data'), $form_data);

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
