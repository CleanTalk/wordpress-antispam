<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class ElfsightContactForm extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( Post::get('fields') ) {
            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', Post::get('fields'));

            return ct_get_fields_any($input_array);
        }

        return null;
    }

    public function doBlock($message)
    {
        header('Content-type: application/json; charset=utf-8');
        exit(json_encode(array(400, $message)));
    }
}
