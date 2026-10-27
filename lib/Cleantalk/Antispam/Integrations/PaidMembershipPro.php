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
        /**
         * Check the checkout form only
         */
        if ( function_exists('pmpro_was_checkout_form_submitted') && ! pmpro_was_checkout_form_submitted() ) {
            return null;
        }

        $this->is_spammer = $argument;

        /**
         * Filter for GET and POST, PMPro reads the checkout fields from both
         */
        $input_array = apply_filters('apbct__filter_post', array_merge($_GET, $_POST));

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
