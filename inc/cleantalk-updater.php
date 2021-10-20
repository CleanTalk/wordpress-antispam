<?php

use Cleantalk\ApbctWP\Cron;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\Common\Schema;
use Cleantalk\Variables\Server;

/**
 * Main function to compare versions and run necessary update functions.
 *
 * @param string $current_version
 * @param string $new_version
 *
 * @return bool
 *
 * @psalm-suppress PossiblyUndefinedIntArrayOffset
 */
function apbct_run_update_actions($current_version, $new_version)
{
    $current_version_arr = apbct_version_standardization($current_version);
    $new_version_arr     = apbct_version_standardization($new_version);

    $current_version_str = implode('.', $current_version_arr);
    $new_version_str     = implode('.', $new_version_arr);

    for ( $ver_major = $current_version_arr[0]; $ver_major <= $current_version_arr[0]; $ver_major++ ) {
        for ( $ver_minor = 0; $ver_minor <= 300; $ver_minor++ ) {
            for ( $ver_fix = 0; $ver_fix <= 10; $ver_fix++ ) {
                if ( version_compare("{$ver_major}.{$ver_minor}.{$ver_fix}", $current_version_str, '<=') ) {
                    continue;
                }

                if ( function_exists("apbct_update_to_{$ver_major}_{$ver_minor}_{$ver_fix}") ) {
                    $result = call_user_func("apbct_update_to_{$ver_major}_{$ver_minor}_{$ver_fix}");
                    if ( ! empty($result['error']) ) {
                        break;
                    }
                }

                if ( $ver_fix == 0 && function_exists("apbct_update_to_{$ver_major}_{$ver_minor}") ) {
                    $result = call_user_func("apbct_update_to_{$ver_major}_{$ver_minor}");
                    if ( ! empty($result['error']) ) {
                        break;
                    }
                }

                if ( version_compare("{$ver_major}.{$ver_minor}.{$ver_fix}", $new_version_str, '>=') ) {
                    break(2);
                }
            }
        }
    }

    return true;
}

/**
 * Convert string version to an array
 *
 * @param string $version
 *
 * @return array
 */
function apbct_version_standardization($version)
{
    $parsed_version = explode('.', $version);

    $parsed_version[0] = ! empty($parsed_version[0]) ? (int)$parsed_version[0] : 0;
    $parsed_version[1] = ! empty($parsed_version[1]) ? (int)$parsed_version[1] : 0;
    $parsed_version[2] = ! empty($parsed_version[2]) ? (int)$parsed_version[2] : 0;

    return $parsed_version;
}

/**
 * Get columns from a selected DB table
 *
 * @param string $table_name
 *
 * @return array
 */
function apbct_get_table_columns($table_name)
{
    global $wpdb;
    $query         = 'SHOW COLUMNS FROM ' . $table_name;
    $res           = $wpdb->get_results($query, ARRAY_A);
    $columns_names = array();
    foreach ( $res as $column ) {
        $columns_names[] = $column['Field'];
    }

    return $columns_names;
}

/**
 * @return void
 */
function apbct_update_to_5_50_0()
{
    global $wpdb;
    $wpdb->query(
        'CREATE TABLE IF NOT EXISTS `' . APBCT_TBL_FIREWALL_DATA . '` (
		`network` int(11) unsigned NOT NULL,
		`mask` int(11) unsigned NOT NULL,
		INDEX (  `network` ,  `mask` )
		);'
    );

    $wpdb->query(
        'CREATE TABLE IF NOT EXISTS `' . APBCT_TBL_FIREWALL_LOG . '` (
		`ip` VARCHAR(15) NOT NULL , 
		`all` INT NOT NULL , 
		`blocked` INT NOT NULL , 
		`timestamp` INT NOT NULL , 
		PRIMARY KEY (`ip`));'
    );
}

/**
 * @return void
 */
function apbct_update_to_5_56_0()
{
    if ( ! wp_next_scheduled('cleantalk_update_sfw_hook') ) {
        wp_schedule_event(time() + 1800, 'daily', 'cleantalk_update_sfw_hook');
    }
}

/**
 * @return void
 */
function apbct_update_to_5_70_0()
{
    global $wpdb;

    if ( ! in_array('all_entries', $wpdb->get_col('DESC ' . APBCT_TBL_FIREWALL_LOG, 0)) ) {
        $wpdb->query(
            'ALTER TABLE `' . APBCT_TBL_FIREWALL_LOG . '`
			CHANGE `all` `all_entries` INT(11) NOT NULL,
			CHANGE `blocked` `blocked_entries` INT(11) NOT NULL,
			CHANGE `timestamp` `entries_timestamp` INT(11) NOT NULL;'
        );
    }

    // Deleting usless data
    delete_option('cleantalk_sends_reports_till');
    delete_option('cleantalk_activation_timestamp');

    // Disabling WP_Cron tasks
    wp_clear_scheduled_hook('cleantalk_send_daily_report_hook');
    wp_clear_scheduled_hook('ct_hourly_event_hook');
    wp_clear_scheduled_hook('ct_send_sfw_log');
    wp_clear_scheduled_hook('cleantalk_update_sfw_hook');
    wp_clear_scheduled_hook('cleantalk_get_brief_data_hook');

    // Adding Self cron system tasks
    $cron = new Cron();
    $cron->addTask('check_account_status', 'ct_account_status_check', 3600, time() + 1800); // New
    $cron->addTask('delete_spam_comments', 'ct_delete_spam_comments', 3600, time() + 3500);
    $cron->addTask('send_feedback', 'ct_send_feedback', 3600, time() + 3500);
    $cron->addTask('sfw_update', 'apbct_sfw_update__init', 86400, time() + 43200);
    $cron->addTask('send_sfw_logs', 'ct_sfw_send_logs', 3600, time() + 1800); // New
    $cron->addTask('get_brief_data', 'cleantalk_get_brief_data', 86400, time() + 3500);
}

/**
 * @return void
 */
function apbct_update_to_5_74_0()
{
    $cron = new Cron();
    $cron->removeTask('send_daily_request');
}

/**
 * @return void
 */
function apbct_update_to_5_97_0()
{
    global $apbct;

    if ( count($apbct->data['connection_reports']['negative_report']) >= 20 ) {
        $apbct->data['connection_reports']['negative_report'] = array_slice(
            $apbct->data['connection_reports']['negative_report'],
            -20,
            20
        );
    }

    $apbct->saveData();
}

/**
 * @return void
 */
