<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class NewslettersTribulant extends IntegrationBase
{
    private $return_argument;

    public function getDataForChecking($argument)
    {
        $this->return_argument = $argument;

        // Skip the ajax subscription, it is checked by the common ajax check
        if (
            ! $argument ||
            doing_action('wp_ajax_wpmlsubscribe') ||
            doing_action('wp_ajax_nopriv_wpmlsubscribe')
        ) {
            return null;
        }

        // The plugin adds the subscriber from POST
        $email = isset($_POST['email']) && is_string($_POST['email']) ? $_POST['email'] : '';

        $input_array = apply_filters('apbct__filter_post', $_POST);
        $input_array['event_token'] = Post::getString('ct_bot_detector_event_token');

        return ct_gfa_dto($input_array, $email)->getArray();
    }

    /**
     * @param $message
     *
     * @return void
     * @psalm-suppress UnusedVariable
     */
    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }

    public function allow()
    {
        return $this->return_argument;
    }
}
