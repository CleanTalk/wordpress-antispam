<?php

namespace Cleantalk\ApbctWP\FindSpam;

use Cleantalk\ApbctWP\AJAXService;
use Cleantalk\ApbctWP\ApbctEnqueue;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\Common\TT;

class CommentsChecker extends Checker
{
    public function __construct()
    {
        global $apbct;
        parent::__construct();

        $this->page_title       = esc_html__('Check comments for spam', 'cleantalk-spam-protect');
        $this->page_script_name = 'edit-comments.php';
        $this->page_slug        = 'spam';

        // Preparing data
        if ( Cookie::get('ct_paused_comments_check') ) {
            $prev_check = json_decode(stripslashes(TT::toString(Cookie::get('ct_paused_comments_check'))), true);
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

        ApbctEnqueue::getInstance()->js('cleantalk-comments-checkspam.js', array('jquery', 'jquery-ui-datepicker'));
        wp_localize_script('cleantalk-comments-checkspam-js', 'ctCommentsCheck', array(
            'ct_ajax_nonce'            => $apbct->ajax_service->getAdminNonce(),
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
                "Checked %s comments total (excluding admins), found %s spam comments and %s non-checkable comments (no IP and email found).",
                'cleantalk-spam-protect'
            ),
            'ct_status_string_warning' => '<p>' . __(
                'Please do backup of WordPress database before delete any accounts!',
                'cleantalk-spam-protect'
            ) . '</p>',
            'start'                    => ! empty(Cookie::get('ct_comments_start_check')),
        ));
    }

    /**
     * Get all comments from DB that:
     * * not marked as spam
     * * not marked as trash
     * * comment type is in list of 'comment', 'review', 'trackback', 'pings'
     * * comment_ID has no comment_meta with meta_key 'ct_marked_as_approved'
     *
     * If date_from and date_till are set, get comments from this period (used in accurate check).
     *
     * After DB request, method filter data to skip comments:
     * * with user_id that has role from skip_roles
     * * uncheckable comments - without comment_author_IP and comment_author_email
     * @return object[] Assoc array of db result object:
     * <code>
     * array(
     *  0 => object
     *    ->comment_ID int,
     *    ->comment_date_gmt string,
     *    ->comment_author_IP string,
     *    ->comment_author_email string,
     *    ->user_id int
     *  ,
     * ...
     * );
     * </code>
     * @example Example
     */
    private static function getAllComments(CommentsScanParameters $commentsScanParameters)
    {
        global $wpdb;

        $amount = $commentsScanParameters->getAmount();
        $skip_roles = $commentsScanParameters->getSkipRoles();
        $offset = $commentsScanParameters->getOffset();
        $date_from = $commentsScanParameters->getFrom();
        $date_till = $commentsScanParameters->getTill();
        $sql_where = "WHERE NOT comment_approved = 'spam' AND NOT comment_approved = 'trash'";
        $sql_where .= " AND ( comment_type = 'comment' OR comment_type = 'review' OR comment_type = 'trackback' OR comment_type = 'pings' )";
        $sql_where .= " AND {$wpdb->comments}.comment_ID NOT IN (
            SELECT {$wpdb->commentmeta}.comment_id FROM {$wpdb->commentmeta} WHERE meta_key = 'ct_marked_as_approved')";
        if ($date_from && $date_till) {
            $date_from = date('Y-m-d', (int) strtotime($date_from)) . ' 00:00:00';
            $date_till = date('Y-m-d', (int) strtotime($date_till)) . ' 23:59:59';

            $sql_where .= " AND $wpdb->comments.comment_date_gmt >= '$date_from' AND $wpdb->comments.comment_date_gmt <= '$date_till'";
        }

        $query = "SELECT {$wpdb->comments}.comment_ID, {$wpdb->comments}.comment_date_gmt,
            {$wpdb->comments}.comment_author_IP, {$wpdb->comments}.comment_author_email, {$wpdb->comments}.user_id
            FROM {$wpdb->comments}
            {$sql_where}
            ORDER BY {$wpdb->comments}.comment_ID ASC
            LIMIT %d OFFSET %d";

        $comments = $wpdb->get_results($wpdb->prepare($query, $amount, $offset));

