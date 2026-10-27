<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class DWQuestionAnswer extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        if (
            ! is_array($argument) ||
            ! apbct_is_user_enable() ||
            ($apbct->settings['data__protect_logged_in'] != 1 && is_user_logged_in()) // Skip processing for logged in users.
        ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);
            return null;
        }

        $email = isset($argument['comment_author_email']) ? $argument['comment_author_email'] : '';
        // The comment of the logged in user has no email
        if ( $email === '' && is_user_logged_in() ) {
            $email = wp_get_current_user()->user_email;
        }

        return array(
            'email'       => $email,
            'nickname'    => isset($argument['comment_author']) ? $argument['comment_author'] : '',
            'message'     => isset($argument['comment_content']) ? $argument['comment_content'] : '',
            'event_token' => Post::getString('ct_bot_detector_event_token'),
        );
    }

    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }
}