function apbct_update_to_5_109_0()
{
    global $apbct, $wpdb;

    if ( apbct_is_plugin_active_for_network($apbct->base_name) && ! defined('CLEANTALK_ACCESS_KEY') ) {
        $sfw_data_query = 'CREATE TABLE IF NOT EXISTS `%s` (
			`network` int(11) unsigned NOT NULL,
			`mask` int(11) unsigned NOT NULL,
			INDEX (  `network` ,  `mask` )
			);';

        $sfw_log_query = 'CREATE TABLE IF NOT EXISTS `%s` (
			`ip` VARCHAR(15) NOT NULL,
			`all_entries` INT NOT NULL,
			`blocked_entries` INT NOT NULL,
			`entries_timestamp` INT NOT NULL,
			PRIMARY KEY (`ip`));';

        $initial_blog = get_current_blog_id();
        $blogs        = array_keys($wpdb->get_results('SELECT blog_id FROM ' . $wpdb->blogs, OBJECT_K));
        foreach ( $blogs as $blog ) {
            switch_to_blog($blog);
            $wpdb->query(
                sprintf($sfw_data_query, $wpdb->prefix . 'cleantalk_sfw')
            );       // Table for SpamFireWall data
            $wpdb->query(sprintf($sfw_log_query, $wpdb->prefix . 'cleantalk_sfw_logs'));  // Table for SpamFireWall logs
            // Cron tasks
            $cron = new Cron();
            $cron->addTask(
                'check_account_status',
                'ct_account_status_check',
                3600,
                time() + 1800
            ); // Checks account status
            $cron->addTask(
                'delete_spam_comments',
                'ct_delete_spam_comments',
                3600,
                time() + 3500
            ); // Formerly ct_hourly_event_hook()
            $cron->addTask('send_feedback', 'ct_send_feedback', 3600, time() + 3500); // Formerly ct_hourly_event_hook()
            $cron->addTask('sfw_update', 'apbct_sfw_update__init', 86400, time() + 300);  // SFW update
            $cron->addTask('send_sfw_logs', 'ct_sfw_send_logs', 3600, time() + 1800); // SFW send logs
            $cron->addTask(
                'get_brief_data',
                'cleantalk_get_brief_data',
                86400,
                time() + 3500
            ); // Get data for dashboard widget
            $cron->addTask(
                'send_connection_report',
                'ct_mail_send_connection_report',
                86400,
                time() + 3500
            ); // Send connection report to welcome@cleantalk.org
        }
        switch_to_blog($initial_blog);
    }
}

/**
 * @return void
 */
function apbct_update_to_5_110_0()
{
    global $apbct;
    unset($apbct->data['last_remote_call']);
    $apbct->saveData;
    $apbct->save('remote_calls');
}

/**
 * @return void
 */
function apbct_update_to_5_116_0()
{
    global $apbct, $wpdb;

    $apbct->settings['store_urls']           = 0;
    $apbct->settings['store_urls__sessions'] = 0;
    $apbct->saveSettings();

    $wpdb->query(
        'CREATE TABLE IF NOT EXISTS `' . APBCT_TBL_SESSIONS . '` (
		`id` VARCHAR(64) NOT NULL,
		`name` TEXT NOT NULL,
		`value` TEXT NULL,
		`last_update` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`, `name`(10)));'
    );
}

/**
 * @return void
 */
function apbct_update_to_5_116_1()
{
    global $wpdb;

    $wpdb->query(
        'CREATE TABLE IF NOT EXISTS `' . APBCT_TBL_SESSIONS . '` (
		`id` VARCHAR(64) NOT NULL,
		`name` TEXT NOT NULL,
		`value` TEXT NULL,
		`last_update` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`, `name`(10)));'
    );
}

/**
 * @return void
 */
function apbct_update_to_5_116_2()
{
    global $wpdb;

    $wpdb->query(
        'CREATE TABLE IF NOT EXISTS `' . APBCT_TBL_SESSIONS . '` (
		`id` VARCHAR(64) NOT NULL,
		`name` TEXT NOT NULL,
		`value` TEXT NULL DEFAULT NULL,
		`last_update` DATETIME NULL DEFAULT NULL,
		PRIMARY KEY (`id`, `name`(10)));'
    );
}

/**
 * @return void
 */
function apbct_update_to_5_118_0()
{
    global $wpdb;
    $wpdb->query(
        'DELETE
			FROM `' . APBCT_TBL_SESSIONS . '`
			WHERE last_update < NOW() - INTERVAL ' . APBCT_SEESION__LIVE_TIME . ' SECOND;'
    );
    delete_option('cleantalk_server');
}

/**
 * @return void
 */
function apbct_update_to_5_118_2()
{
    global $apbct;
    $apbct->data['connection_reports']          = $apbct->def_data['connection_reports'];
    $apbct->data['connection_reports']['since'] = date('d M');
    $apbct->saveData();
}

/**
 * @return void
 */
function apbct_update_to_5_119_0()
{
    global $wpdb;

    $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . 'cleantalk_sessions`;');  //  Deleting session table

    $sqls = array();

    // SFW data
    $sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_sfw` (
		`network` int(11) unsigned NOT NULL,
		`mask` int(11) unsigned NOT NULL,
		INDEX (  `network` ,  `mask` )
		);';

    // SFW log
    $sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_sfw_logs` (
		`ip` VARCHAR(15) NOT NULL,
		`all_entries` INT NOT NULL,
		`blocked_entries` INT NOT NULL,
		`entries_timestamp` INT NOT NULL,
		PRIMARY KEY (`ip`));';

    // Sessions
    $sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_sessions` (
		`id` VARCHAR(64) NOT NULL,
		`name` VARCHAR(64) NOT NULL,
		`value` TEXT NULL DEFAULT NULL,
		`last_update` DATETIME NULL DEFAULT NULL,
		PRIMARY KEY (`id`(64), `name`(64)));';

    apbct_activation__create_tables($sqls);

    // WPMS
    if ( is_multisite() ) {
        $initial_blog = get_current_blog_id();
        $blogs        = array_keys($wpdb->get_results('SELECT blog_id FROM ' . $wpdb->blogs, OBJECT_K));
        foreach ( $blogs as $blog ) {
            switch_to_blog($blog);
            $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . 'cleantalk_sessions`;');  //  Deleting session table
            apbct_activation__create_tables($sqls);
        }
        switch_to_blog($initial_blog);
    }

    // Drop work url
    update_option(
        'cleantalk_server',
        array(
            'ct_work_url'       => null,
            'ct_server_ttl'     => 0,
            'ct_server_changed' => 0,
        )
    );
}

/**
 * @return void
 */
function apbct_update_to_5_124_0()
{
    global $apbct;
    // Deleting error in database because format were changed
    $apbct->errors = array();
    $apbct->saveErrors();
}

/**
 * @return void
 */
function apbct_update_to_5_126_0()
{
    global $apbct;
    // Enable storing URLs
    $apbct->settings['store_urls']           = 1;
    $apbct->settings['store_urls__sessions'] = 1;
    $apbct->saveSettings();
}

/**
 * @return void
 */
