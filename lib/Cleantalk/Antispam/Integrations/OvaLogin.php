<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\Variables\Post;

class OvaLogin extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( apbct_is_plugin_active('ova-login/ova-login.php')
             && ! empty($_POST)
             && Post::get('email')
             && Post::get('username')
        ) {
            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $_POST);

            $data             = ct_get_fields_any($input_array);
            $data['register'] = true;

            return $data;
        }

        return null;
    }

    /**
     * @param $message
     *
     * @psalm-suppress UnusedVariable
     */
    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }
}
