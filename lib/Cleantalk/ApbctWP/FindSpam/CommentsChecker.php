<?php

namespace Cleantalk\ApbctWP\FindSpam;

use Cleantalk\ApbctWP\API;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\Variables\Post;

class CommentsChecker extends Checker
{
    public function __construct()
    {
        parent::__construct();

        $this->page_title       = esc_html__('Check comments for spam', 'cleantalk-spam-protect');
        $this->page_script_name = 'edit-comments.php';
        $this->page_slug        = 'spam';

        // Preparing data
        if ( Cookie::get('ct_paused_comments_check') ) {
            $prev_check = json_decode(stripslashes(Cookie::get('ct_paused_comments_check')), true);
            $prev_check_from = $prev_check_till = '';
            if (
                ! empty($prev_check['from']) && ! empty($prev_check['till']) &&
                preg_match('/^[a-zA-Z]{3}\s{1}\d{1,2}\s{1}\d{4}$/', $prev_check['from']) &&
                preg_match('/^[a-zA-Z]{3}\s{1}\d{1,2}\s{1}\d{4}$/', $prev_check['till'])
            ) {
                $prev_check_from = $prev_check['from'];
                $prev_check_till = $prev_check['till'];
            }
        }

        wp_enqueue_script(
            'ct_comments_checkspam',
            APBCT_JS_ASSETS_PATH . '/cleantalk-comments-checkspam.min.js',
            array('jquery', 'jquery-ui-datepicker'),
            APBCT_VERSION
        );
        wp_localize_script('ct_comments_checkspam', 'ctCommentsCheck', array(
            'ct_ajax_nonce'            => wp_create_nonce('ct_secret_nonce'),
            'ct_prev_accurate'         => ! empty($prev_check['accurate']) ? true : false,
            'ct_prev_from'             => ! empty($prev_check_from) ? $prev_check_from : false,
            'ct_prev_till'             => ! empty($prev_check_till) ? $prev_check_till : false,
            'ct_timeout_confirm'       => __(
                'Failed from timeout. Going to check comments again.',
                'cleantalk-spam-protect'
            ),
            'ct_confirm_trash_all'     => __('Trash all spam comments from the list?', 'cleantalk-spam-protect'),
            'ct_confirm_spam_all'      => __('Mark as spam all comments from the list?', 'cleantalk-spam-protect'),
            'ct_comments_added_after'  => __('comments', 'cleantalk-spam-protect'),
            'ct_status_string'         => __(
                'Checked %s, found %s spam comments and %s non-checkable comments (without IP and email).',
                'cleantalk-spam-protect'
            ),
            'ct_status_string_warning' => '<p>' . __(
                'Please do backup of WordPress database before delete any accounts!',
                'cleantalk-spam-protect'
            ) . '</p>',
            'start'                    => ! empty(Cookie::get('ct_comments_start_check')),
        ));
    }

    public function getCurrentScanPage()
    {
        $this->list_table = new \Cleantalk\ApbctWP\FindSpam\ListTable\CommentsScan();

        $this->getCurrentScanPanel($this);
        echo '<form action="" method="POST">';
        $this->list_table->display();
        echo '</form>';
        $this->getFooter();
    }

    public function getSpamLogsPage()
    {
        $this->list_table = new \Cleantalk\ApbctWP\FindSpam\ListTable\CommentsLogs();

        echo '<form action="" method="POST">';
        $this->list_table->display();
        echo '</form>';
    }

    /**
     * Getting a count of total comments of the website and return formatted string about this.
     *
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function getCountText()
    {
        $res = wp_count_comments();

        if ( $res->all ) {
            $text = sprintf(esc_html__('Total count of comments: %s.', 'cleantalk-spam-protect'), $res->all);
        } else {
            $text = esc_html__('No comments found.', 'cleantalk-spam-protect');
        }

        return $text;
    }

    /**
     * Get date last checked comment or date of the first comment
     *
     * @return string   date "M j Y"
     */
    public static function lastCheckDate()
    {
        global $wpdb;
        $query = "SELECT * FROM " . APBCT_SPAMSCAN_LOGS . " WHERE scan_type = 'comments' ORDER BY start_time DESC";
        $res   = $wpdb->get_row($query, ARRAY_A);

        if ( $res ) {
            return date("M j Y", strtotime($res['start_time']));
        } else {
            $params        = array(
                'fields'  => 'ids',
                'orderby' => 'comment_date_gmt',
                'order'   => 'ASC',
                'number'  => 1
            );
            $first_comment = get_comments($params);

            return get_comment_date("M j Y", current($first_comment));
        }
    }