        if (!$comments) {
            $comments = array();
        }

        // removed skip_roles and return $comments
        $comments =  self::removeSkipRoles($comments, $skip_roles);

        // removed comments without comment_author_IP and comment_author_email
        $comments =  self::removeCommentsWithoutIPEmail($comments);

        return $comments;
    }

    /**
     * @param array $comments
     * @param array $skip_roles
     *
     * @return array
     */
    private static function removeSkipRoles(array $comments, array $skip_roles)
    {
        foreach ($comments as $index => $comment) {
            if (!$comment->user_id) {
                continue;
            }
            $user_meta  = get_userdata($comment->user_id);
            if ( !($user_meta instanceof \WP_User) ) {
                return $comments;
            }
            $user_roles = $user_meta->roles;
            foreach ($user_roles as $user_role) {
                if (in_array($user_role, $skip_roles, true)) {
                    unset($comments[$index]);
                    break;
                }
            }
        }

        return $comments;
    }

    /**
     * @param array $comments
     *
     * @return array
     */
    private static function removeCommentsWithoutIPEmail(array $comments)
    {
        foreach ($comments as $index => $comment) {
            $comment_ip = ! empty($comment->comment_author_IP) ? trim($comment->comment_author_IP) : false;
            $comment_email = ! empty($comment->comment_author_email) ? trim($comment->comment_author_email) : false;

            // Validate IP and Email
            $comment_ip = filter_var($comment_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
            $comment_email = filter_var($comment_email, FILTER_VALIDATE_EMAIL);

            if (!$comment_ip && !$comment_email) {
                update_comment_meta($comment->comment_ID, 'ct_bad', '1', true);
                unset($comments[$index]);
            }
        }

        return $comments;
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
            $unicode_star = '&#42;';
            $text         = sprintf(
                esc_html__('Total count of comments: %s.', 'cleantalk-spam-protect'),
                $res->all . $unicode_star
            );
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
            return date("Y-m-d", strtotime($res['start_time']));
        } else {
            $params        = array(
                'fields'  => 'ids',
                'orderby' => 'comment_date_gmt',
                'order'   => 'ASC',
                'number'  => 1
            );
            $first_comment = get_comments($params);

            if ( ! is_array($first_comment) || empty($first_comment) ) {
                return 'unknown';
            }

            return get_comment_date("Y-m-d", current($first_comment));
        }
    }

    public static function ctAjaxCheckComments()
    {
        AJAXService::checkNonceRestrictingNonAdmins('security');

        $commentScanParameters = new CommentsScanParameters($_POST);

        // Set type checking
        if ($commentScanParameters->getAccurateCheck() && ($commentScanParameters->getFrom() && $commentScanParameters->getTill())) {
            self::startAccurateChecking($commentScanParameters);
        } elseif (!$commentScanParameters->getAccurateCheck() && ($commentScanParameters->getFrom() && $commentScanParameters->getTill())) {
            self::startCommonChecking($commentScanParameters);
        } else {
            self::startCommonChecking($commentScanParameters);
        }
    }

    public static function ctAjaxInfo($direct_call = false)
    {
        global $wpdb, $apbct;

        if ( ! $direct_call ) {
            AJAXService::checkNonceRestrictingNonAdmins('security');
        }

        $cnt_checked = TT::toInt($apbct->data['count_checked_comments']);

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

        if (!is_int($cnt_spam)) {
            $cnt_spam = 'unknown';
        } else {
            $cnt_spam = TT::toInt($cnt_spam);
        }

        // Bad comments (without IP and Email)
        $cnt_bad = self::getCountBadComments();

        /**
         * Total comments
         */
        $total_comments = wp_count_comments()->all;

        $return = array(
            'message' => '',
            'spam'    => $cnt_spam,
            'checked' => $cnt_checked,
            'bad'     => $cnt_bad,
            'total'   => $total_comments
        );

        $unicode_star = '&#42;';

        if ( ! $direct_call ) {
            $return['message'] .= sprintf(
                esc_html__(
                    "Checked %s comments total (excluding admins), found %s spam comments and %s non-checkable comments (no IP and email found).",
                    'cleantalk-spam-protect'
                ),
                TT::toString($cnt_checked) . $unicode_star . $unicode_star,
                $cnt_spam,
                $cnt_bad
            );
        } else {
            $query = "SELECT * FROM " . APBCT_SPAMSCAN_LOGS . " WHERE scan_type = 'comments' ORDER BY start_time DESC";
            $res   = $wpdb->get_row($query, ARRAY_A);

            if ( $res ) {
                $return['message'] .= sprintf(
                    __(
                        "Last check %s: checked %s comments, found %s spam comments and %s non-checkable comments (no IP and email found).",
                        'cleantalk-spam-protect'
                    ),
                    self::lastCheckDate(),
                    TT::toString($cnt_checked) . $unicode_star . $unicode_star,
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

        AJAXService::checkNonceRestrictingNonAdmins('security');

        $apbct->data['count_checked_comments'] = 0;
        $apbct->saveData();

        $wpdb->query("DELETE FROM {$wpdb->commentmeta} WHERE meta_key IN ('ct_marked_as_spam')");
        die('OK');
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

        $cnt_bad = self::getCountBadComments();

        return array(
            'spam'    => $cnt_spam,
            'checked' => $cnt_checked,
            'bad'     => $cnt_bad,
        );
    }

    public static function ctAjaxTrashAll()
    {
        AJAXService::checkNonceRestrictingNonAdmins('security');

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
        if ( !is_array($c_spam) ) {
            $c_spam = array();
        }

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
            if ($iValue instanceof \WP_Comment) {
                wp_trash_comment($iValue);
                usleep(10000);
            }
        }
        /** @psalm-suppress InvalidArgument */
        print $cnt_all;
        die();
    }

    public static function ctAjaxSpamAll()
    {
        AJAXService::checkNonceRestrictingNonAdmins('security');

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
        if (!is_array($c_spam)) {
            $c_spam = array();
        }

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
            if ($iValue instanceof \WP_Comment) {
                wp_trash_comment($iValue);
                usleep(10000);
            }
        }
        /** @psalm-suppress InvalidArgument */
        print $cnt_all;
        die();
    }

    /**
     * Getting count bas comments without IP and Email
     *
     * @return int
     */
    public static function getCountBadComments()
    {
        global $wpdb;

        $sql = "SELECT
                COUNT(`comment_id`)
                FROM $wpdb->commentmeta
                where `meta_key`='ct_bad'";

        $count_bad = $wpdb->get_var($sql);

        if (is_null($count_bad)) {
            return 0;
        }

        return (int) $count_bad;
    }

    /**
     * All comments checking
     *
     * @param CommentsScanParameters $commentScanParameters
     *
     * @return void
     */
    public static function startCommonChecking(CommentsScanParameters $commentScanParameters)
    {
        global $apbct;
        $comments = self::getAllComments($commentScanParameters);

        if (!$comments) {
            CommentsScanResponse::getInstance()->setEnd(1);

            $log_data = static::getLogData();
            static::writeSpamLog(
                'comments',
                current_datetime()->format("Y-m-d H:i:s"),
                $log_data['checked'],
                $log_data['spam'],
                $log_data['bad']
            );

            die(CommentsScanResponse::getInstance()->toJson());
        }

        $ips_emails_data = self::getIPEmailsData($comments);

        $result = \Cleantalk\ApbctWP\API::methodSpamCheckCms(
            TT::toString($apbct->api_key),
            $ips_emails_data
        );

        $error = !is_array($result)
            ? 'Unknown API error'
            : null;
        $error = !empty($result['error'])
            ? TT::getArrayValueAsString($result, 'error')
            : $error;

        if ( !is_null($error) ) {
            CommentsScanResponse::getInstance()->setError(1);
            CommentsScanResponse::getInstance()->setErrorMessage($error);
        } else {
            $onlySpammers = self::getSpammersFromResultAPI(TT::toArray($result));
            $marked_comment_ids = [];

            foreach ($comments as $comment) {
                if (
                    ! in_array($comment->comment_ID, $marked_comment_ids, true) &&
                    (in_array($comment->comment_author_IP, $onlySpammers, true) ||
                     in_array($comment->comment_author_email, $onlySpammers, true))
                ) {
                    $marked_comment_ids[] = $comment->comment_ID;
                    update_comment_meta($comment->comment_ID, 'ct_marked_as_spam', '1', true);
                }
            }

            // Count spam
            CommentsScanResponse::getInstance()->updateSpam(count($marked_comment_ids));
        }

        // Count bad comments
        CommentsScanResponse::getInstance()->setBad((int)self::getCountBadComments());
        // Count checked comments
        CommentsScanResponse::getInstance()->setChecked(count($comments));
        // save count checked comments to State:data
        $apbct->data['count_checked_comments'] += count($comments);
        $apbct->saveData();

        die(CommentsScanResponse::getInstance()->toJson());
    }

    /**
     * Accurate comment checking
     *
     * @param CommentsScanParameters $commentScanParameters
     *
     * @return void
     */
    private static function startAccurateChecking(CommentsScanParameters $commentScanParameters)
    {
        global $apbct;
        $comments = self::getAllComments($commentScanParameters);

        if (!$comments) {
            CommentsScanResponse::getInstance()->setEnd(1);

            $log_data = static::getLogData();
            static::writeSpamLog(
                'comments',
                current_datetime()->format("Y-m-d H:i:s"),
                $log_data['checked'],
                $log_data['spam'],
                $log_data['bad']
            );

            die(CommentsScanResponse::getInstance()->toJson());
        }

        $comments_grouped_by_date = array();

        foreach ($comments as $index => $comment) {
            if (!empty($comment->comment_date_gmt)) {
                $comment_date = date('Y-m-d', strtotime($comment->comment_date_gmt));
                $comments_grouped_by_date[$comment_date][] = $comment;
            } else {
                unset($comments[$index]);
            }
        }

        foreach ($comments_grouped_by_date as $date => $comments) {
            $ips_emails_data = self::getIPEmailsData($comments);

            $result = \Cleantalk\ApbctWP\API::methodSpamCheckCms(
                TT::toString($apbct->api_key),
                $ips_emails_data,
                $date
            );

            $error = !is_array($result)
                ? 'Unknown API error'
                : null;
            $error = !empty($result['error'])
                ? TT::getArrayValueAsString($result, 'error')
                : $error;

            if ( !is_null($error) ) {
                CommentsScanResponse::getInstance()->setError(1);
                CommentsScanResponse::getInstance()->setErrorMessage($error);
            } else {
                $onlySpammers = self::getSpammersFromResultAPI(TT::toArray($result));
                $marked_comment_ids = [];

                foreach ($comments as $comment) {
                    if (
                        ! in_array($comment->comment_ID, $marked_comment_ids, true) &&
                        (in_array($comment->comment_author_IP, $onlySpammers, true) ||
                         in_array($comment->comment_author_email, $onlySpammers, true))
                    ) {
                        $marked_comment_ids[] = $comment->comment_ID;
                        update_comment_meta($comment->comment_ID, 'ct_marked_as_spam', '1', true);
                    }
                }

                // Count spam
                CommentsScanResponse::getInstance()->updateSpam(count($marked_comment_ids));
            }

            // Count checked comments
            CommentsScanResponse::getInstance()->updateChecked(count($comments));
            // save count checked comments to State:data
            $apbct->data['count_checked_comments'] += count($comments);
            $apbct->saveData();
        }

        // Count bad comments
        CommentsScanResponse::getInstance()->setBad((int)self::getCountBadComments());

        die(CommentsScanResponse::getInstance()->toJson());
    }

    /**
     * @param array $comments
     *
     * @return array
     */
    private static function getIPEmailsData(array $comments)
    {
        $data = array();

        foreach ($comments as $comment) {
            if ($comment->comment_author_IP) {
                $data[] = $comment->comment_author_IP;
            }
            if ($comment->comment_author_email) {
                $data[] = $comment->comment_author_email;
            }
        }

        return $data;
    }

    /**
     * @param array $result
     *
     * @return array
     */
    private static function getSpammersFromResultAPI(array $result)
    {
        $spammers = array();

        foreach ($result as $param => $status) {
            if ((int) $status['appears'] === 1) {
                $spammers[] = $param;
            }
        }

        return $spammers;
    }
}