function apbct_update_to_5_127_0()
{
    global $apbct, $wpdb;

    // Move exclusions from variable to settins
    global $cleantalk_url_exclusions, $cleantalk_key_exclusions;
    // URLs
    if ( ! empty($cleantalk_url_exclusions) && is_array($cleantalk_url_exclusions) ) {
        $apbct->settings['exclusions__urls'] = implode(',', $cleantalk_url_exclusions);
        if ( APBCT_WPMS ) {
            $initial_blog = get_current_blog_id();
            switch_to_blog(1);
            $apbct->saveSettings();
            switch_to_blog($initial_blog);
        } else {
            $apbct->saveSettings();
        }
    }
    // Fields
    if ( ! empty($cleantalk_key_exclusions) && is_array($cleantalk_key_exclusions) ) {
        $apbct->settings['exclusions__fields'] = implode(',', $cleantalk_key_exclusions);
        if ( APBCT_WPMS ) {
            $initial_blog = get_current_blog_id();
            switch_to_blog(1);
            $apbct->saveSettings();
            switch_to_blog($initial_blog);
        } else {
            $apbct->saveSettings();
        }
    }

    // Deleting legacy
    if ( isset($apbct->data['testing_failed']) ) {
        unset($apbct->data['testing_failed']);
        $apbct->saveData();
    }

    if ( APBCT_WPMS ) {
        // Whitelabel
        // Reset "api_key_is_received" flag
        $initial_blog = get_current_blog_id();
        $blogs        = array_keys($wpdb->get_results('SELECT blog_id FROM ' . $wpdb->blogs, OBJECT_K));
        foreach ( $blogs as $blog ) {
            switch_to_blog($blog);

            $settings = get_option('cleantalk_settings');
            if ( isset($settings['data__use_static_js_key']) ) {
                $settings['data__use_static_js_key'] = $settings['data__use_static_js_key'] === 0
                    ? -1
                    : $settings['data__use_static_js_key'];
                update_option('cleantalk_settings', $settings);

                $data = get_option('cleantalk_data');
                if ( isset($data['white_label_data']['is_key_recieved']) ) {
                    unset($data['white_label_data']['is_key_recieved']);
                    update_option('cleantalk_data', $data);
                }
            }
            switch_to_blog($initial_blog);

            if ( defined('APBCT_WHITELABEL') ) {
                $apbct->network_settings = array(
                    'white_label'              => defined('APBCT_WHITELABEL') && APBCT_WHITELABEL == true ? 1 : 0,
                    'white_label__plugin_name' => defined('APBCT_WHITELABEL_NAME') ? APBCT_WHITELABEL_NAME : APBCT_NAME,
                );
            } elseif ( defined('CLEANTALK_ACCESS_KEY') ) {
                $apbct->network_settings = array(
                    'allow_custom_key' => 0,
                    'apikey'           => CLEANTALK_ACCESS_KEY,
                );
            }
            $apbct->saveNetworkSettings();
        }
    } else {
        // Switch data__use_static_js_key to Auto if it was disabled
        $apbct->settings['data__use_static_js_key'] = $apbct->settings['data__use_static_js_key'] === 0
            ? -1
            : $apbct->settings['data__use_static_js_key'];
        $apbct->saveSettings();
    }
}

/**
 * @return void
 */
function apbct_update_to_5_127_1()
{
    global $apbct;
    if ( APBCT_WPMS && is_main_site() ) {
        $network_settings = get_site_option('cleantalk_network_settings');
        if ( $network_settings !== false && empty($network_settings['allow_custom_key']) && empty($network_settings['white_label']) ) {
            $network_settings['allow_custom_key'] = 1;
            update_site_option('cleantalk_network_settings', $network_settings);
        }
        if ( $network_settings !== false && $network_settings['white_label'] == 1 && $apbct->data['moderate'] == 0 ) {
            ct_account_status_check(
                $network_settings['apikey'] ? $network_settings['apikey'] : $apbct->settings['apikey'],
                false
            );
        }
    } elseif ( is_main_site() ) {
        ct_account_status_check(
            $apbct->settings['apikey'],
            false
        );
    }
}

/**
 * @return void
 */
function apbct_update_to_5_128_0()
{
    global $apbct;
    $apbct->remote_calls = array();
    $apbct->save('remote_calls');
}

/**
 * @return void
 */
function apbct_update_to_5_133_0()
{
    $sqls = array();

    // Scan comment/user log
    $sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_spamscan_logs` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
        `scan_type` varchar(11) NOT NULL,
        `start_time` datetime NOT NULL,
        `finish_time` datetime NOT NULL,
        `count_to_scan` int(11) DEFAULT NULL,
        `found_spam` int(11) DEFAULT NULL,
        `found_bad` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`));';

    apbct_activation__create_tables($sqls);
}

/**
 * @return void
 *
 * @psalm-suppress PossiblyUndefinedStringArrayOffset
 */
