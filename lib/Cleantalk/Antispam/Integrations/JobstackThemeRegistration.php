<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\Common\TT;
use Cleantalk\Variables\Post;

class JobstackThemeRegistration extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( Post::get('new_user_submit') ) {
            return null;
        }

        $form_data = [];
        $form_data['email'] = TT::toString(Post::get('new_user_email'));

        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $form_data);

        $input_array['register'] = true;

        return $input_array;
    }

    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }
}
