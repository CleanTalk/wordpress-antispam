<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;

class SmartForms extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( Post::get('action') === 'rednao_smart_forms_save_form_values') {
            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $_POST);
            Cookie::$force_alt_cookies_global = true;
            return ct_gfa($input_array);
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
        if ( Post::get('action') === 'rednao_smart_forms_save_form_values' ) {
            $result = array(
                'message'        => $message,
                'refreshCaptcha' => 'n',
                'success'        => 'n'
            );
            print json_encode($result);
            die();
        }
    }
}
