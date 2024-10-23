<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class TourMasterRegister extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        if (
            apbct_is_plugin_active('tourmaster/tourmaster.php') &&
            !empty($_POST) &&
            Post::get('email') &&
            Post::get('username') &&
            Post::get('tourmaster-require-acceptance')
        ) {
           /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $_POST);

            $data = ct_gfa($input_array);
            $data['register'] = true;
            return $data;
        }

        return null;
    }

    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }
}
