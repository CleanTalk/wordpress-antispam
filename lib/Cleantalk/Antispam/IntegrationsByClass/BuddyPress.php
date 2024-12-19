<?php

namespace Cleantalk\Antispam\IntegrationsByClass;

use Cleantalk\ApbctWP\Escape;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;
use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\State;

/**
 * @psalm-suppress UnusedClass
 */
class BuddyPress extends IntegrationByClassBase
{
    public function doAjaxWork()
    {
        add_action('messages_message_before_save', array($this, 'privateMsgCheck'));
    }

    public function doPublicWork()
    {
        global $apbct;

        // global actions for registration
        add_action('bp_before_registration_submit_buttons', 'ct_register_form', 1);
        add_filter('bp_signup_validate', 'ct_registration_errors', 1);
        add_filter('bp_signup_validate', 'ct_check_registration_errors', 999999);

        // BuddyPress private messages check
        add_action('messages_message_before_save', array($this, 'privateMsgCheck'), 1);

        // Handle buddyPress users manage hooks to cathch spam/not spam feedback
        add_action('make_spam_user', array($this, 'sendFeedback'));
        add_action('make_ham_user', array($this, 'sendFeedback'));

        // Show admin notice on the users page
        if (!empty($apbct->data['bp_feedback_message'])) {
            add_action('admin_notices', array($this, 'userFeedbackShowAdminNotice'), 998);
        }

        //add ct_hash to meta on user registartion BEFORE user created
        add_action('bp_core_signups_after_add', array($this, 'updateCTHashOnUserRegistration'), 10, 3);
        //add ct_hash to meta on user registartion AFTER user created
        add_action('bp_core_activated_user', array($this, 'updateCTHashOnUserActivation'), 10, 3);
    }

    public function doAdminWork()
    {
        add_filter('bp_activity_is_spam_before_save', array($this, 'activityWall'), 999, 2);
        add_action('bp_locate_template', array($this, 'getTemplateName'), 10, 6);
    }

    /**
     * Public function - Tests new private messages (dialogs)
     *
     * @param object $bp_message_obj
     *
     * @return void with errors if spam has found
     * @psalm-suppress UndefinedClass
     * @psalm-suppress UnusedVariable
     * @global State $apbct
     */
    public function privateMsgCheck($bp_message_obj)
    {
        global $apbct;

        // Check for enabled option
        if ($apbct->settings['comments__bp_private_messages'] == 0 ||
            apbct_exclusions_check() ||
            ($apbct->settings['data__protect_logged_in'] == 0 && is_user_logged_in())
        ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

            return;
        }

        // Check for quantity of comments
        $comments_check_number = defined('CLEANTALK_CHECK_COMMENTS_NUMBER') ? CLEANTALK_CHECK_COMMENTS_NUMBER : 3;

        if ( $apbct->settings['comments__check_comments_number'] ) {
            $args             = array(
                'user_id'      => $bp_message_obj->sender_id,
                'box'          => 'sentbox',
                'type'         => 'all',
                'limit'        => $comments_check_number,
                'page'         => null,
                'search_terms' => '',
                'meta_query'   => array()
            );
            $sentbox_msgs     = \BP_Messages_Thread::get_current_threads_for_user($args);
            $cnt_sentbox_msgs = isset($sentbox_msgs['total']) ? $sentbox_msgs['total'] : 0;
            $args['box']      = 'inbox';
            $inbox_msgs       = \BP_Messages_Thread::get_current_threads_for_user($args);
            $cnt_inbox_msgs   = isset($inbox_msgs['total']) ? $inbox_msgs['total'] : 0;

            if ( ($cnt_inbox_msgs + $cnt_sentbox_msgs) >= $comments_check_number ) {
                $is_max_comments = true;
            }
        }

        $exception_action = false;
        if ( ! empty($is_max_comments) ) {
            $exception_action = true;
        }

        $sender_user_obj = get_user_by('id', $bp_message_obj->sender_id);

        //Making a call
        $event_token = TT::getArrayValueAsString(Post::get('meta'), 'ct_bot_detector_event_token');
        $base_call_result = apbct_base_call(
            array(
                'message'         => $bp_message_obj->subject . " " . $bp_message_obj->message,
                'sender_email'    => $sender_user_obj !== false ? $sender_user_obj->data->user_email : '',
                'sender_nickname' => $sender_user_obj !== false ? $sender_user_obj->data->user_login : '',
                'post_info'       => array(
                    'comment_type' => 'buddypress_comment',
                    'post_url'     => Server::get('HTTP_REFERER'),
                ),
                'js_on'           => apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true) ?: apbct_js_test(Sanitize::cleanTextField(Post::get('ct_checkjs'))),
                'sender_info'     => array('sender_url' => null),
                'exception_action' => $exception_action === true ? 1 : 0,
                'event_token' => $event_token,
            )
        );

