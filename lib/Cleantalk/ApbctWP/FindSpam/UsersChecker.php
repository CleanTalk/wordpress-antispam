<?php

namespace Cleantalk\ApbctWP\FindSpam;

use Cleantalk\ApbctWP\AJAXService;
use Cleantalk\ApbctWP\ApbctEnqueue;
use Cleantalk\ApbctWP\FindSpam\ListTable\BadUsers;
use Cleantalk\ApbctWP\FindSpam\ListTable\UsersLogs;
use Cleantalk\ApbctWP\FindSpam\ListTable\UsersScan;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\TT;

class UsersChecker extends Checker
{
    public function __construct()
    {
        global $apbct;
        parent::__construct();

        $this->page_title       = esc_html__('Check users for spam', 'cleantalk-spam-protect');
        $this->page_script_name = 'users.php';
        $this->page_slug        = 'users';

        // Preparing data
        $current_user = wp_get_current_user();
        $is_paused = TT::toString(Cookie::get('ct_paused_users_check'));
        if ( ! empty($is_paused) ) {
            $prev_check = json_decode(stripslashes($is_paused), true);
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

        ApbctEnqueue::getInstance()->js('cleantalk-users-checkspam.js', array('jquery', 'jquery-ui-core'));
        wp_localize_script('cleantalk-users-checkspam-js', 'ctUsersCheck', array(
            'ct_ajax_nonce'            => $apbct->ajax_service->getAdminNonce(),
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
                "Checked %s users (excluding admins), found %s spam users and %s non-checkable users (no IP and email found).",
                'cleantalk-spam-protect'
            ),
            'ct_status_string_warning' => "<p>" . __(
                "Please do backup of WordPress database before delete any accounts!",
                'cleantalk-spam-protect'
            ) . "</p>",
            'ct_specify_date_range' => esc_html__('Please, specify a date range.', 'cleantalk-spam-protect'),
            'ct_select_date_range' => esc_html__('Please, select a date range.', 'cleantalk-spam-protect'),
        ));

        ApbctEnqueue::getInstance()->css('cleantalk-spam-check.css');
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

        $wc_orders = '';
        if ($userScanParameters->getAccurateCheck()) {
            $wc_orders = \Cleantalk\Antispam\IntegrationsByClass\Woocommerce::getCompletedOrders();
        }

        $query = "SELECT {$wpdb->users}.ID, {$wpdb->users}.user_email, {$wpdb->users}.user_registered
            FROM {$wpdb->users}
            {$between_dates_sql}
            {$wc_orders}
            ORDER BY {$wpdb->users}.ID ASC
            LIMIT %d OFFSET %d";

        $users = $wpdb->get_results($wpdb->prepare($query, $amount, $offset));

        if (!$users) {
            $users = array();
        }

        // removed skip_roles and return $users
        $users =  self::removeSkipRoles($users, $skip_roles);
        if (false === $users) {
            $users = array();
        }
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
            if (empty($user->user_id) || !property_exists($user, 'user_id') === false) {
                continue;
            }

            $user_meta  = get_userdata($user->user_id);
            if ( !($user_meta instanceof \WP_User) ) {
                continue;
            }

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
     * @return array
     */
    private static function removeUsersWithoutIPEmail(array $users)
    {
        global $apbct;
        foreach ($users as $index => $user) {
            if ( (bool)get_user_meta($user->ID, 'ct_bad') === true ) {
                delete_user_meta($user->ID, 'ct_marked_as_spam');
                unset($users[$index]);
                continue;
            }

            $ip_from_keeper = $apbct->login_ip_keeper->getIP($user->ID);
            $ip_from_keeper = null !== $ip_from_keeper
                ? $ip_from_keeper
                : false;
            $user_ip    = $ip_from_keeper;
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

    /**
     * @param $result
     * @param array $users
     *
     * @return array
     */
    public static function getUserIDsMarked($result, array $users)
    {
        $onlySpammers    = self::getSpammersFromResultAPI(TT::toArray($result));
        $marked_user_ids = [];

        foreach ( $users as $user ) {
            if (
                ! in_array($user->ID, $marked_user_ids, true) &&
                (in_array($user->user_ip, $onlySpammers, true) ||
                 in_array($user->user_email, $onlySpammers, true))
            ) {
                $marked_user_ids[] = $user->ID;
                update_user_meta($user->ID, 'ct_marked_as_spam', '1', true);
            }
        }

        return $marked_user_ids;
    }

    public function getCurrentScanPage()
    {
        $this->list_table = new UsersScan();

        $this->getCurrentScanPanel($this);
        echo UsersScan::getExtraTableNavInsertDeleteUsers();
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
            "These users can't be checked because they haven't IP and e-mail",
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

        if (is_multisite()) {
            $res = count(get_users());
        }

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
        AJAXService::checkNonceRestrictingNonAdmins('security');

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
        AJAXService::checkNonceRestrictingNonAdmins('security');

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
            AJAXService::checkNonceRestrictingNonAdmins('security');
        }

        global $wpdb, $apbct;

        // Checked users
        $cnt_checked = $apbct->data['count_checked_users'];
        if (is_multisite()) {
            $cnt_checked = count(get_users());
        }

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
                    'Checked %s users (excluding admins), found %s spam users and %s non-checkable users (no IP and email found).',
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
                        "Last check %s: checked %s users (excluding admins), found %s spam users and %s non-checkable users (no IP and email found).",
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
        global $apbct;
        AJAXService::checkNonceRestrictingNonAdmins('security');

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
            // gain IP from keeper
            $ip_from_keeper = $apbct->login_ip_keeper->getIP($iValue->ID);
            $ip_from_keeper = null !== $ip_from_keeper
                ? $ip_from_keeper
                : 'N/A';

            $text .= $iValue->user_login . ',';
            $text .= $iValue->data->user_email . ',';
            $text .= $ip_from_keeper;
            $text .= PHP_EOL;
        }

        $filename = ! empty(Post::get('filename')) ? Post::get('filename') : false;

        if ( $filename !== false ) {
            header('Content-Type: text/csv');
            echo esc_html($text);
        } else {
            echo 'Export error.'; // file not exists or empty $_POST['filename']
        }
        die();
    }

    public static function ctAjaxInsertUsers()
    {
        AJAXService::checkNonceRestrictingNonAdmins('security');

        global $wpdb, $apbct;

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
        $to_insert = 50;
        $query = 'SELECT network FROM `' . APBCT_TBL_FIREWALL_DATA . '` LIMIT ' . $to_insert . ';';

        $result    = $wpdb->get_results(
            $query,
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
                $email     = TT::toString($rnd - mt_rand(1, 10000)) . "_stop_email_$rnd@example.com";

                $user_id = wp_create_user(
                    $user_name,
                    (string)rand(),
                    $email
                );

                $curr_user = get_user_by('email', $email);

                if (false === $curr_user) {
                    continue;
                }

                update_user_meta($curr_user->ID, 'session_tokens', array($rnd => array('ip' => $ips[$i])));
                $apbct->login_ip_keeper->addUserIP($curr_user);
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
        AJAXService::checkNonceRestrictingNonAdmins('security');

        global $wpdb, $apbct;

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

        if (is_multisite()) {
            $params = array(
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'ct_marked_as_spam',
                        'compare' => '1'
                    ),
                    array(
                        'key'     => $wpdb->get_blog_prefix() . 'user_level',
                        'compare' => '1'
                    ),
                ),
            );

            $count_spammers = count(get_users($params));
        } else {
            $sql = "SELECT
                    COUNT(`user_id`)
                    FROM $wpdb->usermeta
                    where `meta_key`='ct_marked_as_spam'";

            $count_spammers = $wpdb->get_var($sql);
        }

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

        if (is_multisite()) {
            $params = array(
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'ct_bad',
                        'compare' => '1'
                    ),
                    array(
                        'key'     => $wpdb->get_blog_prefix() . 'user_level',
                        'compare' => '1'
                    ),
                ),
            );

