<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class PaidMembershipPro extends IntegrationBase
{
    private $is_spammer;

    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        $this->is_spammer = $argument;

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
        return $message;
    }

    public function allow()
    {
        return $this->is_spammer;
    }
}