        if ( isset($base_call_result['ct_result']) ) {
            $ct_result = $base_call_result['ct_result'];

            if ( $ct_result->allow == 0 ) {
                if (apbct_is_ajax() ) {
                    wp_send_json_error(
                        array(
                            'feedback' => $ct_result->comment,
                            'type' => 'error'
                        )
                    );
                } else {
                    wp_die(
                        "<h1>"
                        . __('Spam protection by CleanTalk', 'cleantalk-spam-protect')
                        . "</h1><h2>" . $ct_result->comment . "</h2>",
                        '',
                        array(
                            'response'       => 403,
                            "back_link"      => true,
                            "text_direction" => 'ltr'
                        )
                    );
                }
            }
        }
    }

    /**
     * Test BuddyPress activity for spam (post update only)
     *
     * @param bool $is_spam
     * @param object $activity_obj Activity object (\plugins\buddypress\bp-activity\classes\class-bp-activity-activity.php)
     *
     * @return boolean Spam flag
     * @psalm-suppress UnusedVariable
     * @global State $apbct
     */
    public function activityWall($is_spam, $activity_obj = null)
    {
        global $apbct;

        $allowed_post_actions = array('post_update', 'new_activity_comment');

        if ( ! in_array(Post::get('action'), $allowed_post_actions) ||
            $activity_obj === null ||
            ! Post::get('action') ||
            (isset($activity_obj->privacy) && $activity_obj->privacy == 'media') ||
            apbct_exclusions_check() ||
            ! $apbct->settings['forms__contact_forms_test']
        ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

            return false;
        }

        $curr_user = get_user_by('id', $activity_obj->user_id);

        //Making a call
        $base_call_result = apbct_base_call(
            array(
                'message'         => is_string($activity_obj->content) ? $activity_obj->content : '',
                'sender_email'    => $curr_user !== false ? $curr_user->data->user_email : '',
                'sender_nickname' => $curr_user !== false ? $curr_user->data->user_login : '',
                'post_info'       => array(
                    'post_url'     => Server::get('HTTP_REFERER'),
                    'comment_type' => 'buddypress_activitywall',
                ),
                'sender_info'     => array('sender_url' => null),
            )
        );

        if ( isset($base_call_result['ct_result']) ) {
            $ct_result = $base_call_result['ct_result'];

            if ( $ct_result->allow == 0 ) {
                add_action('bp_activity_after_save', array($this, 'activityWallShowResponse'), 1, 1);
                $apbct->spam_notification = $ct_result->comment;

                return true;
            }
        }

        return $is_spam;
    }

    /**
     * Outputs message to AJAX frontend handler
     *
     * @return void
     * @global State $apbct
     */
    public function activityWallShowResponse()
    {
        global $apbct;

        $message = isset($apbct->spam_notification) ? $apbct->spam_notification : '';

        // Legacy template
        if (isset($apbct->buddy_press_tmpl) && $apbct->buddy_press_tmpl === 'bp-legacy') {
            die('<div id="message" class="error bp-ajax-message"><p>' . $message . '</p></div>');
            // Nouveau template and others
        } else {
            @header('Content-Type: application/json; charset=' . get_option('blog_charset'));
            die(
                json_encode(
                    array(
                        'success' => false,
                        'data'    => array('message' => $message),
                    )
                )
            );
        }
    }

    /**
     * Get BuddyPress template name
     *
     * @param string $located
     * @param string $_template_name
     * @param string $_template_names
     * @param string $_template_locations
     * @param bool $_load
     * @param bool $_require_once
     */
    public function getTemplateName($located, $_template_name, $_template_names, $_template_locations, $_load, $_require_once)
    {
        global $apbct;

        preg_match("/\/([a-z-_]+)\/buddypress-functions\.php$/", $located, $matches);
        $apbct->buddy_press_tmpl = isset($matches[1]) ? $matches[1] : 'unknown';
    }

    /**
     * Show admin notice if feedback from buddypress hooks collected and prepared to send
     * @return void
     */
    public function userFeedbackShowAdminNotice()
    {
        global $apbct;
        // second check if message persists
        if (!empty($apbct->data['bp_feedback_message'])) {
            $html = '<div class="notice notice-success is-dismissible">
                                        <p>' . $apbct->data['bp_feedback_message'] . '</p>
                                    </div>';
            echo Escape::escKsesPreset(
                $html,
                'apbct_response_custom_message'
            );
            // clear message to prevent next show
            $apbct->data['bp_feedback_message'] = null;
            $apbct->saveData();
        }
    }

    /**
     * Send feedback to the cloud
     * @param int $user_id
     * @return void
     */
    public function sendFeedback($user_id)
    {
        global $apbct;

        // check user rights and if user_id arg
        if ( !current_user_can('activate_plugins') || empty($user_id) || !is_scalar($user_id)) {
            return;
        }

        // get user meta
        $meta = get_user_meta((int)$user_id);

        // get ct_hash from meta
        if (empty($meta['ct_hash']) || !isset($meta['ct_hash'][0])) {
            return;
        }
        $ct_hash = $meta['ct_hash'][0];
        $current_action = current_action();

        // set new solution or return on wrong action
        if ( $current_action === 'make_spam_user' ) {
            $feedback_flag = 0;
        } elseif ( $current_action === 'make_ham_user' ) {
            $feedback_flag = 1;
        } else {
            return;
        }

        // add message to state, message data will be saved in the ct_feedback() function
        $apbct->data['bp_feedback_message'] = __('CleanTalk: Your feedback will be sent to the cloud within the next hour. Feel free to contact us via support@cleantalk.org.', 'cleantalk-spam-protect');

        // this will be sent by cron task within an hour
        ct_feedback($ct_hash, $feedback_flag);
    }

    /**
     * Fires on BP Signup process after user is in activation await stage.
     * Adds a ct_hash to signup meta.
     * This is not a user creation process!
     * @param $_retval - unused
     * @param $_r - unused
     * @param $args - args of signup data
     *
     * @return void
     */
    public function updateCTHashOnUserRegistration($_retval, $_r, $args)
    {
        if (
            class_exists('\BP_Signup') &&
            ! empty($args['activation_key'])
        ) {
            try {
                /** @psalm-suppress UndefinedClass */
                $object_usage = \BP_Signup::get(array('activation_key' => $args['activation_key']));
                $the_signup   = isset($object_usage['signups'][0]) ? $object_usage['signups'][0] : null;
                $signup_meta  = is_object($the_signup) && property_exists($the_signup, 'meta')
                    ? $the_signup->meta
                    : null;
                $id           = is_object($the_signup) && property_exists($the_signup, 'id')
                    ? $the_signup->id
                    : null;
                $hash         = ct_hash();
                if ( is_array($signup_meta) && !empty($hash) && !empty($id)) {
                    $signup_meta['ct_hash'] = $hash;
                    /** @psalm-suppress UndefinedClass */
                    \BP_Signup::update(array('signup_id' => $id, 'meta' => $signup_meta));
                }
            } catch (\Exception $e) {
                //does nothing
            }
        }
    }

    /**
     * Fires if user created after signup process verified
     * @param $user_id - user id
     * @param $_key - unused
     * @param $user - user data
     *
     * @return void
     */
    public function updateCTHashOnUserActivation($user_id, $_key, $user)
    {
        if (!empty($user['meta']) && !empty($user['meta']['ct_hash']) && $user_id) {
            update_user_meta($user_id, 'ct_hash', $user['meta']['ct_hash']);
        }
    }
}