            $count_bad = count(get_users($params));
        } else {
            $sql = "SELECT
                    COUNT(`user_id`)
                    FROM $wpdb->usermeta
                    where `meta_key`='ct_bad'";

            $count_bad = $wpdb->get_var($sql);
        }

        if (is_null($count_bad)) {
            return 0;
        }

        return (int) $count_bad;
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
                current_datetime()->format("Y-m-d H:i:s"),
                $log_data['checked'],
                $log_data['spam'],
                $log_data['bad']
            );

            die(UsersScanResponse::getInstance()->toJson());
        }

        $ips_emails_data = self::getIPEmailsData($users);

        $result = \Cleantalk\ApbctWP\API::methodSpamCheckCms(
            $apbct->api_key,
            $ips_emails_data,
            null
        );

        $error = !is_array($result)
            ? 'Unknown API error'
            : null;
        $error = !empty($result['error'])
            ? TT::getArrayValueAsString($result, 'error')
            : $error;

        if ( !is_null($error) ) {
            UsersScanResponse::getInstance()->setError(1);
            UsersScanResponse::getInstance()->setErrorMessage($error);
        } else {
            $marked_user_ids = self::getUserIDsMarked($result, $users);

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

        die(UsersScanResponse::getInstance()->toJson());
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
                current_datetime()->format("Y-m-d H:i:s"),
                $log_data['checked'],
                $log_data['spam'],
                $log_data['bad']
            );

            die(UsersScanResponse::getInstance()->toJson());
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

            $error = !is_array($result)
                ? 'Unknown API error'
                : null;
            $error = !empty($result['error'])
                ? TT::getArrayValueAsString($result, 'error')
                : $error;

            if ( !is_null($error) ) {
                UsersScanResponse::getInstance()->setError(1);
                UsersScanResponse::getInstance()->setErrorMessage($error);
            } else {
                $marked_user_ids = self::getUserIDsMarked($result, $users);

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

        die(UsersScanResponse::getInstance()->toJson());
    }
}
