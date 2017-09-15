<?php

function ct_run_update_actions($current_version, $new_version, $start_version){
	
	global $ct_data, $wpdb;
	
	$current_version = ct_version_standartization($current_version);
	$new_version     = ct_version_standartization($new_version);
	
	//Update actions
	if($current_version[0] <= 5){
		if($current_version[1] <= 49){
			if($current_version[2] <= 1){
				$wpdb->query("CREATE TABLE IF NOT EXISTS `".$wpdb->base_prefix."cleantalk_sfw` (
					`network` int(11) unsigned NOT NULL,
					`mask` int(11) unsigned NOT NULL,
					INDEX (  `network` ,  `mask` )
					) ENGINE = MYISAM ;");
					
				$wpdb->query("CREATE TABLE IF NOT EXISTS `".$wpdb->base_prefix."cleantalk_sfw_logs` (
					`ip` VARCHAR(15) NOT NULL , 
					`all` INT NOT NULL , 
					`blocked` INT NOT NULL , 
					`timestamp` INT NOT NULL , 
					PRIMARY KEY (`ip`)) 
					ENGINE = MYISAM;");
			}
		}
		if($current_version[1] <= 55){
			if (!wp_next_scheduled('cleantalk_update_sfw_hook'))
				wp_schedule_event(time()+1800, 'daily', 'cleantalk_update_sfw_hook' );
		}
		if($current_version[1] <= 69){
			
			if(!in_array('all_entries', $wpdb->get_col("DESC " . $wpdb->base_prefix."cleantalk_sfw_logs", 0))){
				$wpdb->query("ALTER TABLE `".$wpdb->base_prefix."cleantalk_sfw_logs`
					CHANGE `all` `all_entries` INT(11) NOT NULL,
					CHANGE `blocked` `blocked_entries` INT(11) NOT NULL,
					CHANGE `timestamp` `entries_timestamp` INT(11) NOT NULL;"
				);
			}
			
			// Deleting usless data
			unset($ct_data['db_refreshed'], $ct_data['last_sfw_send'], $ct_data['next_account_status_check']);
			update_option('cleantalk_data', $ct_data);
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
		if($current_version[1] <= 73){
			CleantalkCron::removeTask('send_daily_request');
		}
	}
	
	return true;
	
}

function ct_version_standartization($version){
	
	$version = explode('.', $version);
	$version = !empty($version) ? $version : array();
	
	$version[0] = !empty($version[0]) ? (int)$version[0] : 0;
	$version[1] = !empty($version[1]) ? (int)$version[1] : 0;
	$version[2] = !empty($version[2]) ? (int)$version[2] : 0;
	
	return $version;
}

?>