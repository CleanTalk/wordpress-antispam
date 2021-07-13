<?php


namespace Cleantalk\ApbctWP;


use Cleantalk\Common\Schema;

class Activator {

	public static function activation( $network_wide, $concrete_blog_id = null )
	{
		global $wpdb, $apbct;

		if( is_null( $concrete_blog_id ) ) {
			// Do actions for the all blogs on activation
			$apbct->stats['plugin']['activation_previous__timestamp'] = $apbct->stats['plugin']['activation__timestamp'];
			$apbct->stats['plugin']['activation__timestamp'] = time();
			$apbct->stats['plugin']['activation__times'] += 1;
			$apbct->save('stats');

			if( $network_wide && ! defined( 'CLEANTALK_ACCESS_KEY' ) ){
				$initial_blog  = get_current_blog_id();
				$blogs = array_keys( $wpdb->get_results( 'SELECT blog_id FROM '. $wpdb->blogs, OBJECT_K ) );
				foreach ( $blogs as $blog ) {
					switch_to_blog( $blog );
					self::create_tables( Schema::getSchema() );
					self::setCronJobs();
				}
				switch_to_blog( $initial_blog );
			}else{
				self::setCronJobs();
				apbct_activation__create_tables( Schema::getSchema() );
				ct_account_status_check(null, false);
			}

			// Additional options
			add_option( 'ct_plugin_do_activation_redirect', true );
			apbct_add_admin_ip_to_swf_whitelist( null, null );

		} else {
			// Do actions for the new blog created
			if ( apbct_is_plugin_active_for_network('cleantalk-spam-protect/cleantalk.php') ){

				$settings = get_option('cleantalk_settings');

				switch_to_blog( $concrete_blog_id );

				self::setCronJobs( false );
				apbct_activation__create_tables( Schema::getSchema() );
				apbct_sfw_update__init( 3 ); // Updating SFW
				ct_account_status_check(null, false);

				if (isset($settings['multisite__use_settings_template_apply_for_new']) && $settings['multisite__use_settings_template_apply_for_new'] == 1) {
					update_option('cleantalk_settings', $settings);
				}
				restore_current_blog();
			}
		}
	}

	/**
	 * Creating specific tables
	 *
	 * @param $sqls
	 * @param string $db_prefix
	 *
	 * @return void
	 */
	public static function create_tables( $sqls, $db_prefix = '' )
	{

		if( ! is_array( $sqls ) && empty( $sqls ) ) {
			return;
		}

		global $wpdb;

		$db_prefix = $db_prefix ?: $wpdb->prefix;

		$wpdb->show_errors = false;
		foreach( $sqls as $sql ){
			$sql = sprintf( $sql, $db_prefix ); // Adding current blog prefix
			$result = $wpdb->query( $sql );
			if( $result === false ) {
				$errors[] = "Failed.\nQuery: $wpdb->last_query\nError: $wpdb->last_error";
			}
		}
		$wpdb->show_errors = true;

		// Logging errors
		if(!empty($errors)) {
			apbct_log( $errors );
		}
	}

	/**
	 * Set CRON jobs
	 *
	 * @param bool $sfw_update_include
	 */
	public static function setCronJobs( $sfw_update_include = true )
	{
		$ct_cron = new Cron();

		// Cron tasks
		if( $sfw_update_include ) {
			$ct_cron->addTask('sfw_update',            'apbct_sfw_update__init',         86400 );  // SFW update
		}
		$ct_cron->addTask('check_account_status',  'ct_account_status_check',        3600,  time() + 1800); // Checks account status
		$ct_cron->addTask('delete_spam_comments',  'ct_delete_spam_comments',        3600,  time() + 3500); // Formerly ct_hourly_event_hook()
		$ct_cron->addTask('send_feedback',         'ct_send_feedback',               3600,  time() + 3500); // Formerly ct_hourly_event_hook()
		$ct_cron->addTask('send_sfw_logs',         'ct_sfw_send_logs',               3600,  time() + 1800); // SFW send logs
		$ct_cron->addTask('get_brief_data',        'cleantalk_get_brief_data',       86400, time() + 3500); // Get data for dashboard widget
		$ct_cron->addTask('send_connection_report','ct_mail_send_connection_report', 86400, time() + 3500); // Send connection report to welcome@cleantalk.org
		$ct_cron->addTask('antiflood__clear_table','apbct_antiflood__clear_table',   86400, time() + 300);  // Clear Anti-Flood table
	}
}