function apbct_update_to_5_138_0()
{
    global $wpdb;
    // change name for prevent psalm false positive
    $_wpdb = $wpdb;

    $sqls = array();

    // SQL queries for each blog
    $sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_spamscan_logs` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
        `scan_type` varchar(11) NOT NULL,
        `start_time` datetime NOT NULL,
        `finish_time` datetime NOT NULL,
        `count_to_scan` int(11) DEFAULT NULL,
        `found_spam` int(11) DEFAULT NULL,
        `found_bad` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`));';
    $sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_sfw` (
		`network` int(11) unsigned NOT NULL,
		`mask` int(11) unsigned NOT NULL,
		INDEX (  `network` ,  `mask` )
		);';

    $table_sfw_columns = apbct_get_table_columns(APBCT_TBL_FIREWALL_DATA);
    if ( ! in_array('status', $table_sfw_columns) ) {
        $sqls[] = 'ALTER TABLE `%scleantalk_sfw` ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 0 AFTER mask;';
    }

    // Actions for WPMS
    if ( APBCT_WPMS ) {
        // Getting all blog ids
        $initial_blog = get_current_blog_id();
        $blogs        = $_wpdb->get_results('SELECT blog_id FROM ' . $_wpdb->blogs, OBJECT_K);
        $blogs_ids    = array_keys($blogs);

        // Getting main blog setting
        switch_to_blog(1);
        $main_blog_settings = get_option('cleantalk_settings');
        switch_to_blog($initial_blog);

        // Getting network settings
        $net_settings = get_site_option('cleantalk_network_settings');

        foreach ( $blogs_ids as $blog ) {
            // Update time limit to prevent exec time error
            set_time_limit(20);

            switch_to_blog($blog);

            // Update SQL structure
            apbct_activation__create_tables($sqls);

            // Getting key
            $settings = $net_settings['allow_custom_key']
                ? get_option('cleantalk_settings')
                : $main_blog_settings;

            // Update plugin status
            if ( ! empty($settings['apikey']) ) {
                $data = get_option('cleantalk_data', array());

                $result = \Cleantalk\ApbctWP\API::methodNoticePaidTill(
                    $settings['api_key'],
                    preg_replace('/http[s]?:\/\//', '', get_option('home'), 1),
                    ! is_main_site() && $net_settings['white_label'] ? 'anti-spam-hosting' : 'antispam'
                );

                if ( empty($result['error']) || ! empty($result['valid']) ) {
                    // Notices
                    $data['notice_show']        = isset($result['show_notice']) ? (int)$result['show_notice'] : 0;
                    $data['notice_renew']       = isset($result['renew']) ? (int)$result['renew'] : 0;
                    $data['notice_trial']       = isset($result['trial']) ? (int)$result['trial'] : 0;
                    $data['notice_review']      = isset($result['show_review']) ? (int)$result['show_review'] : 0;
                    $data['notice_auto_update'] = isset($result['show_auto_update_notice']) ? (int)$result['show_auto_update_notice'] : 0;

                    // Other
                    $data['service_id']      = isset($result['service_id']) ? (int)$result['service_id'] : 0;
                    $data['valid']           = isset($result['valid']) ? (int)$result['valid'] : 0;
                    $data['moderate']        = isset($result['moderate']) ? (int)$result['moderate'] : 0;
                    $data['ip_license']      = isset($result['ip_license']) ? (int)$result['ip_license'] : 0;
                    $data['moderate_ip']     = isset($result['moderate_ip'], $result['ip_license']) ? (int)$result['moderate_ip'] : 0;
                    $data['spam_count']      = isset($result['spam_count']) ? (int)$result['spam_count'] : 0;
                    $data['auto_update']     = isset($result['auto_update_app']) ? (int)$result['auto_update_app'] : 0;
                    $data['user_token']      = isset($result['user_token']) ? (string)$result['user_token'] : '';
                    $data['license_trial']   = isset($result['license_trial']) ? (int)$result['license_trial'] : 0;
                    $data['account_name_ob'] = isset($result['account_name_ob']) ? (string)$result['account_name_ob'] : '';
                }

                $data['key_is_ok'] = ! empty($result['valid'])
                    ? true
                    : false;

                update_option('cleantalk_data', $data);
            }
        }

        // Restoring initial blog
        switch_to_blog($initial_blog);
        // Actions for stand alone blog
    } else {
        apbct_activation__create_tables($sqls);
    }
}

/**
 * @return void
 */
function apbct_update_to_5_142_0()
{
    $sqls = array();

    $sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_ac_log` (
		`id` VARCHAR(40) NOT NULL,
		`ip` VARCHAR(40) NOT NULL,
		`entries` INT DEFAULT 0,
		`interval_start` INT NOT NULL,
		PRIMARY KEY (`id`));';

    $table_sfw_logs_columns = apbct_get_table_columns(APBCT_TBL_FIREWALL_LOG);
    if ( ! in_array('id', $table_sfw_logs_columns) ) {
        $status = ! in_array(
            'status',
            $table_sfw_logs_columns
        ) ? ' ADD COLUMN `status` ENUM(\'PASS_SFW\',\'DENY_SFW\',\'PASS_SFW_BY_WHITELIST\',\'PASS_SFW_BY_COOKIE\',\'DENY_ANTIBOT\',\'DENY_ANTICRAWLER\') NOT NULL AFTER `ip`,' : '';
        $sqls[] = 'ALTER TABLE `%scleantalk_sfw_logs`
		ADD COLUMN `id` VARCHAR(40) NOT NULL FIRST,
		' . $status . '
		DROP PRIMARY KEY,
		ADD PRIMARY KEY (`id`);';
    }


    apbct_activation__create_tables($sqls);
}

/**
 * @return void
 */
