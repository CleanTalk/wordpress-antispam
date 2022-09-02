<?php

namespace Cleantalk\ApbctWP\FindSpam;

use Cleantalk\ApbctWP\FindSpam\ListTable\BadUsers;
use Cleantalk\ApbctWP\FindSpam\ListTable\UsersLogs;
use Cleantalk\ApbctWP\FindSpam\ListTable\UsersScan;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;

class UsersChecker extends Checker
{
    public function __construct()
    {
        parent::__construct();

        $this->page_title       = esc_html__('Check users for spam', 'cleantalk-spam-protect');
        $this->page_script_name = 'users.php';
        $this->page_slug        = 'users';

        // Preparing data
        $current_user = wp_get_current_user();
        if ( ! empty(Cookie::get('ct_paused_users_check')) ) {
            $prev_check = json_decode(stripslashes(Cookie::get('ct_paused_users_check')), true);
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
            'ct_users_checkspam',
            APBCT_JS_ASSETS_PATH . '/cleantalk-users-checkspam.min.js',
            array('jquery', 'jquery-ui-core'),
            APBCT_VERSION
        );
        wp_localize_script('ct_users_checkspam', 'ctUsersCheck', array(
            'ct_ajax_nonce'            => wp_create_nonce('ct_secret_nonce'),
            'ct_prev_accurate'         => ! empty($prev_check['accurate']) ? true : false,
            'ct_prev_from'             => ! empty($prev_check_from) ? $prev_check_from : false,
            'ct_prev_till'             => ! empty($prev_check_till) ? $prev_check_till : false,
            'ct_timeout'               => __(
                'Failed from timeout. Going to check users again.',
                'cleantalk-spam-protect'
            ),
            'ct_timeout_delete'        => __(
                'Failed from timeout. Going to run a new attempt to delete spam users.',
                'cleantalk-spam-protect'
            ),
            'ct_confirm_deletion_all'  => __(
                'Do you confirm deletion selected accounts and all content owned by the accounts? Please do backup of the site before deletion!',
                'cleantalk-spam-protect'
            ),
            'ct_iusers'                => __('users.', 'cleantalk-spam-protect'),
            'ct_csv_filename'          => "user_check_by_" . $current_user->user_login,
            'ct_status_string'         => __(
                "Checked %s, found %s spam users and %s non-checkable users (without IP and email)",
                'cleantalk-spam-protect'
            ),
            'ct_status_string_warning' => "<p>" . __(
                "Please do backup of WordPress database before delete any accounts!",
                'cleantalk-spam-protect'
            ) . "</p>",
            'ct_specify_date_range' => esc_html__('Please, specify a date range.', 'cleantalk-spam-protect'),
            'ct_select_date_range' => esc_html__('Please, select a date range.', 'cleantalk-spam-protect'),
        ));

        wp_enqueue_style(
            'cleantalk_admin_css_settings_page',
            APBCT_JS_ASSETS_PATH . '/cleantalk-spam-check.min.css',
            array(),
            APBCT_VERSION,
            'all'
        );
    }

