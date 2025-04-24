<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\Variables\AltSessions;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\Helper;
use WP_User;

class CleantalkPreprocessComment extends IntegrationBase
{
    private $apbct;
    private $wp_comment_post_id;
    private $wp_comment;
    private $post_info;
    private $ct_jp_comments;
    private $exception_action;
    private $comments_check_number_needs_to_skip_request;
    public function getDataForChecking($argument)
    {
        return array();
    }

    /**
     * Prepare intergation data if needs.
     * @param $argument
     * @return bool True if everything is OK, false if something wrong and needs to exit integration without changes.
     */
    public function doPrepareActions($argument)
    {
        // this action is called just when WP process POST request (adds new comment)
        // this action is called by wp-comments-post.php
        // after processing WP makes redirect to post page with comment's form by GET request (see above)
        global $current_user, $comment_post_id, $ct_comment_done, $ct_jp_comments, $apbct;

        $this->exception_action = false;

        if (is_null($argument)) {
            //run debug plugin log
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '(): comment argument is null' . __LINE__, $_POST);
            return false;
        }

        if (
            apbct_is_plugin_active('clean-and-simple-contact-form-by-meg-nicholas/clean-and-simple-contact-form-by-meg-nicholas.php') &&
            Post::getString('cscf_nonce') &&
            Post::getString('post-id') &&
            $argument
        ) {
            // if JS disabled the CSCF plugin run the preprocess_comment hook
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '(): is CSCF contact form - has own integration' . __LINE__, $_POST);
            return false;
        }

        $this->wp_comment = $argument;
        $this->apbct = $apbct;
        $this->ct_jp_comments = $ct_jp_comments && Post::getBool('jetpack_comments_nonce');

        $comment_post_id = $this->wp_comment['comment_post_ID'];
        $this->wp_comment_post_id = $comment_post_id;

        $this->post_info = array();
        $this->comments_check_number_needs_to_skip_request = defined('CLEANTALK_CHECK_COMMENTS_NUMBER') ? CLEANTALK_CHECK_COMMENTS_NUMBER : 3;

        /**
         * Custom mail notifications processing
         */
        $json_list_of_emails_to_notify = $this->getEmailAddressesListToNotify();
        if (false !== $json_list_of_emails_to_notify) {
            $apbct->comment_notification_recipients = $json_list_of_emails_to_notify;
        }

        /**
         * Handling process skip ruleset
         */
        $do_skip_on_line = $this->doSkipReason($current_user, $ct_comment_done);

        if (false !== $do_skip_on_line) {
            //run debug plugin log
            do_action('apbct_skipped_request', $do_skip_on_line, $_POST);
            //$this->exception_action = true;
            // todo do we need exception action on this ruleset or just return comment?
            return false;
        }

        //Set globally that CleanTalk commnent processing executed
        $ct_comment_done = true;
        /**
         * Pre-action done. Run data collecting.
         */
        return true;
    }

    /**
     * Integration own way to collect CleanTalk base call data.
     * @return array
     */
    public function collectBaseCallData()
    {
        // JetPack comments logic
        $this->post_info['comment_type'] = $this->ct_jp_comments ? 'jetpack_comment' : $this->wp_comment['comment_type'];
        $this->post_info['post_url']     = ct_post_url(null, $this->wp_comment_post_id);

        // Comment type
        $this->post_info['comment_type'] = empty($this->post_info['comment_type'])
            ? 'contact_form_wordpress_' . strtolower('CleantalkPreprocessComment')
            : $this->post_info['comment_type'];

        $checkjs = apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true) ?: apbct_js_test(Sanitize::cleanTextField(Post::get('ct_checkjs')));

        // jetpack_comment case
        if ( $this->post_info['comment_type'] === 'jetpack_comment' ) {
            $checkjs = apbct_js_test(AltSessions::get('ct_checkjs'));
            $event_token = AltSessions::get('ct_bot_detector_event_token');
        }

        $example = null;
        if ( $this->apbct->data['relevance_test'] ) {
            $wp_post = get_post($this->wp_comment_post_id);
            if ( $wp_post !== null && is_object($wp_post) ) {
                $example['title']    = $wp_post->post_title;
                $example['body']     = $wp_post->post_content;
                $example['comments'] = null;

                $last_comments = get_comments(array('status' => 'approve', 'number' => 10, 'post_id' => $this->wp_comment_post_id));
                if ( is_array($last_comments) || is_object($last_comments) ) {
                    foreach ( $last_comments as $post_comment ) {
                        $example['comments'] .= "\n\n" . $post_comment->comment_content;
                    }
                }

                $example = json_encode($example);
            }

            // Use plain string format if've failed with JSON
            if ( ($example === false || $example === null) && is_object($wp_post) ) {
                $example = ($wp_post->post_title !== null) ? $wp_post->post_title : '';
                $example .= ($wp_post->post_content !== null) ? "\n\n" . $wp_post->post_content : '';
            }
        }

        if ($this->checkMaxCommentsPublishedByUser()) {
            $this->exception_action = true;
            ct_hash(md5(time() . $this->wp_comment['comment_author_email']));
            add_action('comment_post', 'ct_set_real_user_badge_hash', 999, 2);
        }

        $http_host = is_string(Server::get('HTTP_HOST')) ? Server::get('HTTP_HOST') : '';
        $request_uri = is_string(Server::get('REQUEST_URI')) ? Server::get('REQUEST_URI') : '';
        /** @psalm-suppress PossiblyFalseOperand, PossiblyInvalidOperand */
        $base_call_data = array(
            'message'         => $this->wp_comment['comment_content'],
            'example'         => $example,
            'sender_email'    => $this->wp_comment['comment_author_email'],
            'sender_nickname' => $this->wp_comment['comment_author'],
            'post_info'       => $this->post_info,
            'js_on'           => $checkjs,
            'event_token'     => isset($event_token) ? $event_token : '',
            'sender_info'     => array(
                'sender_url'      => @$this->wp_comment['comment_author_url'],
                'form_validation' => ! isset($this->apbct->validation_error)
                    ? null
                    : json_encode(
                        array(
                            'validation_notice' => $this->apbct->validation_error,
                            'page_url'          => $http_host . $request_uri,
                        )
                    )
            ),
            'exception_action' => $this->exception_action,
        );

        /**
         * Add honeypot_field to $base_call_data is comments__hide_website_field on
         */
        if ( isset($this->apbct->settings['comments__hide_website_field']) && $this->apbct->settings['comments__hide_website_field'] ) {
            $honeypot_field = 1;
            if (
                $this->post_info['comment_type'] === 'comment' &&
                Post::get('url') &&
                Post::get('comment_post_ID')
            ) {
                $honeypot_field = 0;
                // if url is filled then pass them to $base_call_data as additional fields
                $base_call_data['sender_info']['honeypot_field_value']  = Sanitize::cleanTextField(Post::get('url'));
                $base_call_data['sender_info']['honeypot_field_source'] = 'url';
            }

            $base_call_data['honeypot_field'] = $honeypot_field;
        }

        return $base_call_data;
    }

    public function doActionsBeforeBaseCall($argument)
    {
        //Don't check trusted users
        $post_url = isset($this->post_info['post_url']) ? $this->post_info['post_url'] : false;
        $run_set_meta_on_flow = $this->doNeedSetMeta($post_url);
        if ($run_set_meta_on_flow) {
            add_action('comment_post', 'ct_set_meta', 6, 2);
        }
    }

    public function doActionsBeforeAllowDeny($argument)
    {
        ct_hash($this->base_call_result['ct_result']->id);
    }

    /**
     * Do all the actions after and if request is allowed
     * @return void
     */
    public function allow()
    {
        $wp_comment_moderation_enabled = get_option('comment_moderation') === '1';
        $wp_auto_approve_for_user_who_has_approved_comment = get_option('comment_previously_approved') === '1';
        $clentalk_option_skip_moderation_for_first_comment = get_option('cleantalk_allowed_moderation', 1) == 1;
        $is_allowed_because_of_inactive_license = false;

        $args            = array(
            'author_email' => $this->wp_comment['comment_author_email'],
            'status'       => 'approve',
            'count'        => false,
            'number'       => 1,
        );
        /** @psalm-suppress PossiblyInvalidArgument */
        $is_new_author = count(get_comments($args)) === 0;

        //check moderation status for inactive license
        if (!empty($this->base_call_result['ct_result']) && !empty($this->base_call_result['ct_result']->codes)) {
            $codes = $this->base_call_result['ct_result']->codes;
            $is_allowed_because_of_inactive_license = (
                is_array($codes) &&
                (
                    in_array('SERVICE_DISABLED', $codes) ||
                    in_array('TRIAL_EXPIRED', $codes) ||
                    in_array('KEY_NOT_FOUND', $codes)
                )
            );
        }

        // If moderation is required - exit with no changes
        if ( $wp_comment_moderation_enabled ) {
            return;
        }

        if (!$is_allowed_because_of_inactive_license) {
            add_action('comment_post', 'ct_set_real_user_badge_hash', 999, 2);
        }

        // if anu of options is disabled - standard WP recheck and exit
        if (
            !$wp_auto_approve_for_user_who_has_approved_comment ||
            !$clentalk_option_skip_moderation_for_first_comment
        ) {
            if (
                $this->rerunWPcheckCommentFunction()
            ) {
                $this->setCommentPreStatusAndModifyEmail('approved');
            } else {
                $this->setCommentPreStatusAndModifyEmail('not_approved');
            }
            return;
        }

        //both options enabled and is new author
        if ($is_new_author) {
            // if ct option is 1 and moderate is ok - skip WP logic
            // that checks $wp_auto_approve_for_user_who_has_approved_comment
            if (
                $this->apbct->data['moderate'] &&
                !$is_allowed_because_of_inactive_license
            ) {
                $this->setCommentPreStatusAndModifyEmail('approved');
            } else {
                // moderation disabled - standard WP check
                if (
                    $this->rerunWPcheckCommentFunction()
                ) {
                    $this->setCommentPreStatusAndModifyEmail('approved');
                } else {
                    $this->setCommentPreStatusAndModifyEmail('not_approved');
                }
            }
        } else {
            //not new author - standard WP check
            if (
                $this->rerunWPcheckCommentFunction()
            ) {
                $this->setCommentPreStatusAndModifyEmail('approved');
            } else {
                $this->setCommentPreStatusAndModifyEmail('not_approved');
            }
        }
    }

    public function doBlock($message)
    {
        $ct_result = $this->base_call_result['ct_result'];

        global $ct_comment, $ct_stop_words;
        $ct_comment = $message;
        $ct_stop_words = $ct_result->stop_words;
        /**
         * We have to increase priority to apply filters for comments
         * management after akismet fires
         **/
        $increased_priority = 0;
        if (apbct_is_plugin_active('akismet/akismet.php')) {
            $increased_priority = 5;
        }

        $err_text =
            '<center>'
            . ((defined('CLEANTALK_DISABLE_BLOCKING_TITLE') && CLEANTALK_DISABLE_BLOCKING_TITLE == true)
                ? ''
                : '<b style="color: #49C73B;">Clean</b><b style="color: #349ebf;">Talk.</b> ')
            . __('Spam protection', 'cleantalk-spam-protect')
            . "</center><br><br>\n"
            . $ct_result->comment;
        if ( ! $this->ct_jp_comments ) {
            $err_text .= '<script>setTimeout("history.back()", 5000);</script>';
        }

        // Terminate. Definitely spam.
        if ( $ct_result->stop_queue == 1 ) {
            wp_die($err_text, 'Blacklisted', array('response' => 200, 'back_link' => ! $this->ct_jp_comments));
        }

        // Terminate by user's setting.
        if ( $ct_result->spam == 3 ) {
            wp_die($err_text, 'Blacklisted', array('response' => 200, 'back_link' => ! $this->ct_jp_comments));
        }

        // Trash comment.
        if ( $ct_result->spam == 2 ) {
            add_filter('pre_comment_approved', 'ct_set_comment_spam', 997, 2);
            add_action('comment_post', 'ct_wp_trash_comment', 7 + $increased_priority, 2);
        }

        // Spam comment
        if ( $ct_result->spam == 1 ) {
            add_filter('pre_comment_approved', 'ct_set_comment_spam', 7, 2);
        }

        // Move to pending folder. Contains stop_words.
        if ( $ct_result->stop_words ) {
            add_filter('pre_comment_approved', 'ct_set_not_approved', 998, 2);
            add_action('comment_post', 'ct_mark_red', 8 + $increased_priority, 2);
        }

        add_action('comment_post', 'ct_die', 9 + $increased_priority, 2);
    }

    public function doFinalActions($argument)
    {
        global $apbct;

        if ( $apbct->settings['comments__remove_comments_links'] == 1 ) {
            $this->removeLinksFromComment();
        }

        // Change mail notification if license is out of date
        if ( $this->apbct->data['moderate'] == 0 ) {
            $apbct->sender_email = $this->wp_comment['comment_author_email'];
            $apbct->sender_ip    = Helper::ipGet('real');
            add_filter(
                'comment_moderation_text',
                'apbct_comment__Wordpress__changeMailNotification',
                100,
                2
            ); // Comment sent to moderation
            add_filter(
                'comment_notification_text',
                'apbct_comment__Wordpress__changeMailNotification',
                100,
                2
            ); // Comment approved
        }

        return $this->wp_comment;
    }

    /**
     * Check if user already have max comments published
     * @return bool
     */
    private function checkMaxCommentsPublishedByUser()
    {
        $result = false;
        if ( $this->apbct->settings['comments__check_comments_number'] && $this->wp_comment['comment_author_email'] ) {
            $args            = array(
                'author_email' => $this->wp_comment['comment_author_email'],
                'status'       => 'approve',
                'count'        => false,
                'number'       => $this->comments_check_number_needs_to_skip_request,
            );
            /** @psalm-suppress PossiblyInvalidArgument */
            $cnt             = count(get_comments($args));
            $result = $cnt >= $this->comments_check_number_needs_to_skip_request;
        }
        return $result;
    }

    /**
     * Get JSON of email addresses needs to be notified
     * @return false|string False if setting is disbale or invalid, JSON string otherwise
     */
    private function getEmailAddressesListToNotify()
    {
        // Send email notification for chosen groups of users
        if ( $this->apbct->settings['wp__comment_notify'] && ! empty($this->apbct->settings['wp__comment_notify__roles']) && $this->apbct->data['moderate'] ) {
            add_filter('notify_post_author', 'apbct_comment__Wordpress__doNotify', 100, 2);

            $users = get_users(array(
                'role__in' => $this->apbct->settings['wp__comment_notify__roles'],
                'fileds'   => array('user_email')
            ));
            $emails = [];
            if ( $users ) {
                add_filter(
                    'comment_notification_recipients',
                    'apbct_comment__Wordpress__changeMailNotificationRecipients',
                    100,
                    2
                );
                foreach ( $users as $user ) {
                    $emails[] = $user->user_email;
                }
                //todo check if referenced
                return json_encode($emails);
            }
        }
        return false;
    }

    /**
     * @param WP_User $current_user global
     * @param bool $ct_comment_done global sign of cleantalk comment action done
     * @return false|string String with reason to skip, false if no reasons
     */
    private function doSkipReason($current_user, $ct_comment_done)
    {
        // Skip processing admin.
        if ( in_array("administrator", $current_user->roles) ) {
            return __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__;
        }

        //do not hadle trackbacks on exclusions
        if ($this->wp_comment['comment_type'] !== 'trackback') {
            /**
             * Process main ruleset
             */
            if (
                apbct_is_user_enable() === false ||
                $this->apbct->settings['forms__comments_test'] == 0 ||
                $ct_comment_done ||
                (isset($_SERVER['HTTP_REFERER']) && stripos($_SERVER['HTTP_REFERER'], 'page=wysija_campaigns&action=editTemplate') !== false) ||
                (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-admin/') !== false)
            ) {
                return __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__;
            }

            /**
             * Check if wordpress integrated words/chars blacklists.
             */
            $wordpress_own_string_blacklists_result = apbct_wp_blacklist_check(
                $this->wp_comment['comment_author'],
                $this->wp_comment['comment_author_email'],
                $this->wp_comment['comment_author_url'],
                $this->wp_comment['comment_content'],
                Server::get('REMOTE_ADDR'),
                Server::get('HTTP_USER_AGENT')
            );

            // Go out if author in local blacklists, already stopped
            if ( $wordpress_own_string_blacklists_result === true ) {
                return __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__;
            }
        }

        return false;
    }

    /**
     * If need to set comment meta
     * @param $post_url
     * @return bool
     */
    private function doNeedSetMeta($post_url = null)
    {
        $is_new_user = false;
        if ( isset($this->wp_comment['comment_author_email']) ) {
            $approved_comments = get_comments(
                array('status' => 'approve', 'count' => true, 'author_email' => $this->wp_comment['comment_author_email'])
            );
            $is_new_user = $approved_comments == 0;
        }

        // Change comment flow only for new authors
        return $is_new_user || empty($post_url);
    }

    /**
     * Remove links from the comment
     * @return void
     */
    private function removeLinksFromComment()
    {
        $this->wp_comment['comment_content'] = preg_replace(
            "~(http|https|ftp|ftps)://(.*?)(\s|\n|[,.?!](\s|\n)|$)~",
            '[Link deleted]',
            $this->wp_comment['comment_content']
        );
    }

    private function rerunWPcheckCommentFunction()
    {
        $comment_author = isset($this->wp_comment['comment_author']) ? $this->wp_comment['comment_author'] : '';
        $comment_author_email = isset($this->wp_comment['comment_author_email']) ? $this->wp_comment['comment_author_email'] : '';
        $comment_author_url = isset($this->wp_comment['comment_author_url']) ? $this->wp_comment['comment_author_url'] : '';
        $comment_content = isset($this->wp_comment['comment_content']) ? $this->wp_comment['comment_content'] : '';
        $comment_author_IP = isset($this->wp_comment['comment_author_IP']) ? $this->wp_comment['comment_author_IP'] : '';
        $comment_agent = isset($this->wp_comment['comment_agent']) ? $this->wp_comment['comment_agent'] : '';
        $comment_type = isset($this->wp_comment['comment_type']) ? $this->wp_comment['comment_type'] : '';

        $check_result = check_comment(
            $comment_author,
            $comment_author_email,
            $comment_author_url,
            $comment_content,
            $comment_author_IP,
            $comment_agent,
            $comment_type
        );

        return $check_result;
    }

    private function setCommentPreStatusAndModifyEmail($status)
    {
        if ($status !== 'approved' && $status !== 'not_approved') {
            return;
        }
        if ( $status === 'approved' ) {
            add_filter('pre_comment_approved', 'ct_set_approved', 999, 2);
        } else {
            add_filter('pre_comment_approved', 'ct_set_not_approved', 999, 2);
        }

        // Modify the email notification
        add_filter(
            'comment_notification_text',
            'apbct_comment__wordpress__show_blacklists',
            100,
            2
        ); // Add two blacklist links: by email and IP
    }
}