    public static function ctAjaxCheckComments()
    {
        check_ajax_referer('ct_secret_nonce', 'security');

        global $wpdb, $apbct;

        $sql_where = "WHERE NOT comment_approved = 'spam'";
        $sql_where .= " AND comment_type = 'comment'";
        if ( Post::get('from') && Post::get('till') ) {
            $from_date = date('Y-m-d', intval(strtotime(Post::get('from'))));
            $till_date = date('Y-m-d', intval(strtotime(Post::get('till'))));

            $sql_where .= " AND comment_date_gmt > '$from_date 00:00:00' AND comment_date_gmt < '$till_date 23:59:59'";
        }

        $offset = Cookie::get('apbct_check_comments_offset') ? (int) Cookie::get('apbct_check_comments_offset') : 0;

        $query = "SELECT comment_ID, comment_date_gmt, comment_author_IP, comment_author_email
                       FROM $wpdb->comments
                       $sql_where
                       ORDER BY comment_ID
                       LIMIT 100 OFFSET " . $offset;

        $c = $wpdb->get_results($query);

        $check_result = array(
            'end'     => 0,
            'checked' => 0,
            'spam'    => 0,
            'bad'     => 0,
            'error'   => 0,
            'total'   => wp_count_comments()->total_comments,
        );

        if ( count($c) > 0 ) {
            // Converting $c to objects
            if ( is_array($c[0]) ) {
                foreach ( $c as $key => $value ) {
                    $c[$key] = (object)$value;
                }
            }

            if ( ! empty(Post::get('accurate_check')) ) {
                // Leaving comments only with first comment's date. Unsetting others.

                foreach ( $c as $comment_index => $comment ) {
                    if ( ! isset($curr_date) ) {
                        $curr_date = (substr($comment->comment_date_gmt, 0, 10) ?: '');
                    }

                    if ( substr($comment->comment_date_gmt, 0, 10) != $curr_date ) {
                        unset($c[$comment_index]);
                    }
                }
            }

            // Checking comments IP/Email. Gathering $data for check.
            $data = array();
            foreach ( $c as $i => $iValue ) {
                $curr_ip    = $iValue->comment_author_IP;
                $curr_email = $iValue->comment_author_email;

                // Check for identity
                $curr_ip    = preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $curr_ip) === 1 ? $curr_ip : null;
                $curr_email = preg_match('/^\S+@\S+\.\S+$/', $curr_email) === 1 ? $curr_email : null;

                if ( empty($curr_ip) && empty($curr_email) ) {
                    $check_result['bad']++;
                    unset($c[$i]);
                } else {
                    if ( ! empty($curr_ip) ) {
                        $data[] = $curr_ip;
                    }
                    if ( ! empty($curr_email) ) {
                        $data[] = $curr_email;
                    }
                    // Patch for empty IP/Email
                    $iValue->comment_author_IP    = empty($curr_ip) ? 'none' : $curr_ip;
                    $iValue->comment_author_email = empty($curr_email) ? 'none' : $curr_email;
                }
            }

            // save count bad comments to State:data
            $apbct->data['count_bad_comments'] += $check_result['bad'];
            $apbct->saveData();

            // Recombining after checking and unsetting
            $c = array_values($c);

            // Drop if data empty and there's no comments to check
            if ( count($data) === 0 ) {
                if ( (int) Post::get('unchecked') === 0 ) {
                    $check_result['end'] = 1;
                }
                print json_encode($check_result);
                die();
            }

            $result = API::methodSpamCheckCms(
                $apbct->api_key,
                $data,
                ! empty(Post::get('accurate_check')) ? $curr_date : null
            );

            if ( empty($result['error']) ) {
                foreach ( $c as $iValue ) {
                    $mark_spam_ip    = false;
                    $mark_spam_email = false;

                    $check_result['checked']++;

                    $uip = $iValue->comment_author_IP;
                    $uim = $iValue->comment_author_email;

                    if ( isset($result[$uip]) && isset($result[$uim]['appears']) && $result[$uip]['appears'] == 1 ) {
                        $mark_spam_ip = true;
                    }

                    if ( isset($result[$uim]) && isset($result[$uim]['appears']) && $result[$uim]['appears'] == 1 ) {
                        $mark_spam_email = true;
                    }

                    if ( $mark_spam_ip || $mark_spam_email ) {
                        $check_result['spam']++;
                        update_comment_meta($iValue->comment_ID, 'ct_marked_as_spam', '1');
                    }
                }

                // save count checked comments to State:data
                $apbct->data['count_checked_comments'] += $check_result['checked'];
                $apbct->saveData();

                print json_encode($check_result);
            } else {
                $check_result['error']         = 1;
                $check_result['error_message'] = $result['error'];
                echo json_encode($check_result);
            }
        } else {
            $check_result['end'] = 1;

            $log_data = static::getLogData();
            static::writeSpamLog(
                'comments',
                date("Y-m-d H:i:s"),
                $log_data['checked'],
                $log_data['spam'],
                $log_data['bad']
            );

            print json_encode($check_result);
        }

