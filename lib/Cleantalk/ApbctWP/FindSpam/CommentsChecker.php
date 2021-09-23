<?php

namespace Cleantalk\ApbctWP\FindSpam;

use Cleantalk\ApbctWP\API;

class CommentsChecker extends Checker
{
    public function __construct()
    {
        parent::__construct();

        $this->page_title       = esc_html__('Check comments for spam', 'cleantalk-spam-protect');
        $this->page_script_name = 'edit-comments.php';
        $this->page_slug        = 'spam';

        // Preparing data
        if ( ! empty($_COOKIE['ct_paused_comments_check']) ) {
            $prev_check = json_decode(stripslashes($_COOKIE['ct_paused_comments_check']), true);
        }

        wp_enqueue_script(
            'ct_comments_checkspam',
            plugins_url('/cleantalk-spam-protect/js/cleantalk-comments-checkspam.min.js'),
            array('jquery', 'jqueryui'),
            APBCT_VERSION
        );
        wp_localize_script('ct_comments_checkspam', 'ctCommentsCheck', array(
            'ct_ajax_nonce'            => wp_create_nonce('ct_secret_nonce'),
            'ct_prev_accurate'         => ! empty($prev_check['accurate']) ? true : false,
            'ct_prev_from'             => ! empty($prev_check['from']) ? $prev_check['from'] : false,
            'ct_prev_till'             => ! empty($prev_check['till']) ? $prev_check['till'] : false,
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
            'start'                    => ! empty($_COOKIE['ct_comments_start_check']),
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

        if ( isset($_POST['from'], $_POST['till']) ) {
            $from_date = date('Y-m-d', intval(strtotime($_POST['from'])));
            $till_date = date('Y-m-d', intval(strtotime($_POST['till'])));
        }

        // Getting comments 100 unchecked comments
        if ( isset($_COOKIE['ct_comments_safe_check']) ) {
            $c = $wpdb->get_results(
                "
			SELECT comment_ID, comment_date_gmt, comment_author_IP, comment_author_email
			FROM {$wpdb->comments} as comm
			WHERE 
				(comm.comment_approved = '1' OR comm.comment_approved = '0')
				AND NOT EXISTS(
				SELECT comment_id, meta_key
					FROM {$wpdb->commentmeta} as meta
					WHERE comm.comment_ID = meta.comment_id AND (meta_key = 'ct_checked' OR meta_key = 'ct_bad')
			)
			ORDER BY comment_date_gmt
			LIMIT 100",
                ARRAY_A
            );
        } else {
            $params = array(
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'ct_checked_now',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key'     => 'ct_checked',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key'     => 'ct_bad',
                        'compare' => 'NOT EXISTS'
                    )
                ),
                'orderby'    => 'comment_date_gmt',
                'order'      => 'ASC',
                'number'     => 100
            );
            if ( isset($from_date, $till_date) ) {
                $params['date_query'] = array(
                    'column'    => 'comment_date_gmt',
                    'after'     => $from_date,
                    'before'    => $till_date,
                    'inclusive' => true,
                );
            }
            $c = get_comments($params);
        }

        $check_result = array(
            'end'     => 0,
            'checked' => 0,
            'spam'    => 0,
            'bad'     => 0,
            'error'   => 0
        );

        if ( count($c) > 0 ) {
            // Converting $c to objects
            if ( is_array($c[0]) ) {
                foreach ( $c as $key => $value ) {
                    $c[$key] = (object)$value;
                }
            }

            if ( ! empty($_POST['accurate_check']) ) {
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
                    update_comment_meta($iValue->comment_ID, 'ct_bad', '1');
                    update_comment_meta($iValue->comment_ID, 'ct_checked', '1');
                    update_comment_meta($iValue->comment_ID, 'ct_checked_now', '1');
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

            // Recombining after checking and unsetting
            $c = array_values($c);

            // Drop if data empty and there's no comments to check
            if ( count($data) === 0 ) {
                if ( $_POST['unchecked'] === 0 ) {
                    $check_result['end'] = 1;
                }
                print json_encode($check_result);
                die();
            }

            $result = API::methodSpamCheckCms(
                $apbct->api_key,
                $data,
                ! empty($_POST['accurate_check']) ? $curr_date : null
            );

            if ( empty($result['error']) ) {
                foreach ( $c as $iValue ) {
                    $mark_spam_ip    = false;
                    $mark_spam_email = false;

                    $check_result['checked']++;
                    update_comment_meta($iValue->comment_ID, 'ct_checked', date("Y-m-d H:m:s"));
                    update_comment_meta($iValue->comment_ID, 'ct_checked_now', date("Y-m-d H:m:s"), true);

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
        global $wpdb;

        if ( ! $direct_call ) {
            check_ajax_referer('ct_secret_nonce', 'security');
        }

        // Checked comments
        $params_checked   = array(
            'meta_key' => 'ct_checked_now',
            'orderby'  => 'ct_checked_now'
        );
        $checked_comments = new \WP_Comment_Query($params_checked);
        $cnt_checked      = count($checked_comments->get_comments());

        // Spam comments
        $params_spam   = array(
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => 'ct_marked_as_spam',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key'     => 'ct_checked_now',
                    'compare' => 'EXISTS'
                ),
            ),
        );
        $spam_comments = new \WP_Comment_Query($params_spam);
        $cnt_spam      = count($spam_comments->get_comments());

        // Bad comments (without IP and Email)
        $params_bad   = array(
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => 'ct_bad',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key'     => 'ct_checked_now',
                    'compare' => 'EXISTS'
                ),
            ),
        );
        $bad_comments = new \WP_Comment_Query($params_bad);
        $cnt_bad      = count($bad_comments->get_comments());

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
        global $wpdb;

        check_ajax_referer('ct_secret_nonce', 'security');

        $wpdb->query("DELETE FROM {$wpdb->commentmeta} WHERE meta_key IN ('ct_checked_now')");

        if ( isset($_POST['from']) && isset($_POST['till']) ) {
            if (
                preg_match('/[a-zA-Z]{3}\s{1}\d{1,2}\s{1}\d{4}/', $_POST['from']) &&
                preg_match('/[a-zA-Z]{3}\s{1}\d{1,2}\s{1}\d{4}/', $_POST['till'])
            ) {
                $from = date('Y-m-d', intval(strtotime($_POST['from']))) . ' 00:00:00';
                $till = date('Y-m-d', intval(strtotime($_POST['till']))) . ' 23:59:59';

                $wpdb->query(
                    "DELETE FROM {$wpdb->commentmeta} WHERE 
                meta_key IN ('ct_checked','ct_marked_as_spam','ct_bad') 
                AND meta_value >= '{$from}' 
                AND meta_value <= '{$till}';"
                );
                die();
            } else {
                $wpdb->query(
                    "DELETE FROM {$wpdb->commentmeta} WHERE 
                meta_key IN ('ct_checked','ct_marked_as_spam','ct_bad')"
                );
                die();
            }
        }
    }

    private static function getLogData()
    {
        // Checked users
        $params_spam   = array(
            'meta_key' => 'ct_checked_now',
        );
        $spam_comments = new \WP_Comment_Query($params_spam);
        $cnt_checked   = count($spam_comments->get_comments());

        // Spam users
        $params_spam   = array(
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => 'ct_marked_as_spam',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key'     => 'ct_checked_now',
                    'compare' => 'EXISTS'
                ),
            ),
        );
        $spam_comments = new \WP_Comment_Query($params_spam);
        $cnt_spam      = count($spam_comments->get_comments());

        // Bad users (without IP and Email)
        $params_bad    = array(
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => 'ct_bad',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key'     => 'ct_checked_now',
                    'compare' => 'EXISTS'
                ),
            ),
        );
        $spam_comments = new \WP_Comment_Query($params_bad);
        $cnt_bad       = count($spam_comments->get_comments());

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
