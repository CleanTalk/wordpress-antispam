<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class PaidMemberSubscription extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $_POST);

        $output = ct_gfa($input_array);
        $output['register'] = true;

        if ( Post::get('ct_bot_detector_event_token') ) {
            $output['event_token'] = Post::get('ct_bot_detector_event_token');
        }

        return $output;
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        ct_die_extended($message);
    }
}