function apbct_update_to_5_142_1()
{
    $sqls   = array();
    $sqls[] = 'DELETE FROM `%scleantalk_sfw_logs` WHERE 1=1';

    $sqls[] = 'ALTER TABLE `%scleantalk_sfw_logs`
		CHANGE `status` `status` ENUM(\'PASS_SFW\',\'DENY_SFW\',\'PASS_SFW_BY_WHITELIST\',\'PASS_SFW_BY_COOKIE\',\'DENY_ANTICRAWLER\',\'DENY_ANTIFLOOD\') NOT NULL AFTER `ip`;';

    apbct_activation__create_tables($sqls);
}

/**
 * @return void
 */
function apbct_update_to_5_142_2()
{
    $sqls   = array();
    $sqls[] = 'DELETE FROM `%scleantalk_sfw_logs` WHERE 1=1';

    $sqls[] = 'ALTER TABLE `%scleantalk_sfw_logs`
		CHANGE `status` `status` ENUM(\'PASS_SFW\',\'DENY_SFW\',\'PASS_SFW__BY_WHITELIST\',\'PASS_SFW__BY_COOKIE\',\'DENY_ANTICRAWLER\',\'PASS_ANTICRAWLER\',\'DENY_ANTIFLOOD\',\'PASS_ANTIFLOOD\') NOT NULL AFTER `ip`;';

    apbct_activation__create_tables($sqls);
}

/**
 * @return void
 */
function apbct_update_to_5_142_3()
{
    global $apbct;

    $sqls   = array();
    $sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_sfw_logs` (
		`id` VARCHAR(40) NOT NULL,
		`ip` VARCHAR(15) NOT NULL,
		`status` ENUM(\'PASS_SFW\',\'DENY_SFW\',\'PASS_SFW__BY_WHITELIST\',\'PASS_SFW__BY_COOKIE\',\'DENY_ANTICRAWLER\',\'PASS_ANTICRAWLER\',\'DENY_ANTIFLOOD\',\'PASS_ANTIFLOOD\') NULL DEFAULT NULL,
		`all_entries` INT NOT NULL,
		`blocked_entries` INT NOT NULL,
		`entries_timestamp` INT NOT NULL,
		PRIMARY KEY (`id`));';

    apbct_activation__create_tables($sqls, $apbct->db_prefix);
}

/**
 * @return void
 */
function apbct_update_to_5_143_2()
{
    global $apbct;

    $sqls   = array();
    $sqls[] = 'DROP TABLE IF EXISTS `%scleantalk_sfw_logs`;';

    $sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_sfw_logs` (
		`id` VARCHAR(40) NOT NULL,
		`ip` VARCHAR(15) NOT NULL,
		`status` ENUM(\'PASS_SFW\',\'DENY_SFW\',\'PASS_SFW__BY_WHITELIST\',\'PASS_SFW__BY_COOKIE\',\'DENY_ANTICRAWLER\',\'PASS_ANTICRAWLER\',\'DENY_ANTIFLOOD\',\'PASS_ANTIFLOOD\') NULL DEFAULT NULL,
		`all_entries` INT NOT NULL,
		`blocked_entries` INT NOT NULL,
		`entries_timestamp` INT NOT NULL,
		PRIMARY KEY (`id`));';

    apbct_activation__create_tables($sqls, $apbct->db_prefix);
}

/**
 * @return void
 */
function apbct_update_to_5_146_1()
{
    global $apbct;

    $sqls   = array();
    $sqls[] = 'DROP TABLE IF EXISTS `%scleantalk_ac_log`;';

    $sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_ac_log` (
		`id` VARCHAR(40) NOT NULL,
		`ip` VARCHAR(40) NOT NULL,
		`ua` VARCHAR(40) NOT NULL,
		`entries` INT DEFAULT 0,
		`interval_start` INT NOT NULL,
		PRIMARY KEY (`id`));';

    apbct_activation__create_tables($sqls, $apbct->db_prefix);
}

/**
 * @return void
 */
function apbct_update_to_5_146_3()
{
    update_option('cleantalk_plugin_request_ids', array());
}

/**
 * @return void
 */
function apbct_update_to_5_148_0()
{
    $cron = new Cron();
    $cron->updateTask('antiflood__clear_table', 'apbct_antiflood__clear_table', 86400);
}

/**
 * @return void
 */
function apbct_update_to_5_149_2()
{
    global $apbct;

    $sqls   = array();
    $sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_ua_bl` (
			`id` INT(11) NOT NULL,
			`ua_template` VARCHAR(255) NULL DEFAULT NULL,
			`ua_status` TINYINT(1) NULL DEFAULT NULL,
			PRIMARY KEY ( `id` ),
			INDEX ( `ua_template` )			
		) DEFAULT CHARSET=utf8;'; // Don't remove the default charset!

    $sqls[] = 'DROP TABLE IF EXISTS `%scleantalk_sfw_logs`;';

    $sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_sfw_logs` (
		`id` VARCHAR(40) NOT NULL,
		`ip` VARCHAR(15) NOT NULL,
		`status` ENUM(\'PASS_SFW\',\'DENY_SFW\',\'PASS_SFW__BY_WHITELIST\',\'PASS_SFW__BY_COOKIE\',\'DENY_ANTICRAWLER\',\'PASS_ANTICRAWLER\',\'DENY_ANTICRAWLER_UA\',\'PASS_ANTICRAWLER_UA\',\'DENY_ANTIFLOOD\',\'PASS_ANTIFLOOD\') NULL DEFAULT NULL,
		`all_entries` INT NOT NULL,
		`blocked_entries` INT NOT NULL,
		`entries_timestamp` INT NOT NULL,
		`ua_id` INT(11) NULL DEFAULT NULL,
		`ua_name` VARCHAR(1024) NOT NULL, 
		PRIMARY KEY (`id`));';

    apbct_activation__create_tables($sqls, $apbct->db_prefix);
}

/**
 * @return void
 */
function apbct_update_to_5_150_0()
{
    global $wpdb;

    // Actions for WPMS
    if ( APBCT_WPMS ) {
        // Getting all blog ids
        $initial_blog = get_current_blog_id();
        $blogs        = array_keys($wpdb->get_results('SELECT blog_id FROM ' . $wpdb->blogs, OBJECT_K));

        foreach ( $blogs as $blog ) {
            switch_to_blog($blog);

            update_option('cleantalk_plugin_request_ids', array());
        }

        // Restoring initial blog
        switch_to_blog($initial_blog);
    }
}

/**
 * @return void
 */
function apbct_update_to_5_150_1()
{
    global $apbct;
    $sqls = array();
    // UA BL with default charset
    $sqls[] = 'DROP TABLE IF EXISTS `%scleantalk_ua_bl`;';
    $sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_ua_bl` (
			`id` INT(11) NOT NULL,
			`ua_template` VARCHAR(255) NULL DEFAULT NULL,
			`ua_status` TINYINT(1) NULL DEFAULT NULL,
			PRIMARY KEY ( `id` ),
			INDEX ( `ua_template` )			
		) DEFAULT CHARSET=utf8;'; // Don't remove the default charset!

    apbct_activation__create_tables($sqls, $apbct->db_prefix);
}

/**
 * @return void
 */
function apbct_update_to_5_151_1()
{
    global $apbct;
    $apbct->fw_stats['firewall_updating_id']         = isset($apbct->data['firewall_updating_id'])
        ? $apbct->data['firewall_updating_id']
        : '';
    $apbct->fw_stats['firewall_update_percent']      = isset($apbct->data['firewall_update_percent'])
        ? $apbct->data['firewall_update_percent']
        : 0;
    $apbct->fw_stats['firewall_updating_last_start'] = isset($apbct->data['firewall_updating_last_start'])
        ? $apbct->data['firewall_updating_last_start']
        : 0;
    $apbct->save('fw_stats');
}

/**
 * @return void
 * @throws Exception
 */
function apbct_update_to_5_151_3()
{
    global $wpdb, $apbct;
    $sql    = 'SHOW TABLES LIKE "%scleantalk_sfw";';
    $sql    = sprintf($sql, $wpdb->prefix); // Adding current blog prefix
    $result = $wpdb->get_var($sql);
    if ( ! $result ) {
        apbct_activation__create_tables(Schema::getSchema('sfw'), $apbct->db_prefix);
    }
    $apbct->fw_stats['firewall_updating_last_start'] = 0;
    $apbct->save('fw_stats');
    $apbct->stats['sfw']['entries'] = 0;
    $apbct->save('stats');
}

/**
 * @return void
 */
function apbct_update_to_5_151_6()
{
    global $apbct;
    $apbct->errorDelete('sfw_update', true);
}

/**
 * @return void
 */
function apbct_update_to_5_153_4()
{
    // Adding cooldown to sending SFW logs
    global $apbct;
    $apbct->stats['sfw']['sending_logs__timestamp'] = 0;
    $apbct->save('stats');
}

/**
 * @return void
 */
function apbct_update_to_5_154_0()
{
    global $apbct, $wpdb;

    // Old setting name => New setting name
    $keys_map = array(
        'spam_firewall'                                      => 'sfw__enabled',
        'registrations_test'                                 => 'forms__registrations_test',
        'comments_test'                                      => 'forms__comments_test',
        'contact_forms_test'                                 => 'forms__contact_forms_test',
        'general_contact_forms_test'                         => 'forms__general_contact_forms_test',
        'wc_checkout_test'                                   => 'forms__wc_checkout_test',
        'wc_register_from_order'                             => 'forms__wc_register_from_order',
        'search_test'                                        => 'forms__search_test',
        'check_external'                                     => 'forms__check_external',
        'check_external__capture_buffer'                     => 'forms__check_external__capture_buffer',
        'check_internal'                                     => 'forms__check_internal',
        'disable_comments__all'                              => 'comments__disable_comments__all',
        'disable_comments__posts'                            => 'comments__disable_comments__posts',
        'disable_comments__pages'                            => 'comments__disable_comments__pages',
        'disable_comments__media'                            => 'comments__disable_comments__media',
        'bp_private_messages'                                => 'comments__bp_private_messages',
        'check_comments_number'                              => 'comments__check_comments_number',
        'remove_old_spam'                                    => 'comments__remove_old_spam',
        'remove_comments_links'                              => 'comments__remove_comments_links',
        'show_check_links'                                   => 'comments__show_check_links',
        'manage_comments_on_public_page'                     => 'comments__manage_comments_on_public_page',
        'protect_logged_in'                                  => 'data__protect_logged_in',
        'use_ajax'                                           => 'data__use_ajax',
        'use_static_js_key'                                  => 'data__use_static_js_key',
        'general_postdata_test'                              => 'data__general_postdata_test',
        'set_cookies'                                        => 'data__set_cookies',
        'set_cookies__sessions'                              => 'data__set_cookies__sessions',
        'ssl_on'                                             => 'data__ssl_on',
        'show_adminbar'                                      => 'admin_bar__show',
        'all_time_counter'                                   => 'admin_bar__all_time_counter',
        'daily_counter'                                      => 'admin_bar__daily_counter',
        'sfw_counter'                                        => 'admin_bar__sfw_counter',
        'gdpr_enabled'                                       => 'gdpr__enabled',
        'gdpr_text'                                          => 'gdpr__text',
        'collect_details'                                    => 'misc__collect_details',
        'send_connection_reports'                            => 'misc__send_connection_reports',
        'async_js'                                           => 'misc__async_js',
        'debug_ajax'                                         => 'misc__debug_ajax',
        'store_urls'                                         => 'misc__store_urls',
        'store_urls__sessions'                               => 'misc__store_urls__sessions',
        'complete_deactivation'                              => 'misc__complete_deactivation',
        'use_buitin_http_api'                                => 'wp__use_builtin_http_api',
        'comment_notify'                                     => 'wp__comment_notify',
        'comment_notify__roles'                              => 'wp__comment_notify__roles',
        'dashboard_widget__show'                             => 'wp__dashboard_widget__show',
        'allow_custom_key'                                   => 'multisite__allow_custom_key',
        'allow_custom_settings'                              => 'multisite__allow_custom_settings',
        'white_label'                                        => 'multisite__white_label',
        'white_label__plugin_name'                           => 'multisite__white_label__plugin_name',
        'use_settings_template'                              => 'multisite__use_settings_template',
        'use_settings_template_apply_for_new'                => 'multisite__use_settings_template_apply_for_new',
        'use_settings_template_apply_for_current'            => 'multisite__use_settings_template_apply_for_current',
        'use_settings_template_apply_for_current_list_sites' => 'multisite__use_settings_template_apply_for_current_list_sites',
    );

    if ( is_multisite() ) {
        $network_settings = get_site_option('cleantalk_network_settings');

        if ( $network_settings ) {
            $_network_settings = array();
            // replacing old key to new keys
            foreach ( $network_settings as $key => $value ) {
                if ( array_key_exists($key, $keys_map) ) {
                    $_network_settings[$keys_map[$key]] = $value;
                } else {
                    $_network_settings[$key] = $value;
                }
            }
            if ( ! empty($_network_settings) ) {
                update_site_option('cleantalk_network_settings', $_network_settings);
            }
        }

        $initial_blog = get_current_blog_id();
        $blogs        = array_keys($wpdb->get_results('SELECT blog_id FROM ' . $wpdb->blogs, OBJECT_K));
        foreach ( $blogs as $blog ) {
            switch_to_blog($blog);

            $settings = get_option('cleantalk_settings');

            if ( $settings ) {
                // replacing old key to new keys
                $_settings = array();
                foreach ( $settings as $key => $value ) {
                    if ( array_key_exists($key, $keys_map) ) {
                        $_settings[$keys_map[$key]] = $value;
                    } else {
                        $_settings[$key] = $value;
                    }
                }
                if ( ! empty($_settings) ) {
                    update_option('cleantalk_settings', $_settings);
                }
            }
        }
        switch_to_blog($initial_blog);
    } else {
        $apbct->data['current_settings_template_id']   = null;
        $apbct->data['current_settings_template_name'] = null;
        $apbct->saveData();

        $settings = (array)$apbct->settings;

        if ( $settings ) {
            $_settings = array();
            // replacing old key to new keys
            foreach ( $settings as $key => $value ) {
                if ( array_key_exists($key, $keys_map) ) {
                    $_settings[$keys_map[$key]] = $value;
                } else {
                    $_settings[$key] = $value;
                }
            }

            $apbct->settings = $_settings;
            $apbct->saveSettings();
        }
    }

    $sqls = array();

    $sqls[] = 'DROP TABLE IF EXISTS `%scleantalk_sfw_logs`;';

    $sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_sfw_logs` (
		`id` VARCHAR(40) NOT NULL,
		`ip` VARCHAR(15) NOT NULL,
		`status` ENUM(\'PASS_SFW\',\'DENY_SFW\',\'PASS_SFW__BY_WHITELIST\',\'PASS_SFW__BY_COOKIE\',\'DENY_ANTICRAWLER\',\'PASS_ANTICRAWLER\',\'DENY_ANTICRAWLER_UA\',\'PASS_ANTICRAWLER_UA\',\'DENY_ANTIFLOOD\',\'PASS_ANTIFLOOD\',\'DENY_ANTIFLOOD_UA\',\'PASS_ANTIFLOOD_UA\') NULL DEFAULT NULL,
		`all_entries` INT NOT NULL,
		`blocked_entries` INT NOT NULL,
		`entries_timestamp` INT NOT NULL,
		`ua_id` INT(11) NULL DEFAULT NULL,
		`ua_name` VARCHAR(1024) NOT NULL, 
		PRIMARY KEY (`id`));';

    apbct_activation__create_tables($sqls, $apbct->db_prefix);
}

/**
 * @return void
 */
function apbct_update_to_5_156_0()
{
    global $apbct;

    $apbct->remote_calls['debug']     = array('last_call' => 0, 'cooldown' => 0);
    $apbct->remote_calls['debug_sfw'] = array('last_call' => 0, 'cooldown' => 0);
    $apbct->save('remote_calls');

    $cron = new Cron();
    $cron->updateTask('sfw_update', 'apbct_sfw_update__init', 86400, time() + 42300);
}

/**
 * @return void
 */
function apbct_update_to_5_157_0()
{
    global $apbct;

    $apbct->remote_calls['sfw_update__worker'] = array('last_call' => 0, 'cooldown' => 0);
    $apbct->save('remote_calls');

    if ( ! empty($apbct->settings['data__set_cookies__sessions']) ) {
        $apbct->settings['data__set_cookies'] = 2;
    }
    $apbct->data['ajax_type'] = 'rest';

    $apbct->save('settings');
    $apbct->save('data');

    cleantalk_get_brief_data($apbct->api_key);
}

/**
 * @return void
 */
function apbct_update_to_5_158_0()
{
    global $apbct, $wpdb;
    // change name for prevent psalm false positive
    $_wpdb = $wpdb;

    $sqls = array();

    $table_sfw_columns      = apbct_get_table_columns(APBCT_TBL_FIREWALL_DATA);
    $table_sfw_logs_columns = apbct_get_table_columns(APBCT_TBL_FIREWALL_LOG);

    if ( ! in_array('source', $table_sfw_columns) ) {
        $sqls[] = 'ALTER TABLE `%scleantalk_sfw` ADD COLUMN `source` TINYINT(1) NULL DEFAULT NULL AFTER `status`;';
    }

    if ( ! in_array('source', $table_sfw_logs_columns) ) {
        $network   = ! in_array(
            'network',
            $table_sfw_logs_columns
        ) ? ' ADD COLUMN `network` VARCHAR(20) NULL DEFAULT NULL AFTER `source`,' : '';
        $first_url = ! in_array(
            'first_url',
            $table_sfw_logs_columns
        ) ? ' ADD COLUMN `first_url` VARCHAR(100) NULL DEFAULT NULL AFTER `network`,' : '';
        $last_url  = ! in_array(
            'last_url',
            $table_sfw_logs_columns
        ) ? ' ADD COLUMN `last_url` VARCHAR(100) NULL DEFAULT NULL AFTER `first_url`' : '';
        $sqls[]    = 'ALTER TABLE `%scleantalk_sfw_logs`'
                     . ' ADD COLUMN `source` TINYINT(1) NULL DEFAULT NULL AFTER `ua_name`,'
                     . $network
                     . $first_url
                     . $last_url
                     . ';';
    }

    if ( APBCT_WPMS ) {
        // Getting all blog ids
        $initial_blog = get_current_blog_id();
        $blogs        = array_keys($_wpdb->get_results('SELECT blog_id FROM ' . $_wpdb->blogs, OBJECT_K));

        foreach ( $blogs as $blog ) {
            switch_to_blog($blog);
            apbct_activation__create_tables($sqls);
        }

        // Restoring initial blog
        switch_to_blog($initial_blog);
    } else {
        apbct_activation__create_tables($sqls);
    }

    // Update from fix branch
    if ( APBCT_WPMS && is_main_site() ) {
        $wp_blogs           = $_wpdb->get_results('SELECT blog_id, site_id FROM ' . $_wpdb->blogs, OBJECT_K);
        $current_sites_list = $apbct->settings['multisite__use_settings_template_apply_for_current_list_sites'];

        if ( is_array($wp_blogs) && is_array($current_sites_list) ) {
            foreach ( $wp_blogs as $blog ) {
                $blog_details = get_blog_details(array('blog_id' => $blog->blog_id));
                if ( $blog_details ) {
                    $site_list_index = array_search($blog_details->blogname, $current_sites_list, true);
                    if ( $site_list_index !== false ) {
                        $current_sites_list[$site_list_index] = $blog_details->id;
                    }
                }
            }
            $apbct->settings['multisite__use_settings_template_apply_for_current_list_sites'] = $current_sites_list;
            $apbct->settings['comments__hide_website_field']                                  = '0';
            $apbct->settings['data__pixel']                                                   = '0';
            $apbct->saveSettings();
        }
    } else {
        $apbct->settings['comments__hide_website_field'] = '0';
        $apbct->settings['data__pixel']                  = '0';
        $apbct->saveSettings();
    }
}

/**
 * @return void
 */
function apbct_update_to_5_158_2()
{
    global $apbct;
    $apbct->stats['cron']['last_start'] = 0;
    $apbct->save('stats');
}

/**
 * @return void
 */
function apbct_update_to_5_159_6()
{
    global $wpdb;

    $ct_cron = new Cron();

    if ( is_multisite() ) {
        $initial_blog = get_current_blog_id();
        $blogs        = array_keys($wpdb->get_results('SELECT blog_id FROM ' . $wpdb->blogs, OBJECT_K));
        foreach ( $blogs as $blog ) {
            switch_to_blog($blog);
            // Cron tasks
            $ct_cron->addTask(
                'check_account_status',
                'ct_account_status_check',
                3600,
                time() + 1800
            ); // Checks account status
            $ct_cron->addTask(
                'delete_spam_comments',
                'ct_delete_spam_comments',
                3600,
                time() + 3500
            ); // Formerly ct_hourly_event_hook()
            $ct_cron->addTask(
                'send_feedback',
                'ct_send_feedback',
                3600,
                time() + 3500
            ); // Formerly ct_hourly_event_hook()
            $ct_cron->addTask('sfw_update', 'apbct_sfw_update__init', 86400);  // SFW update
            $ct_cron->addTask('send_sfw_logs', 'ct_sfw_send_logs', 3600, time() + 1800); // SFW send logs
            $ct_cron->addTask(
                'get_brief_data',
                'cleantalk_get_brief_data',
                86400,
                time() + 3500
            ); // Get data for dashboard widget
            $ct_cron->addTask(
                'send_connection_report',
                'ct_mail_send_connection_report',
                86400,
                time() + 3500
            ); // Send connection report to welcome@cleantalk.org
            $ct_cron->addTask(
                'antiflood__clear_table',
                'apbct_antiflood__clear_table',
                86400,
                time() + 300
            ); // Clear Anti-Flood table
        }
        switch_to_blog($initial_blog);
    } else {
        // Cron tasks
        $ct_cron->addTask(
            'check_account_status',
            'ct_account_status_check',
            3600,
            time() + 1800
        ); // Checks account status
        $ct_cron->addTask(
            'delete_spam_comments',
            'ct_delete_spam_comments',
            3600,
            time() + 3500
        ); // Formerly ct_hourly_event_hook()
        $ct_cron->addTask('send_feedback', 'ct_send_feedback', 3600, time() + 3500); // Formerly ct_hourly_event_hook()
        $ct_cron->addTask('sfw_update', 'apbct_sfw_update__init', 86400);  // SFW update
        $ct_cron->addTask('send_sfw_logs', 'ct_sfw_send_logs', 3600, time() + 1800); // SFW send logs
        $ct_cron->addTask(
            'get_brief_data',
            'cleantalk_get_brief_data',
            86400,
            time() + 3500
        ); // Get data for dashboard widget
        $ct_cron->addTask(
            'send_connection_report',
            'ct_mail_send_connection_report',
            86400,
            time() + 3500
        ); // Send connection report to welcome@cleantalk.org
        $ct_cron->addTask(
            'antiflood__clear_table',
            'apbct_antiflood__clear_table',
            86400,
            time() + 300
        ); // Clear Anti-Flood table
    }
}

/**
 * @return void
 */
function apbct_update_to_5_159_7()
{
    global $wpdb;
    // change name for prevent psalm false positive
    $_wpdb = $wpdb;

    $sqls = array();

    $table_sfw_columns      = apbct_get_table_columns(APBCT_TBL_FIREWALL_DATA);
    $table_sfw_logs_columns = apbct_get_table_columns(APBCT_TBL_FIREWALL_LOG);

    if ( ! in_array('source', $table_sfw_columns) ) {
        $sqls[] = 'ALTER TABLE `%scleantalk_sfw` ADD COLUMN `source` TINYINT(1) NULL DEFAULT NULL AFTER `status`;';
    }

    if ( ! in_array('source', $table_sfw_logs_columns) ) {
        $network   = ! in_array(
            'network',
            $table_sfw_logs_columns
        ) ? ' ADD COLUMN `network` VARCHAR(20) NULL DEFAULT NULL AFTER `source`,' : '';
        $first_url = ! in_array(
            'first_url',
            $table_sfw_logs_columns
        ) ? ' ADD COLUMN `first_url` VARCHAR(100) NULL DEFAULT NULL AFTER `network`,' : '';
        $last_url  = ! in_array(
            'last_url',
            $table_sfw_logs_columns
        ) ? ' ADD COLUMN `last_url` VARCHAR(100) NULL DEFAULT NULL AFTER `first_url`' : '';
        $sqls[]    = 'ALTER TABLE `%scleantalk_sfw_logs`'
                     . ' ADD COLUMN `source` TINYINT(1) NULL DEFAULT NULL AFTER `ua_name`,'
                     . $network
                     . $first_url
                     . $last_url
                     . ';';
    }

    if ( ! empty($sqls) ) {
        if ( APBCT_WPMS ) {
            // Getting all blog ids
            $initial_blog = get_current_blog_id();
            $blogs        = array_keys($_wpdb->get_results('SELECT blog_id FROM ' . $_wpdb->blogs, OBJECT_K));

            foreach ( $blogs as $blog ) {
                switch_to_blog($blog);
                apbct_activation__create_tables($sqls);
            }

            // Restoring initial blog
            switch_to_blog($initial_blog);
        } else {
            apbct_activation__create_tables($sqls);
        }
    }
}

/**
 * @return  void
 */
function apbct_update_to_5_159_9()
{
    $cron = new Cron();
    $cron->addTask('rotate_moderate', 'apbct_rotate_moderate', 86400, time() + 3500); // Rotate moderate server
}

/**
 * @return  void
 */
function apbct_update_to_5_160_4()
{
    global $apbct;

    $apbct->settings['sfw__random_get'] = '1';
    $apbct->saveSettings();

    apbct_remove_upd_folder(APBCT_DIR_PATH . '/fw_files');

    if ( $apbct->is_multisite ) {
        $apbct->network_settings = array_merge((array)$apbct->network_settings, $apbct->default_network_settings);
        $apbct->save('network_settings');
    }

    apbct_remove_upd_folder(ABSPATH . '/wp-admin/fw_files');
    apbct_remove_upd_folder(Server::get('DOCUMENT_ROOT') . '/fw_files');
    $file_path = Server::get('DOCUMENT_ROOT') . '/fw_filesindex.php';
    if ( is_file($file_path) && is_writable($file_path) ) {
        unlink($file_path);
    }
}

function apbct_update_to_5_161_1()
{
    global $apbct;

    if ( $apbct->is_multisite ) {
        $apbct->network_settings = array_merge((array)$apbct->network_settings, $apbct->default_network_settings);
        // Migrate old WPMS to the new wpms mode
        if ( isset($apbct->network_settings['multisite__allow_custom_key']) ) {
            if ( $apbct->network_settings['multisite__allow_custom_key'] == 1 ) {
                $apbct->network_settings['multisite__work_mode'] = 1;
            } else {
                $apbct->network_settings['multisite__work_mode'] = 2;
            }
        }
        $apbct->saveNetworkSettings();
    }
}

function apbct_update_to_5_161_2()
{
    global $apbct;
    // Set type of the alt cookies
    if ( $apbct->settings['data__set_cookies'] == 2 ) {
        // Check custom ajax availability
        $res_custom_ajax = Helper::httpRequestGetResponseCode(esc_url(APBCT_URL_PATH . '/lib/Cleantalk/ApbctWP/Ajax.php'));
        if ( $res_custom_ajax != 400 ) {
            // Check rest availability
            $res_rest = Helper::httpRequestGetResponseCode(esc_url(apbct_get_rest_url()));
            if ( $res_rest != 200 ) {
                // Check WP ajax availability
                $res_ajax = Helper::httpRequestGetResponseCode(admin_url('admin-ajax.php'));
                if ( $res_ajax != 400 ) {
                    // There is no available alt cookies types. Cookies will be disabled.
                    $apbct->settings['data__set_cookies'] = 0;
                } else {
                    $apbct->data['ajax_type'] = 'admin_ajax';
                }
            } else {
                $apbct->data['ajax_type'] = 'rest';
            }
        } else {
            $apbct->data['ajax_type'] = 'custom_ajax';
        }
        $apbct->saveSettings();
        $apbct->saveData();
    }
}

/**
 * 5.162
 */
function apbct_update_to_5_162_0()
{
    global $apbct;

    $apbct->settings['forms__wc_honeypot'] = '1';
    $apbct->saveSettings();
}

/**
 * 5.162.1
 */
function apbct_update_to_5_162_1()
{
    global $apbct;

    if (
        ! isset($apbct->stats['sfw']['update_period']) ||
        (isset($apbct->stats['sfw']['update_period']) && $apbct->stats['sfw']['update_period'] == 0)
    ) {
        $apbct->stats['sfw']['update_period'] = 14400;
        $apbct->save('stats');
    }

    // Set type of the AJAX handler for the ajax js
    if ( $apbct->settings['data__use_ajax'] == 1 ) {
        // Check custom ajax availability
        $res_custom_ajax = Helper::httpRequestGetResponseCode(
            esc_url(APBCT_URL_PATH . '/lib/Cleantalk/ApbctWP/Ajax.php')
        );
        if ( $res_custom_ajax != 400 ) {
            // Check rest availability
            $res_rest = Helper::httpRequestGetResponseCode(esc_url(apbct_get_rest_url()));
            if ( $res_rest != 200 ) {
                // Check WP ajax availability
                $res_ajax = Helper::httpRequestGetResponseCode(admin_url('admin-ajax.php'));
                if ( $res_ajax != 400 ) {
                    // There is no available alt cookies types. Cookies will be disabled.
                    $apbct->settings['data__use_ajax'] = 0;
                } else {
                    $apbct->data['ajax_type'] = 'admin_ajax';
                }
            } else {
                $apbct->data['ajax_type'] = 'rest';
            }
        } else {
            $apbct->data['ajax_type'] = 'custom_ajax';
        }
        $apbct->saveSettings();
        $apbct->saveData();
    }

    // Migrate old WPMS to the new wpms mode
    if ( isset($apbct->network_settings['multisite__allow_custom_key']) ) {
        if ( $apbct->network_settings['multisite__allow_custom_key'] == 1 ) {
            $apbct->network_settings['multisite__work_mode'] = 1;
        } else {
            $apbct->network_settings['multisite__work_mode'] = 2;
        }
        $apbct->saveNetworkSettings();
    }
}

/**
 * 5.164
 */
function apbct_update_to_5_164_0()
{
    global $apbct;

    $alt_cookies_type = isset($apbct->settings['data__set_cookies__alt_sessions_type'])
        ? $apbct->settings['data__set_cookies__alt_sessions_type']
        : false;

    switch ((int)$alt_cookies_type) {
        case 0:
            $alt_cookies_type = 'rest';
            break;
        case 1:
            $alt_cookies_type = 'custom_ajax';
            break;
        case 2:
            $alt_cookies_type = 'admin_ajax';
            break;
    }

    $apbct->data['ajax_type'] = $alt_cookies_type;
    $apbct->saveData();
}
