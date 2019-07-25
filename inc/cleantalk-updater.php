<?php

function apbct_run_update_actions($current_version, $new_version){
	
	$current_version = apbct_version_standartization($current_version);
	$new_version     = apbct_version_standartization($new_version);
	
	$current_version_str = implode('.', $current_version);
	$new_version_str     = implode('.', $new_version);
	
	for($ver_major = $current_version[0]; $ver_major <= $new_version[0]; $ver_major++){
		for($ver_minor = 0; $ver_minor <= 200; $ver_minor++){
			for($ver_fix = 0; $ver_fix <= 10; $ver_fix++){
				
				if(version_compare("{$ver_major}.{$ver_minor}.{$ver_fix}", $current_version_str, '<='))
					continue;
				
				if(function_exists("apbct_update_to_{$ver_major}_{$ver_minor}_{$ver_fix}")){
					$result = call_user_func("apbct_update_to_{$ver_major}_{$ver_minor}_{$ver_fix}");
					if(!empty($result['error']))
						break;
				}
				
				if(version_compare("{$ver_major}.{$ver_minor}.{$ver_fix}", $new_version_str, '>='))
					break(2);
				
			}
		}
	}
	
	return true;
	
}

function apbct_version_standartization($version){
	
	$version = explode('.', $version);
	$version = !empty($version) ? $version : array();
	
	$version[0] = !empty($version[0]) ? (int)$version[0] : 0;
	$version[1] = !empty($version[1]) ? (int)$version[1] : 0;
	$version[2] = !empty($version[2]) ? (int)$version[2] : 0;
	
	return $version;
}

function apbct_update_to_5_50_0(){
	global $wpdb;
	$wpdb->query('CREATE TABLE IF NOT EXISTS `'. APBCT_TBL_FIREWALL_DATA .'` (
		`network` int(11) unsigned NOT NULL,
		`mask` int(11) unsigned NOT NULL,
		INDEX (  `network` ,  `mask` )
		) ENGINE = MYISAM ;');
		
	$wpdb->query('CREATE TABLE IF NOT EXISTS `'. APBCT_TBL_FIREWALL_LOG .'` (
		`ip` VARCHAR(15) NOT NULL , 
		`all` INT NOT NULL , 
		`blocked` INT NOT NULL , 
		`timestamp` INT NOT NULL , 
		PRIMARY KEY (`ip`)) 
		ENGINE = MYISAM;');
}

function apbct_update_to_5_56_0(){
	if (!wp_next_scheduled('cleantalk_update_sfw_hook'))
		wp_schedule_event(time()+1800, 'daily', 'cleantalk_update_sfw_hook' );
}
function apbct_update_to_5_70_0(){
	
	global $wpdb;
	
	if(!in_array('all_entries', $wpdb->get_col('DESC '. APBCT_TBL_FIREWALL_LOG, 0))){
		$wpdb->query('ALTER TABLE `'. APBCT_TBL_FIREWALL_LOG .'`
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
	CleantalkCron::addTask('check_account_status', 'ct_account_status_check',  3600,  time()+1800); // New
	CleantalkCron::addTask('delete_spam_comments', 'ct_delete_spam_comments',  3600,  time()+3500);
	CleantalkCron::addTask('send_feedback',        'ct_send_feedback',         3600,  time()+3500);
	CleantalkCron::addTask('sfw_update',           'ct_sfw_update',            86400, time()+43200);
	CleantalkCron::addTask('send_sfw_logs',        'ct_sfw_send_logs',         3600,  time()+1800); // New
	CleantalkCron::addTask('get_brief_data',       'cleantalk_get_brief_data', 86400, time()+3500);
}
function apbct_update_to_5_74_0(){
	CleantalkCron::removeTask('send_daily_request');
}

function apbct_update_to_5_97_0(){
	
	global $apbct;
	
	if(count($apbct->data['connection_reports']['negative_report']) >= 20)
		$apbct->data['connection_reports']['negative_report'] = array_slice($apbct->data['connection_reports']['negative_report'], -20, 20);
	
	$apbct->saveData();
}

function apbct_update_to_5_109_0(){
	
	global $apbct, $wpdb;
	
	if(apbct_is_plugin_active_for_network($apbct->base_name) && !defined('CLEANTALK_ACCESS_KEY')){
		
		$sfw_data_query = 'CREATE TABLE IF NOT EXISTS `%s` (
			`network` int(11) unsigned NOT NULL,
			`mask` int(11) unsigned NOT NULL,
			INDEX (  `network` ,  `mask` )
			) ENGINE = MYISAM ;';

		$sfw_log_query = 'CREATE TABLE IF NOT EXISTS `%s` (
			`ip` VARCHAR(15) NOT NULL,
			`all_entries` INT NOT NULL,
			`blocked_entries` INT NOT NULL,
			`entries_timestamp` INT NOT NULL,
			PRIMARY KEY (`ip`)) 
			ENGINE = MYISAM;';

		$initial_blog  = get_current_blog_id();
		$blogs = array_keys($wpdb->get_results('SELECT blog_id FROM '. $wpdb->blogs, OBJECT_K));
		foreach ($blogs as $blog) {
			switch_to_blog($blog);
			$wpdb->query(sprintf($sfw_data_query, $wpdb->prefix . 'cleantalk_sfw'));       // Table for SpamFireWall data
			$wpdb->query(sprintf($sfw_log_query,  $wpdb->prefix . 'cleantalk_sfw_logs'));  // Table for SpamFireWall logs
			// Cron tasks
			CleantalkCron::addTask('check_account_status',  'ct_account_status_check',        3600,  time()+1800); // Checks account status
			CleantalkCron::addTask('delete_spam_comments',  'ct_delete_spam_comments',        3600,  time()+3500); // Formerly ct_hourly_event_hook()
			CleantalkCron::addTask('send_feedback',         'ct_send_feedback',               3600,  time()+3500); // Formerly ct_hourly_event_hook()
			CleantalkCron::addTask('sfw_update',            'ct_sfw_update',                  86400, time()+300);  // SFW update
			CleantalkCron::addTask('send_sfw_logs',         'ct_sfw_send_logs',               3600,  time()+1800); // SFW send logs
			CleantalkCron::addTask('get_brief_data',        'cleantalk_get_brief_data',       86400, time()+3500); // Get data for dashboard widget
			CleantalkCron::addTask('send_connection_report','ct_mail_send_connection_report', 86400, time()+3500); // Send connection report to welcome@cleantalk.org
		}
		switch_to_blog($initial_blog);
	}
}

