<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;

class CleantalkWpDieOnComment extends IntegrationBase
{
    /**
     * Filter wp_die_handler to check the comment that failed WP validation.
     * @param $message
     * @param $title
     * @param $args
     * @return void
     */
    public function wpDie($message, $title, $args)
    {
        global $apbct;
        // only in this way we can collect validation errors
        if ( $title == __('Comment Submission Failure') ) {
            // save the validation error to use in the new instance
            $apbct->validation_error = $message;
            // call the new instance for spam checking
            $integrations = new \Cleantalk\Antispam\Integrations(array(), $apbct->settings);
            $check_spam_result = $integrations->checkSpam($apbct->comment_data, 'CleantalkWpDieOnComment');
            // await for CTR block message
            if (is_string($check_spam_result)) {
                // modify the default wp error message
                $ct_message = '<p>' . TT::toString($check_spam_result) . '</p>';
                $message .= $ct_message;
            }
        }
        // run default wp die handler anyway
        _default_wp_die_handler($message, $title, $args);
    }

    /**
     * Prepare integration data if it needs.
     * @param $argument
     * @return array|true True if everything is OK, false if something wrong and needs to exit integration without
     * changes. Mixed if it needs to return a specific arg
     */
    public function doPrepareActions($argument)
    {
        global $apbct;

        // if the second call in the flow, the action is changed - do nothing there
        if (current_action() !== 'wp_die_handler') {
            return true;
        }

        // first call in the flow - collect data for further instance calling
        $comment_data = wp_unslash($_POST);
        $comment_content        = TT::getArrayValueAsString($comment_data, 'comment');
        $comment_author         = trim(strip_tags(TT::getArrayValueAsString($comment_data, 'author')));
        $comment_author_email   = trim(TT::getArrayValueAsString($comment_data, 'email'));
        $comment_author_url     = trim(TT::getArrayValueAsString($comment_data, 'url'));

        $user = function_exists('apbct_wp_get_current_user') ? apbct_wp_get_current_user() : null;

        if ( $user && $user->exists() ) {
            $comment_author       = empty($user->display_name) ? $user->user_login : $user->display_name;
            $comment_author_email = $user->user_email;
        }

        // use the global state object to keep the comment data
        $apbct->comment_data = compact(
            'comment_author',
            'comment_author_email',
            'comment_content',
            'comment_author_url'
        );

        // return new handler method for wp_die
        return array($this, 'wpDie');
    }

    public function collectBaseCallData()
    {
        /*
         * Implements own way to collect base call data
         */
        global $apbct;
        $argument = TT::toArray($apbct->comment_data);
        // use referrer to get the comment page
        $request_uri = TT::toString(Server::get('HTTP_REFERER'));
        return array(
            'message'           => TT::getArrayValueAsString($argument, 'comment_content'),
            'sender_email'      => TT::getArrayValueAsString($argument, 'comment_author_email'),
            'sender_nickname'   => TT::getArrayValueAsString($argument, 'comment_author'),
            'event_token'       => Post::get('ct_bot_detector_event_token'),
            'sender_info'       => array(
                'sender_url'      => TT::getArrayValueAsString($argument, 'comment_author_url'),
                'form_validation' => ! isset($apbct->validation_error)
                    ? null
                    : json_encode(
                        array(
                            'validation_notice' => $apbct->validation_error,
                            'page_url'          => $request_uri
                        )
                    )
            ),
            'page_url' => $request_uri,
            'post_info' => array(
                'comment_type' => 'contact_form_wordpress_' . strtolower('CleantalkWpDieOnComment'),
            )
        );
    }

    public function doBlock($message)
    {
        return $message;
    }

    public function getDataForChecking($argument)
    {
        //stub
        return array();
    }
}