    /**
     * Get all users from DB
     *
     * @return array|false
     */
    private static function getAllUsers(UsersScanParameters $userScanParameters)
    {
        global $wpdb;

        $amount = $userScanParameters->getAmount();
        $skip_roles = $userScanParameters->getSkipRoles();
        $offset = $userScanParameters->getOffset();
        $between_dates_sql = '';
        $date_from = $userScanParameters->getFrom();
        $date_till = $userScanParameters->getTill();
        if ($date_from && $date_till) {
            $date_from = date('Y-m-d', (int) strtotime($date_from)) . ' 00:00:00';
            $date_till = date('Y-m-d', (int) strtotime($date_till)) . ' 23:59:59';

            $between_dates_sql = "WHERE $wpdb->users.user_registered >= '$date_from' AND $wpdb->users.user_registered <= '$date_till'";
        }

        // Woocommerce
        $wc_active = false;
        $wc_orders = '';
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true)) {
            $wc_active = true;
        }
        if ($wc_active && $userScanParameters->getAccurateCheck()) {
            $wc_orders = " AND NOT EXISTS (SELECT posts.* FROM {$wpdb->posts} AS posts"
                . " INNER JOIN {$wpdb->postmeta} AS postmeta"
                . " WHERE posts.post_type = 'shop_order'"
                . " AND posts.post_status = 'wc-completed'"
                . " AND posts.ID = postmeta.post_id"
                . " AND postmeta.meta_key = '_customer_user'"
                . " AND postmeta.meta_value = {$wpdb->users}.ID)";
        }

        $users = $wpdb->get_results(
            "
			SELECT {$wpdb->users}.ID, {$wpdb->users}.user_email, {$wpdb->users}.user_registered
			FROM {$wpdb->users}
			{$between_dates_sql}
			{$wc_orders}
			ORDER BY {$wpdb->users}.ID ASC
			LIMIT $amount OFFSET $offset;"
        );

        if (!$users) {
            $users = array();
        }

        // removed skip_roles and return $users
        $users =  self::removeSkipRoles($users, $skip_roles);

        // removed users without IP and Email
        $users =  self::removeUsersWithoutIPEmail($users);

        return $users;
    }

    /**
     * @param array $users
     * @param array $skip_roles
     *
     * @return array|false
     */
    private static function removeSkipRoles(array $users, array $skip_roles)
    {
        foreach ($users as $index => $user) {
            $user_meta  = get_userdata($user->ID);
            $user_roles = $user_meta->roles;
            foreach ($user_roles as $user_role) {
                if (in_array($user_role, $skip_roles, true)) {
                    delete_user_meta($user->ID, 'ct_marked_as_spam');
                    unset($users[$index]);
                    break;
                }
            }
        }

        return $users;
    }

    /**
     * @param array $users
     *
     * @return array|false
     */
    private static function removeUsersWithoutIPEmail(array $users)
    {
        foreach ($users as $index => $user) {
            $user_meta = self::getUserMeta($user->ID);

            $user_ip    = ! empty($user_meta[0]['ip']) ? trim($user_meta[0]['ip']) : false;
            $user_email = ! empty($user->user_email) ? trim($user->user_email) : false;

            // Validate IP and Email
            $user_ip    = filter_var($user_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
            $user_email = filter_var($user_email, FILTER_VALIDATE_EMAIL);

            if (!$user_ip && !$user_email) {
                update_user_meta($user->ID, 'ct_bad', '1', true);
                unset($users[$index]);
                continue;
            }

            // Add user ip to $user
            $user->user_ip = $user_ip;
        }

        return $users;
    }

    /**
     * @param array $users
     *
     * @return array
     */
    private static function getIPEmailsData(array $users)
    {
        $data = array();

        foreach ($users as $user) {
            if ($user->user_ip) {
                $data[] = $user->user_ip;
            }
            if ($user->user_email) {
                $data[] = $user->user_email;
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

    public function getCurrentScanPage()
    {
        $this->list_table = new UsersScan();

        $this->getCurrentScanPanel($this);
        echo '<form action="" method="POST">';
        $this->list_table->display();
        echo '</form>';
        $this->getFooter();
    }

    public function getSpamLogsPage()
    {
        $this->list_table = new UsersLogs();

        echo '<form action="" method="POST">';
        $this->list_table->display();
        echo '</form>';
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getBadUsersPage()
    {
        $this->list_table = new BadUsers();

        echo '<h3>' . esc_html__(
            "These users can't be checked because they haven't IP or e-mail",
            'cleantalk-spam-protect'
        ) . '</h3>';
        echo '<form action="" method="POST">';
        $this->list_table->display();
        echo '</form>';
    }

    /**
     * Getting a count of total users of the website and return formatted string about this.
     *
     * @return string
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function getCountText()
    {
        global $wpdb;

        $res = $wpdb->get_var(
            "
			SELECT COUNT(*)
			FROM {$wpdb->users}"
        );

        if ( $res ) {
            $text = sprintf(esc_html__('Total count of users: %s.', 'cleantalk-spam-protect'), $res);
        } else {
            $text = esc_html__('No users found.', 'cleantalk-spam-protect');
        }

        return $text;
    }

    /**
     * Get date last checked user or date first registered user
     *
     * @return string   date "M j Y"
     */
    public static function lastCheckDate()
    {
        global $wpdb;

        /**
         * We are trying to get the date of the last scan by actually
         * requesting the start date. But unfortunately, the start date
         * stores the end date. At least for the user scanner.
         */
        $sql = "SELECT `start_time`
				FROM " . APBCT_SPAMSCAN_LOGS . "
				WHERE `scan_type` = 'users'
				ORDER BY start_time DESC LIMIT 1";

        $lastCheckDate = $wpdb->get_col($sql);

        if ($lastCheckDate) {
            return date('M j Y', strtotime($lastCheckDate[0]));
        }

        return date('M j Y');
    }

    public static function ctAjaxCheckUsers()
    {
        check_ajax_referer('ct_secret_nonce', 'security');

        $userScanParameters = new UsersScanParameters($_POST);

        // Set type checking
        if ($userScanParameters->getAccurateCheck() && ($userScanParameters->getFrom() && $userScanParameters->getTill())) {
            self::startAccurateChecking($userScanParameters);
        } elseif (!$userScanParameters->getAccurateCheck() && ($userScanParameters->getFrom() && $userScanParameters->getTill())) {
            self::startCommonChecking($userScanParameters);
        } else {
            self::startCommonChecking($userScanParameters);
        }
    }

    /**
     * Run query for deleting 'ct_checked_now' meta. Need for the new scan.
     *
     * @return void
     */
    public static function ctAjaxClearUsers()
    {
        check_ajax_referer('ct_secret_nonce', 'security');

        global $wpdb, $apbct;

        $apbct->data['count_checked_users'] = 0;
        $apbct->data['count_bad_users'] = 0;
        $apbct->saveData();

        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('ct_marked_as_spam')");

        die;
    }

    public static function ctAjaxInfo($direct_call = false)
    {
        if ( ! $direct_call ) {
            check_ajax_referer('ct_secret_nonce', 'security');
        }

        global $wpdb, $apbct;

        // Checked users
        $cnt_checked = $apbct->data['count_checked_users'];

        $cnt_spam = self::getCountSpammers();

        // Bad users (without IP and Email)
        $cnt_bad      = self::getCountBadUsers();

        $return = array(
            'message' => '',
            'spam'    => $cnt_spam,
            'checked' => $cnt_checked,
            'bad'     => $cnt_bad,
        );

        if ( ! $direct_call ) {
            $return['message'] .= sprintf(
                esc_html__(
                    'Checked %s, found %s spam users and %s non-checkable users (without IP and email)',
                    'cleantalk-spam-protect'
                ),
                $cnt_checked,
                $cnt_spam,
                $cnt_bad
            );
        } else {
            $query = "SELECT * FROM " . APBCT_SPAMSCAN_LOGS . " WHERE scan_type = 'users' ORDER BY start_time DESC";
            $res   = $wpdb->get_row($query, ARRAY_A);

            if ( $res ) {
                $return['message'] .= sprintf(
                    __(
                        "Last check %s: checked %s users, found %s spam users and %s non-checkable users (without IP and email).",
                        'cleantalk-spam-protect'
                    ),
                    self::lastCheckDate(),
                    $cnt_checked,
                    $cnt_spam,
                    $cnt_bad
                );
            } else {
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
                "Results are based on the decision of our spam checking system and do not give a complete guarantee that these users are spammers.",
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

    private static function getLogData()
    {
        // Checked users
        global $apbct;

        $cnt_checked = $apbct->data['count_checked_users'];

        $cnt_spam = self::getCountSpammers();

        // Bad users (without IP and Email)
        $cnt_bad = self::getCountBadUsers();

        return array(
            'spam'    => $cnt_spam,
            'checked' => $cnt_checked,
            'bad'     => $cnt_bad,
        );
    }

    /**
     * Admin action 'wp_ajax_ajax_ct_get_csv_file' - prints CSV file to AJAX
     */
    public static function ctGetCsvFile()
    {
        check_ajax_referer('ct_secret_nonce', 'security');

        $text = 'login,email,ip' . PHP_EOL;

        $params = array(
            'meta_query' => array(
                array(
                    'key'     => 'ct_marked_as_spam',
                    'compare' => '1'
                ),
            ),
            'orderby'    => 'registered',
            'order'      => 'ASC',
        );

        $u = get_users($params);

        foreach ( $u as $iValue ) {
            $user_meta = get_user_meta($iValue->ID, 'session_tokens', true);
            if ( is_array($user_meta) ) {
                $user_meta = array_values($user_meta);
            }
            $text .= $iValue->user_login . ',';
            $text .= $iValue->data->user_email . ',';
            $text .= ! empty($user_meta[0]['ip']) ? trim($user_meta[0]['ip']) : '';
            $text .= PHP_EOL;
        }

        $filename = ! empty(Post::get('filename')) ? Post::get('filename') : false;

        if ( $filename !== false ) {
            header('Content-Type: text/csv');
            echo $text;
        } else {
            echo 'Export error.'; // file not exists or empty $_POST['filename']
        }
        die();
    }

    public static function ctAjaxInsertUsers()
    {
        check_ajax_referer('ct_secret_nonce', 'security');

        global $wpdb;

        //* TEST DELETION
        if ( ! empty(Post::get('delete')) ) {
            $users            = get_users(array('search' => 'user_*', 'search_columns' => array('login', 'nicename')));
            $deleted          = 0;
            $amount_to_delete = 1000;
            foreach ( $users as $user ) {
                if ( $deleted >= $amount_to_delete ) {
                    break;
                }
                if ( wp_delete_user($user->ID) ) {
                    $deleted++;
                }
            }
            print "$deleted";
            die();
        }

        // TEST INSERTION
        $to_insert = 500;
        $result    = $wpdb->get_results(
            'SELECT network FROM `' . APBCT_TBL_FIREWALL_DATA . '` LIMIT ' . $to_insert . ';',
            ARRAY_A
        );

        if ( $result ) {
            $ips = array();
            foreach ( $result as $value ) {
                $ips[] = long2ip($value['network']);
            }

            $inserted = 0;
            for ( $i = 0; $i < $to_insert; $i++ ) {
                $rnd = mt_rand(1, 10000000);

                $user_name = "user_$rnd";
                $email     = "stop_email_$rnd@example.com";

                $user_id = wp_create_user(
                    $user_name,
                    (string)rand(),
                    $email
                );

                $curr_user = get_user_by('email', $email);

                update_user_meta($curr_user->ID, 'session_tokens', array($rnd => array('ip' => $ips[$i])));

                if ( is_int($user_id) ) {
                    $inserted++;
                }
            }
        } else {
            $inserted = '0';
        }

        print "$inserted";
        die();
    }

    public static function ctAjaxDeleteAllUsers($count_all = 0)
    {
        check_ajax_referer('ct_secret_nonce', 'security');

        global $wpdb;

        $r = self::getCountSpammers();

        if ( ! is_null($r) ) {
            $count_all = (int)$r;

            $args  = array(
                'meta_key'   => 'ct_marked_as_spam',
                'meta_value' => '1',
                'fields'     => array('ID')
            );
            $users = get_users($args);

            if ( $users ) {
                foreach ( $users as $user ) {
                    wp_delete_user($user->ID);
                    usleep(5000);
                }
            }
        }

        die($count_all);
    }

    /**
     * Add hidden column into the users table
     *
     * @param $columns
     *
     * @return mixed
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function ctManageUsersColumns($columns)
    {
        $columns['apbct_status hidden'] = '';

        return $columns;
    }

    /**
     * Getting count spammers
     *
     * @return int
     */
    public static function getCountSpammers()
    {
        global $wpdb;

        $sql = "SELECT
                COUNT(`user_id`)
                FROM $wpdb->usermeta
                where `meta_key`='ct_marked_as_spam'";

        $count_spammers = $wpdb->get_var($sql);

        if (is_null($count_spammers)) {
            return 0;
        }

        return (int) $count_spammers;
    }

    /**
     * Getting count bas users without IP and Email
     *
     * @return int
     */
    public static function getCountBadUsers()
    {
        global $wpdb;

        $sql = "SELECT
                COUNT(`user_id`)
                FROM $wpdb->usermeta
                where `meta_key`='ct_bad'";

        $count_bad = $wpdb->get_var($sql);

        if (is_null($count_bad)) {
            return 0;
        }

        return (int) $count_bad;
    }

    public static function getUserMeta($user_id)
    {
        $user_meta = get_user_meta($user_id, 'session_tokens', true);

        if ( is_array($user_meta) ) {
            return array_values($user_meta);
        }

        return false;
    }

    /**
     * All users checking
     *
     * @param UsersScanParameters $userScanParameters
     *
     * @return void
     */
    public static function startCommonChecking(UsersScanParameters $userScanParameters)
    {
        global $apbct;
        $users = self::getAllUsers($userScanParameters);

        if (!$users) {
            UsersScanResponse::getInstance()->setEnd(1);

            $log_data = static::getLogData();
            static::writeSpamLog(
                'users',
                date("Y-m-d H:i:s"),
                $log_data['checked'],
                $log_data['spam'],
                $log_data['bad']
            );

            echo UsersScanResponse::getInstance()->toJson();
            die;
        }

        $ips_emails_data = self::getIPEmailsData($users);

        $result = \Cleantalk\ApbctWP\API::methodSpamCheckCms(
            $apbct->api_key,
            $ips_emails_data,
            null
        );

        if (!empty($result['error'])) {
            UsersScanResponse::getInstance()->setError(1);
            UsersScanResponse::getInstance()->setErrorMessage($result['error']);
        } else {
            $onlySpammers = self::getSpammersFromResultAPI($result);
            $marked_user_ids = [];

            foreach ($users as $user) {
                if (
                    ! in_array($user->ID, $marked_user_ids, true) &&
                    (in_array($user->user_ip, $onlySpammers, true) ||
                    in_array($user->user_email, $onlySpammers, true))
                ) {
                    $marked_user_ids[] = $user->ID;
                    update_user_meta($user->ID, 'ct_marked_as_spam', '1', true);
                }
            }

            // Count spam
            UsersScanResponse::getInstance()->setSpam(count($marked_user_ids));
        }

        // Count bad users
        UsersScanResponse::getInstance()->setBad((int)self::getCountBadUsers());
        // Count checked users
        UsersScanResponse::getInstance()->setChecked(count($users));
        // save count checked users to State:data
        $apbct->data['count_checked_users'] += count($users);
        $apbct->saveData();

        echo UsersScanResponse::getInstance()->toJson();
        die;
    }

    /**
     * Accurate user checking
     *
     * @param UsersScanParameters $userScanParameters
     *
     * @return void
     */
    private static function startAccurateChecking(UsersScanParameters $userScanParameters)
    {
        global $apbct;
        $users = self::getAllUsers($userScanParameters);

        if (!$users) {
            UsersScanResponse::getInstance()->setEnd(1);

            $log_data = static::getLogData();
            static::writeSpamLog(
                'users',
                date("Y-m-d H:i:s"),
                $log_data['checked'],
                $log_data['spam'],
                $log_data['bad']
            );

            echo UsersScanResponse::getInstance()->toJson();
            die;
        }

        $users_grouped_by_date = array();

        foreach ($users as $index => $user) {
            if (!empty($user->user_registered)) {
                $registered_date = date('Y-m-d', strtotime($user->user_registered));
                $users_grouped_by_date[$registered_date][] = $user;
            } else {
                unset($users[$index]);
            }
        }

        foreach ($users_grouped_by_date as $date => $users) {
            $ips_emails_data = self::getIPEmailsData($users);

            $result = \Cleantalk\ApbctWP\API::methodSpamCheckCms(
                $apbct->api_key,
                $ips_emails_data,
                $date
            );

            if (!empty($result['error'])) {
                UsersScanResponse::getInstance()->setError(1);
                UsersScanResponse::getInstance()->setErrorMessage($result['error']);
            } else {
                $onlySpammers = self::getSpammersFromResultAPI($result);
                $marked_user_ids = [];

                foreach ($users as $user) {
                    if (
                        ! in_array($user->ID, $marked_user_ids, true) &&
                        (in_array($user->user_ip, $onlySpammers, true) ||
                         in_array($user->user_email, $onlySpammers, true))
                    ) {
                        $marked_user_ids[] = $user->ID;
                        update_user_meta($user->ID, 'ct_marked_as_spam', '1', true);
                    }
                }

                // Count spam
                UsersScanResponse::getInstance()->updateSpam(count($marked_user_ids));
            }

            // Count checked users
            UsersScanResponse::getInstance()->updateChecked(count($users));
            // save count checked users to State:data
            $apbct->data['count_checked_users'] += count($users);
            $apbct->saveData();
        }

        // Count bad users
        UsersScanResponse::getInstance()->setBad((int)self::getCountBadUsers());

        echo UsersScanResponse::getInstance()->toJson();
        die;
    }
}
