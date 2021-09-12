<?php

namespace Cleantalk\ApbctWP\FindSpam;

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
        if ( ! empty($_COOKIE['ct_paused_users_check']) ) {
            $prev_check = json_decode(stripslashes($_COOKIE['ct_paused_users_check']), true);
        }

        wp_enqueue_script(
            'ct_users_checkspam',
            plugins_url('/cleantalk-spam-protect/js/cleantalk-users-checkspam.min.js'),
            array('jquery', 'jqueryui'),
            APBCT_VERSION
        );
        wp_localize_script('ct_users_checkspam', 'ctUsersCheck', array(
            'ct_ajax_nonce'            => wp_create_nonce('ct_secret_nonce'),
            'ct_prev_accurate'         => ! empty($prev_check['accurate']) ? true : false,
            'ct_prev_from'             => ! empty($prev_check['from']) ? $prev_check['from'] : false,
            'ct_prev_till'             => ! empty($prev_check['till']) ? $prev_check['till'] : false,
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
            ) . "</p>"
        ));

        wp_enqueue_style(
            'cleantalk_admin_css_settings_page',
            plugins_url() . '/cleantalk-spam-protect/css/cleantalk-spam-check.min.css',
            array(),
            APBCT_VERSION,
            'all'
        );
    }

    public function getCurrentScanPage()
    {
        $this->list_table = new \Cleantalk\ApbctWP\FindSpam\ListTable\UsersScan();

        $this->getCurrentScanPanel($this);
        echo '<form action="" method="POST">';
        $this->list_table->display();
        echo '</form>';
        $this->getFooter();
    }

    public function getSpamLogsPage()
    {
        $this->list_table = new \Cleantalk\ApbctWP\FindSpam\ListTable\UsersLogs();

        echo '<form action="" method="POST">';
        $this->list_table->display();
        echo '</form>';
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getBadUsersPage()
    {
        $this->list_table = new \Cleantalk\ApbctWP\FindSpam\ListTable\BadUsers();

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
        // Checked users
        $params      = array(
            'fields'      => 'ID',
            'meta_key'    => 'ct_checked',
            'count_total' => true,
            'orderby'     => 'ct_checked'
        );
        $tmp         = new \WP_User_Query($params);
        $cnt_checked = $tmp->get_total();

        if ( $cnt_checked > 0 ) {
            // If we have checked users return last checking date
            $users = $tmp->get_results();

            return date("M j Y", strtotime(get_user_meta(end($users), 'ct_checked', true)));
        } else {
            // If we have not any checked users return first user registered date
            $params = array(
                'fields'  => 'ID',
                'number'  => 1,
                'orderby' => 'user_registered'
            );
            $tmp    = new \WP_User_Query($params);

            return self::getUserRegister(current($tmp->get_results()));
        }
    }

    /**
     * Get date user registered
     *
     * @param $user_id
     *
     * @return string Date format"M j Y"
     */
    private static function getUserRegister($user_id)
    {
        $user_data  = get_userdata($user_id);
        $registered = $user_data->user_registered;

        return date("M j Y", strtotime($registered));
    }

    public static function ctAjaxCheckUsers()
    {
        check_ajax_referer('ct_secret_nonce', 'security');

        global $apbct, $wpdb;

        $wc_active = false;
        if ( in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) ) {
            $wc_active = true;
        }

        $amount = ! empty($_POST['amount']) && intval($_POST['amount'])
            ? (int)$_POST['amount']
            : 100;

        $skip_roles = array(
            'administrator'
        );

        $from_till = '';

        if ( isset($_POST['from'], $_POST['till']) ) {
            $from_date = date('Y-m-d', intval(strtotime($_POST['from']))) . ' 00:00:00';
            $till_date = date('Y-m-d', intval(strtotime($_POST['till']))) . ' 23:59:59';

            $from_till = " AND $wpdb->users.user_registered >= '$from_date' AND $wpdb->users.user_registered <= '$till_date'";
        }

        $wc_orders = '';

        if ( $wc_active && ! empty($_POST['accurate_check']) ) {
            $wc_orders = " AND NOT EXISTS (SELECT posts.* FROM {$wpdb->posts} AS posts INNER JOIN {$wpdb->postmeta} AS postmeta WHERE posts.post_type = 'shop_order' AND posts.post_status = 'wc-completed' AND posts.ID = postmeta.post_id AND postmeta.meta_key = '_customer_user' AND postmeta.meta_value = {$wpdb->users}.ID)";
        }

        $u = $wpdb->get_results(
            "
			SELECT {$wpdb->users}.ID, {$wpdb->users}.user_email, {$wpdb->users}.user_registered
			FROM {$wpdb->users}
			WHERE
				NOT EXISTS(SELECT * FROM {$wpdb->usermeta} as meta WHERE {$wpdb->users}.ID = meta.user_id AND meta.meta_key = 'ct_bad') AND
		        NOT EXISTS(SELECT * FROM {$wpdb->usermeta} as meta WHERE {$wpdb->users}.ID = meta.user_id AND meta.meta_key = 'ct_checked') AND
		        NOT EXISTS(SELECT * FROM {$wpdb->usermeta} as meta WHERE {$wpdb->users}.ID = meta.user_id AND meta.meta_key = 'ct_checked_now')
		        $wc_orders
			    $from_till
			ORDER BY {$wpdb->users}.user_registered ASC
			LIMIT $amount;"
        );

        $check_result = array(
            'end'     => 0,
            'checked' => 0,
            'spam'    => 0,
            'bad'     => 0,
            'error'   => 0
        );

        if ( count($u) > 0 ) {
            if ( ! empty($_POST['accurate_check']) ) {
                // Leaving users only with first comment's date. Unsetting others.
                foreach ( $u as $user_index => $user ) {
                    if ( ! isset($curr_date) ) {
                        $curr_date = (substr($user->user_registered, 0, 10) ?: '');
                    }

                    if ( substr($user->user_registered, 0, 10) != $curr_date ) {
                        unset($u[$user_index]);
                    }
                }
            }

            // Checking comments IP/Email. Gathering $data for check.
            $data = array();

            foreach ( $u as $i => $iValue ) {
                $user_meta = get_user_meta($iValue->ID, 'session_tokens', true);
                if ( is_array($user_meta) ) {
                    $user_meta = array_values($user_meta);
                }

                $curr_ip    = ! empty($user_meta[0]['ip']) ? trim($user_meta[0]['ip']) : '';
                $curr_email = ! empty($iValue->user_email) ? trim($iValue->user_email) : '';

                // Check for identity
                $curr_ip    = preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $curr_ip) === 1 ? $curr_ip : null;
                $curr_email = preg_match('/^\S+@\S+\.\S+$/', $curr_email) === 1 ? $curr_email : null;

                if ( empty($curr_ip) && empty($curr_email) ) {
                    $check_result['bad']++;
                    update_user_meta($iValue->ID, 'ct_bad', '1', true);
                    update_user_meta($iValue->ID, 'ct_checked', date("Y-m-d H:m:s"), true);
                    update_user_meta($iValue->ID, 'ct_checked_now', '1', true);
                    unset($u[$i]);
                } else {
                    if ( ! empty($curr_ip) ) {
                        $data[] = $curr_ip;
                    }
                    if ( ! empty($curr_email) ) {
                        $data[] = $curr_email;
                    }
                    // Patch for empty IP/Email
                    $iValue->data       = new \stdClass();
                    $iValue->user_ip    = empty($curr_ip) ? 'none' : $curr_ip;
                    $iValue->user_email = empty($curr_email) ? 'none' : $curr_email;
                }
            }

            // Recombining after checking and unsetting
            $u = array_values($u);

            // Drop if data empty and there's no users to check
            if ( count($data) === 0 ) {
                if ( $_POST['unchecked'] === 0 ) {
                    $check_result['end'] = 1;
                }
                print json_encode($check_result);
                die();
            }

            $result = \Cleantalk\ApbctWP\API::methodSpamCheckCms(
                $apbct->api_key,
                $data,
                ! empty($_POST['accurate_check']) ? $curr_date : null
            );

            if ( empty($result['error']) ) {
                foreach ( $u as $iValue ) {
                    $check_result['checked']++;
                    update_user_meta($iValue->ID, 'ct_checked', date("Y-m-d H:m:s"), true);
                    update_user_meta($iValue->ID, 'ct_checked_now', date("Y-m-d H:m:s"), true);

                    // Do not display forbidden roles.
                    foreach ( $skip_roles as $role ) {
                        $user_meta  = get_userdata($iValue->ID);
                        $user_roles = $user_meta->roles;
                        if ( in_array($role, $user_roles) ) {
                            delete_user_meta($iValue->ID, 'ct_marked_as_spam');
                            continue 2;
                        }
                    }

                    $mark_spam_ip    = false;
                    $mark_spam_email = false;

                    $uip = $iValue->user_ip;
                    $uim = $iValue->user_email;

                    if ( isset($result[$uip]) && $result[$uip]['appears'] == 1 ) {
                        $mark_spam_ip = true;
                    }

                    if ( isset($result[$uim]) && $result[$uim]['appears'] == 1 ) {
                        $mark_spam_email = true;
                    }

                    if ( $mark_spam_ip || $mark_spam_email ) {
                        $check_result['spam']++;
                        update_user_meta($iValue->ID, 'ct_marked_as_spam', '1', true);
                    }
                }
            } else {
                $check_result['error']         = 1;
                $check_result['error_message'] = $result['error'];
            }
        } else {
            $check_result['end'] = 1;

            $log_data = static::getLogData();
            static::writeSpamLog(
                'users',
                date("Y-m-d H:i:s"),
                $log_data['checked'],
                $log_data['spam'],
                $log_data['bad']
            );
        }
        echo json_encode($check_result);

        die;
    }

    /**
     * Run query for deleting 'ct_checked_now' meta. Need for the new scan.
     *
     * @return void
     */
    public static function ctAjaxClearUsers()
    {
        check_ajax_referer('ct_secret_nonce', 'security');

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('ct_checked_now')");

        if ( isset($_POST['from']) && isset($_POST['till']) ) {
            if (
                preg_match('/[a-zA-Z]{3}\s{1}\d{1,2}\s{1}\d{4}/', $_POST['from']) &&
                preg_match('/[a-zA-Z]{3}\s{1}\d{1,2}\s{1}\d{4}/', $_POST['till'])
            ) {
                $from = date('Y-m-d', intval(strtotime($_POST['from']))) . ' 00:00:00';
                $till = date('Y-m-d', intval(strtotime($_POST['till']))) . ' 23:59:59';

                $wpdb->query(
                    "DELETE FROM {$wpdb->usermeta} WHERE 
                meta_key IN ('ct_checked','ct_marked_as_spam','ct_bad') 
                AND meta_value >= '{$from}' 
                AND meta_value <= '{$till}';"
                );
                die();
            } else {
                $wpdb->query(
                    "DELETE FROM {$wpdb->usermeta} WHERE 
                meta_key IN ('ct_checked','ct_marked_as_spam','ct_bad')"
                );
                die();
            }
        }

        die();
    }

    public static function ctAjaxInfo($direct_call = false)
    {
        if ( ! $direct_call ) {
            check_ajax_referer('ct_secret_nonce', 'security');
        }

        global $wpdb;

        // Checked users
        $cnt_checked = $wpdb->get_results(
            "
			SELECT COUNT(*) AS cnt
			FROM {$wpdb->usermeta}
			WHERE meta_key='ct_checked_now'"
        )[0]->cnt;

        // Spam users
        $cnt_spam = $wpdb->get_results(
            "
			SELECT COUNT({$wpdb->users}.ID) AS cnt
			FROM {$wpdb->users}
			INNER JOIN {$wpdb->usermeta} AS meta1 ON ( {$wpdb->users}.ID = meta1.user_id )
			INNER JOIN {$wpdb->usermeta} AS meta2 ON ( {$wpdb->users}.ID = meta2.user_id )
				WHERE
					meta1.meta_key = 'ct_marked_as_spam' AND
					meta2.meta_key = 'ct_checked_now';"
        )[0]->cnt;

        // Bad users (without IP and Email)
        $cnt_bad = $wpdb->get_results(
            "
			SELECT COUNT({$wpdb->users}.ID) AS cnt
			FROM {$wpdb->users}
			INNER JOIN {$wpdb->usermeta} AS meta1 ON ( {$wpdb->users}.ID = meta1.user_id )
			INNER JOIN {$wpdb->usermeta} AS meta2 ON ( {$wpdb->users}.ID = meta2.user_id )
				WHERE
					meta1.meta_key = 'ct_bad' AND
					meta2.meta_key = 'ct_checked_now';"
        )[0]->cnt;

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
        global $wpdb;

        // Checked users
        $cnt_checked = $wpdb->get_results(
            "
			SELECT COUNT(*) AS cnt
			FROM {$wpdb->usermeta}
			WHERE meta_key='ct_checked_now'"
        )[0]->cnt;

        // Spam users
        $cnt_spam = $wpdb->get_results(
            "
			SELECT COUNT({$wpdb->users}.ID) AS cnt
			FROM {$wpdb->users}
			INNER JOIN {$wpdb->usermeta} AS meta1 ON ( {$wpdb->users}.ID = meta1.user_id )
			INNER JOIN {$wpdb->usermeta} AS meta2 ON ( {$wpdb->users}.ID = meta2.user_id )
				WHERE
					meta1.meta_key = 'ct_marked_as_spam' AND
					meta2.meta_key = 'ct_checked_now';"
        )[0]->cnt;

        // Bad users (without IP and Email)
        $cnt_bad = $wpdb->get_results(
            "
			SELECT COUNT({$wpdb->users}.ID) AS cnt
			FROM {$wpdb->users}
			INNER JOIN {$wpdb->usermeta} AS meta1 ON ( {$wpdb->users}.ID = meta1.user_id )
			INNER JOIN {$wpdb->usermeta} AS meta2 ON ( {$wpdb->users}.ID = meta2.user_id )
				WHERE
					meta1.meta_key = 'ct_bad' AND
					meta2.meta_key = 'ct_checked_now';"
        )[0]->cnt;

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
                array(
                    'key' => 'ct_checked_now'
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

        $filename = ! empty($_POST['filename']) ? $_POST['filename'] : false;

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
        if ( ! empty($_POST['delete']) ) {
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

        $r = $wpdb->get_var("select count(*) as cnt from $wpdb->usermeta where meta_key='ct_marked_as_spam';");

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
}
