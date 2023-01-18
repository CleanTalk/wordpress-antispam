<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class GiveWP extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( Post::get('action') === 'give_process_donation'
            || (in_array(Post::get('give_action'), ['donation', 'purchase']) && Post::get('give_ajax')) ) {
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
        if ( function_exists('give_set_error') ) {
            give_set_error('spam_donation', $message);
        }
        add_action('give_ajax_donation_errors', function () use ($message) {
            return 'Error: ' . $message;
        });
    }
}
