<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class StrongTestimonials extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( Post::get('action') === 'wpmtst_form' ) {
            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $_POST);

            return ct_get_fields_any($input_array);
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
        wp_die($message);
    }
}
