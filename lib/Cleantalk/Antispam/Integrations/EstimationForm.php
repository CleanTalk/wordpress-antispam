<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class EstimationForm extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( Post::get('customerInfos') ) {
            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', Post::get('customerInfos'));

            return ct_get_fields_any($input_array);
        }

        return null;
    }

    public function doBlock($message)
    {
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked'     => true,
                        'comment'     => $message,
                        'stop_script' => 1
                    )
                )
            )
        );
    }
}