function apbct_update_to_5_110_0(){
	global $apbct;
	unset($apbct->data['last_remote_call']);
	$apbct->saveData;
	$apbct->save('remote_calls');
}

function apbct_update_to_5_115_1(){
	ct_sfw_update();
}

function apbct_update_to_5_116_0(){
	
	global $apbct, $wpdb;
	
	$apbct->settings['store_urls'] = 0;
	$apbct->settings['store_urls__sessions'] = 0;
	$apbct->saveSettings();
	
	$wpdb->query('CREATE TABLE IF NOT EXISTS `'. APBCT_TBL_SESSIONS .'` (
		`id` VARCHAR(64) NOT NULL,
		`name` TEXT NOT NULL,
		`value` TEXT NULL,
		`last_update` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`, `name`(10)))
		ENGINE = MYISAM;'
	);
}

function apbct_update_to_5_116_1(){
	
	global $wpdb;
	
	$wpdb->query('CREATE TABLE IF NOT EXISTS `'. APBCT_TBL_SESSIONS .'` (
		`id` VARCHAR(64) NOT NULL,
		`name` TEXT NOT NULL,
		`value` TEXT NULL,
		`last_update` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`, `name`(10)))
		ENGINE = MYISAM;'
	);
}

function apbct_update_to_5_116_2(){
	
	global $wpdb;
	
	$wpdb->query('CREATE TABLE IF NOT EXISTS `'. APBCT_TBL_SESSIONS .'` (
		`id` VARCHAR(64) NOT NULL,
		`name` TEXT NOT NULL,
		`value` TEXT NULL DEFAULT NULL,
		`last_update` DATETIME NULL DEFAULT NULL,
		PRIMARY KEY (`id`, `name`(10)))
		ENGINE = MYISAM;'
	);
}

function apbct_update_to_5_118_0(){
	global $wpdb;
	$wpdb->query(
		'DELETE
			FROM `'. APBCT_TBL_SESSIONS .'`
			WHERE last_update < NOW() - INTERVAL '. APBCT_SEESION__LIVE_TIME .' SECOND;'
	);
	delete_option('cleantalk_server');
}

function apbct_update_to_5_118_2(){
	global $apbct;
	$apbct->data['connection_reports'] = $apbct->def_data['connection_reports'];
	$apbct->data['connection_reports']['since'] = date('d M');
	$apbct->saveData();
}

function apbct_update_to_5_119(){
	
	global $wpdb;
	
	$wpdb->query('DROP TABLE IF EXISTS `'. $wpdb->prefix.'cleantalk_sessions`;');  //  Deleting session table
	
	// SFW data
	$sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_sfw` (
		`network` int(11) unsigned NOT NULL,
		`mask` int(11) unsigned NOT NULL,
		INDEX (  `network` ,  `mask` )
		) ENGINE = MYISAM ;';
	
	// SFW log
	$sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_sfw_logs` (
		`ip` VARCHAR(15) NOT NULL,
		`all_entries` INT NOT NULL,
		`blocked_entries` INT NOT NULL,
		`entries_timestamp` INT NOT NULL,
		PRIMARY KEY (`ip`)) 
		ENGINE = MYISAM;';
	
	// Sessions
	$sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_sessions` (
		`id` VARCHAR(64) NOT NULL,
		`name` VARCHAR(64) NOT NULL,
		`value` TEXT NULL DEFAULT NULL,
		`last_update` DATETIME NULL DEFAULT NULL,
		PRIMARY KEY (`id`(64), `name`(64)))
		ENGINE = MYISAM;';
	
	apbct_activation__create_tables($sqls);
	
	// WPMS
	if(is_multisite()){
		global $wpdb;
		$initial_blog  = get_current_blog_id();
		$blogs = array_keys($wpdb->get_results('SELECT blog_id FROM '. $wpdb->blogs, OBJECT_K));
		foreach ($blogs as $blog) {
			switch_to_blog($blog);
			$wpdb->query('DROP TABLE IF EXISTS `'. $wpdb->prefix.'cleantalk_sessions`;');  //  Deleting session table
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