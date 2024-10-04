<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\TT;

class StrongTestimonials extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( Post::get('action') === 'wpmtst_form' || Post::get('action') === 'wpmtst_form2' ) {
            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $_POST);

            $nickname = TT::toString(Post::get('client_name'));

            return ct_get_fields_any($input_array, '', $nickname);
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
        if ( apbct_is_ajax() ) {
            $return = array(
                'success' => false,
                'errors' => array(
                    'email' => $message)
            );
            echo json_encode($return);
            wp_die();
        } else {
            wp_die($message);
        }
    }
}