        die;
    }

    public static function ctAjaxInfo($direct_call = false)
    {
        global $wpdb, $apbct;

        if ( ! $direct_call ) {
            check_ajax_referer('ct_secret_nonce', 'security');
        }

        $cnt_checked      = $apbct->data['count_checked_comments'];

        // Spam comments
        $params_spam = array(
            'count'   => true,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => 'ct_marked_as_spam',
                    'compare' => '=',
                    'value'   => 1
                )
            ),
        );
        $cnt_spam = get_comments($params_spam);

        // Bad comments (without IP and Email)
        $cnt_bad      = $apbct->data['count_bad_comments'];

        /**
         * Total comments
         */
        $total_comments = wp_count_comments()->total_comments;

        $return = array(
            'message' => '',
            'spam'    => $cnt_spam,
            'checked' => $cnt_checked,
            'bad'     => $cnt_bad,
            'total'   => $total_comments
        );

        if ( ! $direct_call ) {
            $return['message'] .= sprintf(
                esc_html__(
                    'Checked %s, found %s spam comments and %s non-checkable comments (without IP and email)',
                    'cleantalk-spam-protect'
                ),
                $cnt_checked,
                $cnt_spam,
                $cnt_bad
            );
        } else {
            $query = "SELECT * FROM " . APBCT_SPAMSCAN_LOGS . " WHERE scan_type = 'comments' ORDER BY start_time DESC";
            $res   = $wpdb->get_row($query, ARRAY_A);

            if ( $res ) {
                $return['message'] .= sprintf(
                    __(
                        "Last check %s: checked %s comments, found %s spam comments and %s non-checkable comments (without IP and email).",
                        'cleantalk-spam-protect'
                    ),
                    self::lastCheckDate(),
                    $cnt_checked,
                    $cnt_spam,
                    $cnt_bad
                );
            } else {
                // Never checked
                $return['message'] = esc_html__('Never checked yet or no new spam.', 'cleantalk-spam-protect');
            }
        }

        $backup_notice      = '&nbsp;';
        $spam_system_notice = '&nbsp;';
        if ( $cnt_spam > 0 ) {
            $backup_notice      = __(
                "Please do backup of WordPress database before delete any accounts!",
                'cleantalk-spam-protect'
            );
            $spam_system_notice = __(
                "Results are based on the decision of our spam checking system and do not give a complete guarantee that these comments are spam.",
                'cleantalk-spam-protect'
            );
        }
        $return['message'] .= "<p>$backup_notice</p><p>$spam_system_notice</p>";

        if ( $direct_call ) {
            return $return['message'];
        } else {
            echo json_encode($return);
            die();
        }
    }

    public static function ctAjaxClearComments()
    {
        global $wpdb ,$apbct;

        check_ajax_referer('ct_secret_nonce', 'security');

        $apbct->data['count_checked_comments'] = 0;
        $apbct->data['count_bad_comments'] = 0;
        $apbct->saveData();

        $wpdb->query("DELETE FROM {$wpdb->commentmeta} WHERE meta_key IN ('ct_marked_as_spam')");
        die;
    }

    private static function getLogData()
    {
        global $apbct;

        $cnt_checked   = $apbct->data['count_checked_comments'];

        // Spam comments
        $params_spam = array(
            'count'   => true,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => 'ct_marked_as_spam',
                    'compare' => '=',
                    'value'   => 1
                )
            ),
        );
        $cnt_spam = get_comments($params_spam);

        $cnt_bad = $apbct->data['count_bad_comments'];

        return array(
            'spam'    => $cnt_spam,
            'checked' => $cnt_checked,
            'bad'     => $cnt_bad,
        );
    }

    public static function ctAjaxTrashAll()
    {
        check_ajax_referer('ct_secret_nonce', 'security');

        $args_spam = array(
            'number'     => 100,
            'meta_query' => array(
                array(
                    'key'     => 'ct_marked_as_spam',
                    'value'   => '1',
                    'compare' => 'NUMERIC'
                )
            )
        );
        $c_spam    = get_comments($args_spam);

        $args_spam = array(
            'count'      => true,
            'meta_query' => array(
                array(
                    'key'     => 'ct_marked_as_spam',
                    'value'   => '1',
                    'compare' => 'NUMERIC'
                )
            )
        );
        $cnt_all   = get_comments($args_spam);

        foreach ( $c_spam as $iValue ) {
            wp_trash_comment($iValue->comment_ID);
            usleep(10000);
        }
        /** @psalm-suppress InvalidArgument */
        print $cnt_all;
        die();
    }

    public static function ctAjaxSpamAll()
    {
        check_ajax_referer('ct_secret_nonce', 'security');

        $args_spam = array(
            'number'     => 100,
            'meta_query' => array(
                array(
                    'key'     => 'ct_marked_as_spam',
                    'value'   => '1',
                    'compare' => 'NUMERIC'
                )
            )
        );
        $c_spam    = get_comments($args_spam);

        $args_spam = array(
            'count'      => true,
            'meta_query' => array(
                array(
                    'key'     => 'ct_marked_as_spam',
                    'value'   => '1',
                    'compare' => 'NUMERIC'
                )
            )
        );
        $cnt_all   = get_comments($args_spam);

        foreach ( $c_spam as $iValue ) {
            wp_spam_comment($iValue->comment_ID);
            usleep(10000);
        }
        /** @psalm-suppress InvalidArgument */
        print $cnt_all;
        die();
    }
}
