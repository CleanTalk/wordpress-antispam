<?php

$ct_plugin_basename = 'cleantalk-spam-protect/cleantalk.php';
$ct_options=ct_get_options();
$ct_data=ct_get_data();

// How many days we use an IP to detect spam.
$ct_ip_penalty_days = 30;

// Timeout to get app server
$ct_server_timeout = 10;

//Adding widjet
function ct_dashboard_statistics_widget() {
	if(current_user_can('activate_plugins')){
		$plugin_settings_link = (is_network_admin() ? "settings.php" : "options-general.php" )."?page=cleantalk";
		wp_add_dashboard_widget(
			'ct_dashboard_statistics_widget',
			__("CleanTalk Anti-Spam Statistics", 'cleantalk')
			."<div class='ct_widget_top_links'>"
				."<img src='".plugins_url('/cleantalk-spam-protect/inc/images/preloader.gif')."' class='ct_preloader'>"
				.sprintf(__("%sRefresh%s", 'cleantalk'),    "<a href='#ct_widget' class='ct_widget_refresh_link'>", "</a>")
				.sprintf(__("%sConfigure%s", 'cleantalk'), "<a href='$plugin_settings_link' class='ct_widget_settings_link'>", "</a>")
			."</div>",
			'ct_dashboard_statistics_widget_output'
		);
	}
}

// Outputs statistics widget content
function ct_dashboard_statistics_widget_output( $post, $callback_args ) {	

	global $ct_data, $ct_options, $current_user;
	
	$ct_options = ct_get_options();
    $ct_data = ct_get_data();
	
	$brief_data = $ct_data['brief_data'];
	
	if(!empty($_POST['ct_brief_refresh']) or empty($brief_data['spam_stat'])){
		$brief_data = CleantalkHelper::api_method__get_antispam_report_breif($ct_options['apikey']);
		$ct_data['brief_data'] = $brief_data;
		update_option('cleantalk_data', $ct_data);
	}
	// Parsing brief data 'spam_stat' {"yyyy-mm-dd": spam_count, "yyyy-mm-dd": spam_count} to [["yyyy-mm-dd", "spam_count"], ["yyyy-mm-dd", "spam_count"]]
	$to_chart = array();
	foreach( $brief_data['spam_stat'] as $key => $value ){
		$to_chart[] = array( $key, $value );
	} unset( $key, $value );
	$to_chart = json_encode( $to_chart );
	
	echo "<div id='ct_widget_wrapper'>";
?>
		<form id='ct_refresh_form' method='POST' action='#ct_widget'>
			<input type='hidden' name='ct_brief_refresh' value='1'>
		</form>
		<h4 class='ct_widget_block_header' style='margin-left: 12px;'><?php _e('7 days anti-spam stats', 'cleantalk'); ?></h4>
		<div class='ct_widget_block ct_widget_chart_wrapper'>
			<script>
				var ct_chart_data = <?php echo $to_chart; ?>;
			</script>
			<div id='ct_widget_chart'></div>
		</div>
		<h4 class='ct_widget_block_header'><?php _e('Top 5 spam IPs blocked', 'cleantalk'); ?></h4>
		<hr class='ct_widget_hr'>
<?php	
	if(!ct_valid_key() || (isset($brief_data['error_no']) && $brief_data['error_no'] == 6)){
		$plugin_settings_link = (is_network_admin() ? "settings.php" : "options-general.php" )."?page=cleantalk";
?>		<div class='ct_widget_block'>
			<form action='<? echo $plugin_settings_link; ?>' method='POST'>
				<h2 class='ct_widget_activate_header'><?php _e('Get Access key to activate Anti-Spam protection!', 'cleantalk'); ?></h2>
				<input class='ct_widget_button ct_widget_activate_button' type='submit' name='get_apikey_auto' value='ACTIVATE' />
			</form>
		</div>
<?php
	}elseif(!empty($brief_data['error'])){
		echo '<div class="ct_widget_block">'
			.'<h2 class="ct_widget_activate_header">'
				.sprintf(__('Something went wrong! Error: "%s".', 'cleantalk'), "<u>{$brief_data['error_string']}</u>")
			.'</h2>';
			if(!empty($ct_data['user_token'])){
				echo '<h2 class="ct_widget_activate_header">'
					.__('Please, visit your dashboard.', 'cleantalk')
				.'</h2>'
				.'<a target="_blank" href="https://cleantalk.org/my?user_token='.$ct_data['user_token'].'&cp_mode=antispam">'
					.'<input class="ct_widget_button ct_widget_activate_button ct_widget_resolve_button" type="button" value="VISIT CONTROL PANEL">'
				.'</a>';
			}
		echo '</div>';
	}
	
	if(ct_valid_key() && empty($brief_data['error'])){
?>
		<div class='ct_widget_block'>
			<table cellspacing="0">
				<tr>
					<th><?php _e('IP', 'cleantalk'); ?></th>
					<th><?php _e('Country', 'cleantalk'); ?></th>
					<th><?php _e('Block Count', 'cleantalk'); ?></th>
				</tr>
<?php			foreach($brief_data['top5_spam_ip'] as $val){ ?>				
					<tr>
						<td><?php echo $val[0]; ?></td>
						<td><?php echo $val[1] ? "<img src='https://cleantalk.org/images/flags/".strtolower($val[1]).".png'>" : ''; ?>&nbsp;<?php 
							echo $val[1]
								? locale_get_display_region('sl-Latn-'.$val[1].'-nedis', substr(get_locale(), 0, 2))
								: 'Unknown'; ?></td>
						<td style='text-align: center;'><?php echo $val[2]; ?></td>
					</tr>
<?php			} ?>
			</table>
<?php		if(!empty($ct_data['user_token'])){ ?>
				<a target='_blank' href='https://cleantalk.org/my?user_token=<?php echo $ct_data['user_token']; ?>&cp_mode=antispam'>
					<input class='ct_widget_button' id='ct_widget_button_view_all' type='button' value='View all'>
				</a>
<?php		} ?>
		</div>

<?php
	}
	// Notice at the bottom
	if(isset($current_user) && in_array('administrator', $current_user->roles)){

		$blocked = isset($ct_data['admin_blocked']) ? $ct_data['admin_blocked'] : 0;

		if($blocked > 0){
			echo '<div class="ct_widget_wprapper_total_blocked">'
				.'<img src="'.plugins_url('/cleantalk-spam-protect/inc/images/logo_color.png').'" class="ct_widget_small_logo"/>'
				.'<span title="'.__('This is the count from the CleanTalk\'s cloud and could be different to admin bar counters', 'cleantalk').'">'
					.sprintf(
						/* translators: %s: Number of spam messages */
						__( '%sCleanTalk%s has blocked %s spam for all time. The statistics are automatically updated every 24 hours.', 'cleantalk'), 
						'<a href="https://cleantalk.org/my/?user_token='.@$ct_data['user_token'].'&utm_source=wp-backend&utm_medium=dashboard_widget&cp_mode=antispam" target="_blank">',
						'</a>',
						number_format($blocked)
					)
				.'</span><br><br>'
				.'<b style="font-size: 16px;">'
					.sprintf(
						__('Do you like CleanTalk?%s Post your feedback here%s.', 'cleantalk'),
						'<u><a href="https://wordpress.org/support/plugin/cleantalk-spam-protect/reviews/#new-post" target="_blank">',
						'</a></u>'
					)
				.'</b>'
			.'</div>';
		}
	}
	echo '</div>';
}

/**
 * Admin action 'wp_ajax_ajax_get_timezone' - Ajax method for getting timezone offset
 */ 
function ct_ajax_get_timezone()
{
	global $ct_data;
	check_ajax_referer( 'ct_secret_nonce', 'security' );
	$ct_data = ct_get_data();
	if(isset($_POST['offset']))
	{
		$ct_data['timezone'] = intval($_POST['offset']);
		update_option('cleantalk_data', $ct_data);
	}
}
 
add_action( 'wp_ajax_ajax_get_timezone', 'ct_ajax_get_timezone' );


/**
 * Admin action 'admin_enqueue_scripts' - Enqueue admin script of reloading admin page after needed AJAX events
 * @param 	string $hook URL of hooked page
 */
function apbct_enqueue_scripts($hook) {
	
	global $ct_data, $ct_options;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();
	
	// Scripts to all admin pages
	wp_enqueue_script('ct_admin_js_notices',                  plugins_url('/cleantalk-spam-protect/js/cleantalk-admin.js'),              array(),         APBCT_VERSION);
	wp_enqueue_style ('ct_admin_css',                         plugins_url('/cleantalk-spam-protect/css/cleantalk-admin.css'),            array(),         APBCT_VERSION, 'all');
	
	wp_localize_script( 'jquery', 'ctAdminCommon', array(
			'logo_small_colored' => '<img src="' . plugin_dir_url(__FILE__) . 'images/logo_color.png" alt=""  height="" style="width: 17px; vertical-align: text-bottom;" />'
		));
		
	// Scripts & Styles to main dashboard page
	if($hook == 'index.php' && current_user_can('activate_plugins')){
		wp_enqueue_script('ct_gstatic_charts_loader',         'https://www.gstatic.com/charts/loader.js',                                array(),         APBCT_VERSION);
		wp_enqueue_script('ct_admin_js_widget_dashboard', 	  plugins_url('/cleantalk-spam-protect/js/cleantalk-dashboard-widget.js'),   array('ct_gstatic_charts_loader'), APBCT_VERSION);
		wp_enqueue_style('ct_admin_css_widget_dashboard',     plugins_url('/cleantalk-spam-protect/css/cleantalk-dashboard-widget.css'), array(),         APBCT_VERSION, 'all');
	}
	
	// Scripts & Styles for CleanTalk's settings page
	if( $hook == 'settings_page_cleantalk' ){
		wp_enqueue_script('cleantalk_admin_js_settings_page', plugins_url('/cleantalk-spam-protect/js/cleantalk-admin-settings-page.js'),   array(),     APBCT_VERSION);
		wp_enqueue_style('cleantalk_admin_css_settings_page', plugins_url('/cleantalk-spam-protect/css/cleantalk-admin-settings-page.css'), array(),     APBCT_VERSION, 'all');
		
		$ajax_nonce = wp_create_nonce( "ct_secret_nonce" );
		wp_localize_script( 'jquery', 'ctSettingsPage', array(
			'ct_ajax_nonce' => $ajax_nonce,
			'ct_subtitle'   => $ct_data['ip_license'] != 0 ? __('Hosting AntiSpam', 'cleantalk') : '',
			'ip_license'    => $ct_data['ip_license'] != 0 ? true : false,
		));
	}
	
	// Scripts for comments check
	if( $hook == 'comments_page_ct_check_spam' || $hook == 'edit-comments.php' || $hook == 'settings_page_cleantalk'){
		
		wp_enqueue_style('cleantalk_admin_css_settings_page', plugins_url('/cleantalk-spam-protect/css/cleantalk-spam-check.css'),       array(),         APBCT_VERSION, 'all');
		wp_enqueue_style('jqueryui_css',                      plugins_url('/cleantalk-spam-protect/css/jquery-ui.min.css'),               array(),        '1.21.1',      'all');
		
		$ajax_nonce = wp_create_nonce( "ct_secret_nonce" );
		$user_token = !empty($ct_data['user_token']) ? $ct_data['user_token'] : '';
		$show_check_links = !empty($ct_options['show_check_links']) ? $ct_options['show_check_links'] : 0;
		if(!empty($_COOKIE['ct_paused_comments_check']))
			$prev_check = json_decode(stripslashes($_COOKIE['ct_paused_comments_check']), true);
		
		wp_enqueue_script('ct_comments_checkspam',  plugins_url('/cleantalk-spam-protect/js/cleantalk-comments-checkspam.js'),           array(),         APBCT_VERSION);
		wp_enqueue_script('ct_comments_editscreen', plugins_url('/cleantalk-spam-protect/js/cleantalk-comments-editscreen.js'),          array(),         APBCT_VERSION);
		wp_enqueue_script('jqueryui',               plugins_url('/cleantalk-spam-protect/js/jquery-ui.min.js'),                          array('jquery'), '1.12.1');
		
		wp_localize_script( 'jquery', 'ctCommentsCheck', array(
			'ct_ajax_nonce'               => $ajax_nonce,
			'ct_prev_accurate'            => !empty($prev_check['accurate']) ? true                : false,
			'ct_prev_from'                => !empty($prev_check['from'])     ? $prev_check['from'] : false,
			'ct_prev_till'                => !empty($prev_check['till'])     ? $prev_check['till'] : false,
			'ct_timeout_confirm'          => __('Failed from timeout. Going to check comments again.', 'cleantalk'),
			'ct_comments_added'           => __('Added', 'cleantalk'),
			'ct_comments_deleted'         => __('Deleted', 'cleantalk'),
			'ct_comments_added_after'     => __('comments', 'cleantalk'),
			'ct_confirm_deletion_all'     => __('Delete all spam comments?', 'cleantalk'),
			'ct_confirm_deletion_checked' => __('Delete checked comments?', 'cleantalk'),
			'ct_status_string'            => __('Total comments %s. Checked %s. Found %s spam comments. %s bad comments (without IP or email).', 'cleantalk'),
			'ct_status_string_warning'    => '<p>'.__('Please do backup of WordPress database before delete any accounts!', 'cleantalk').'</p>',
			'start'                       => !empty($_COOKIE['ct_comments_start_check']) ? true : false,
		));
		wp_localize_script( 'jquery', 'ctCommentsScreen', array(
			'ct_ajax_nonce'               => $ajax_nonce,
			'spambutton_text'             => __("Find spam-comments", 'cleantalk'),
			'ct_feedback_msg_whitelisted' => __("The sender has been whitelisted.", 'cleantalk'),
			'ct_feedback_msg_blacklisted' => __("The sender has been blacklisted.", 'cleantalk'),
			'ct_feedback_msg'             => sprintf(__("Feedback has been sent to %sCleanTalk Dashboard%s.", 'cleantalk'), $user_token ? "<a target='_blank' href=https://cleantalk.org/my?user_token={$user_token}&cp_mode=antispam>" : '', $user_token ? "</a>" : ''),
			'ct_show_check_links'		  => $show_check_links,
			'ct_img_src_new_tab'          => plugin_dir_url(__FILE__)."images/new_window.gif",
			
		));
	}
	
	// Scripts for users check
	if( $hook == 'users_page_ct_check_users' || $hook == 'users.php'){
		
		wp_enqueue_style('cleantalk_admin_css_settings_page', plugins_url().'/cleantalk-spam-protect/css/cleantalk-spam-check.css', array(),          APBCT_VERSION, 'all');
		wp_enqueue_style('jqueryui_css',                      plugins_url().'/cleantalk-spam-protect/css/jquery-ui.min.css',         array(),         '1.21.1',                  'all');
		
		$current_user = wp_get_current_user();
		$ajax_nonce = wp_create_nonce( "ct_secret_nonce" );
		$show_check_links = !empty($ct_options['show_check_links']) ? $ct_options['show_check_links'] : 0;
		if(!empty($_COOKIE['ct_paused_users_check']))
			$prev_check = json_decode(stripslashes($_COOKIE['ct_paused_users_check']), true);
		
		wp_enqueue_script('ct_users_checkspam',  plugins_url('/cleantalk-spam-protect/js/cleantalk-users-checkspam.js'),             array(),         APBCT_VERSION);
		wp_enqueue_script('ct_users_editscreen', plugins_url('/cleantalk-spam-protect/js/cleantalk-users-editscreen.js'),            array(),         APBCT_VERSION);
		wp_enqueue_script('jqueryui',            plugins_url('/cleantalk-spam-protect/js/jquery-ui.min.js'),                         array('jquery'), '1.12.1');
		
		wp_localize_script( 'jquery', 'ctUsersCheck', array(
			'ct_ajax_nonce'               => $ajax_nonce,
			'ct_prev_accurate'            => !empty($prev_check['accurate']) ? true                : false,
			'ct_prev_from'                => !empty($prev_check['from'])     ? $prev_check['from'] : false,
			'ct_prev_till'                => !empty($prev_check['till'])     ? $prev_check['till'] : false,
			'ct_timeout'                  => __('Failed from timeout. Going to check users again.', 'cleantalk'),
			'ct_timeout_delete'           => __('Failed from timeout. Going to run a new attempt to delete spam users.', 'cleantalk'),
			'ct_inserted'                 => __('Inserted', 'cleantalk'),
			'ct_deleted'                  => __('Deleted', 'cleantalk'),
			'ct_iusers'                   => __('users.', 'cleantalk'),
			'ct_confirm_deletion_all'     => __('Delete all spam users?', 'cleantalk'),
			'ct_confirm_deletion_checked' => __('Delete checked users?', 'cleantalk'),
			'ct_csv_filename'             => "user_check_by_".$current_user->user_login,
			'ct_bad_csv'                  => __("File doesn't exist. File will be generated while checking. Please, press \"Check for spam\"."),
			'ct_status_string'            => __("Total users %s, checked %s, found %s spam users and %s bad users (without IP or email)", 'cleantalk'),
			'ct_status_string_warning'    => "<p>".__("Please do backup of WordPress database before delete any accounts!", 'cleantalk')."</p>"
		));
		wp_localize_script( 'jquery', 'ctUsersScreen', array(
			'spambutton_text'             => __("Find spam-users", 'cleantalk'),
			'ct_show_check_links'		  => $show_check_links,
			'ct_img_src_new_tab'          => plugin_dir_url(__FILE__)."images/new_window.gif"
		));
	}	
}

/**
 * Admin action 'admin_menu' - Add the admin options page
 */
function ct_admin_add_page() {
	global $ct_plugin_name;	
	
	if(is_network_admin())
		add_submenu_page("settings.php", __('CleanTalk settings', 'cleantalk'), $ct_plugin_name, 'manage_options', 'cleantalk', 'apbct_settings_page');
	else
		add_options_page(__('CleanTalk settings', 'cleantalk'), $ct_plugin_name, 'manage_options', 'cleantalk', 'apbct_settings_page');
	
}

/*
 * Inner function - Account status check
 * Scheduled in 1800 seconds for default!
 */
function ct_account_status_check(){
	
	global $ct_options, $ct_data, $show_ct_notice_trial, $show_ct_notice_renew;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();
		
	$api_key = isset($_POST['cleantalk_settings']['apikey'])
		? $_POST['cleantalk_settings']['apikey']
		: (!empty($ct_options['apikey'])
			? $ct_options['apikey']
			: null);
	
	$result = CleantalkHelper::api_method__notice_paid_till($api_key);	
		
	if(empty($result['error'])){
		
		if(isset($result['spam_count']))
			$ct_data['admin_blocked'] = $result['spam_count'];

		if (isset($result['show_notice'])){
			
			if ($result['show_notice'] == 1 && isset($result['trial']) && $result['trial'] == 1){
				CleantalkCron::updateTask('check_account_status', 'ct_account_status_check',  3600);
				$show_ct_notice_trial = true;
				$ct_data['show_ct_notice_trial']=1;
			}
			
			if ($result['show_notice'] == 1 && isset($result['renew']) && $result['renew'] == 1){
				CleantalkCron::updateTask('check_account_status', 'ct_account_status_check',  1800);
				$show_ct_notice_renew = true;
				$ct_data['show_ct_notice_renew']=1;
			}
			
			if (isset($result['show_review']) && $result['show_review'] == 1)
				$ct_data['show_ct_notice_review'] = 1;
			
			if ($result['show_notice'] == 0)
				CleantalkCron::updateTask('check_account_status', 'ct_account_status_check',  86400);
			
			$ct_data['show_ct_notice_trial']       = (int) $show_ct_notice_trial;
			$ct_data['show_ct_notice_renew']       = (int) $show_ct_notice_renew;
			$ct_data['show_ct_notice_auto_update'] = isset($result['show_auto_update_notice']) ? $result['show_auto_update_notice'] : 0;
			$ct_data['auto_update_app']            = isset($result['auto_update_app'])        ? $result['auto_update_app']          : 0;
		}
		
		if (isset($result['service_id']))
			$ct_data['service_id'] = (int)$result['service_id'];
		
		if (isset($result['moderate']) && $result['moderate'] == 1)
			$ct_data['moderate'] = 1;
		else
			$ct_data['moderate'] = 0;
		
		if (isset($result['license_trial'])){
			$ct_data['license_trial'] = $result['license_trial'];
		}
		
		if (isset($result['moderate_ip']) && $result['moderate_ip'] == 1){
			$ct_data['moderate_ip'] = 1;
			$ct_data['ip_license'] = $result['ip_license'];
		}else{
			$ct_data['moderate_ip'] = 0;
			$ct_data['ip_license'] = 0;
		}
		
		if (isset($result['user_token']))
			$ct_data['user_token'] = $result['user_token'];
			
		update_option('cleantalk_data', $ct_data);
		
	}
}

/**
 * Admin action 'admin_init' - Add the admin settings and such
 */
function apbct_admin_init(){
	
	global $ct_server_timeout, $show_ct_notice_autokey, $ct_notice_autokey_label, $ct_notice_autokey_value, $show_ct_notice_renew, $ct_notice_renew_label, $show_ct_notice_trial, $ct_notice_trial_label, $show_ct_notice_online, $ct_notice_online_label, $renew_notice_showtime, $trial_notice_showtime, $ct_plugin_name, $ct_options, $ct_data, $ct_user_token_label, $notice_check_timeout;
	
    $ct_options = ct_get_options();
	$ct_data = ct_get_data();
	
	// Update logic
	$current_version = (!empty($ct_data['plugin_version']) ? $ct_data['plugin_version'] : '1.0.0');
	
	if($current_version != APBCT_VERSION){
		if(is_main_site()){
			require_once('cleantalk-updater.php');
			$result = apbct_run_update_actions($current_version, APBCT_VERSION);
			//If update is successfull
			if($result === true){
				ct_send_feedback(
					'0:' . APBCT_AGENT // 0 - request_id, agent version.
				);
				$ct_data['plugin_version'] = APBCT_VERSION;
				update_option( 'cleantalk_data' , $ct_data);
			}
		}
	}
	
	// Drop debug data
	if(isset($_POST['ct_debug_reset']) && $_POST['ct_debug_reset']){
		$ct_data['ct_debug_reset'] = true;
		update_option('cleantalk_data', $ct_data);
	}
	
	// Feedback when saving settings
	if(isset($_POST['option_page']) && $_POST['option_page'] == 'cleantalk_settings' && isset($_POST['cleantalk_settings']['apikey'])){
		$ct_options['apikey']=$_POST['cleantalk_settings']['apikey'];
		update_option('cleantalk_settings', $ct_options);
        ct_send_feedback(
            '0:' . APBCT_AGENT // 0 - request_id, agent version.
        );
	}
	
	$show_ct_notice_autokey = false;
	if (isset($_COOKIE[$ct_notice_autokey_label]) && !empty($_COOKIE[$ct_notice_autokey_label]))
	{
		$show_ct_notice_autokey = true;
		$ct_notice_autokey_value = base64_decode($_COOKIE[$ct_notice_autokey_label]);
		setcookie($ct_notice_autokey_label, '', 1, '/');
	}
	
	//Auto getting key
	if (isset($_POST['get_apikey_auto']))
	{
		$website = parse_url(get_option('siteurl'),PHP_URL_HOST);
		$platform = 'wordpress';
		$timezone = $_POST['ct_admin_timezone'];
		
		$result = CleantalkHelper::api_method__get_api_key(ct_get_admin_email(), $website, $platform, $timezone);

		if ($result)
		{
			ct_account_status_check();
			$result = json_decode($result, true);
			
			if (isset($result['data']) && is_array($result['data']))
				$result = $result['data'];
			
			if(isset($result['user_token'])){
				$ct_data['user_token'] = $result['user_token'];
				update_option('cleantalk_data', $ct_data);
			}
			
			if (isset($result['auth_key']) && !empty($result['auth_key'])){
				$_POST['cleantalk_settings']['apikey'] = $result['auth_key'];
				$ct_options['apikey']=$result['auth_key'];
				update_option('cleantalk_settings', $ct_options);
			}else{
				setcookie($ct_notice_autokey_label, (string) base64_encode($result['error_message']), 0, '/');
			}
		}else{
			setcookie($ct_notice_autokey_label, (string) base64_encode(sprintf(__('Unable to connect to %s.', 'cleantalk'),  'api.cleantalk.org')), 0, '/');
		}
	}
	
	//Account's status check if settings saved
	if (isset($_POST['cleantalk_settings']['apikey'])){
		ct_account_status_check();
	}
	

	$show_ct_notice_online = '';
	if (isset($_COOKIE[$ct_notice_online_label]))
	{
		if ($_COOKIE[$ct_notice_online_label] === 'BAD_KEY')
		{
			$show_ct_notice_online = 'N';
		}
		else if (time() - $_COOKIE[$ct_notice_online_label] <= 5)
		{
			$show_ct_notice_online = 'Y';
		}
	}
	
	if(stripos($_SERVER['REQUEST_URI'],'options.php')!==false || stripos($_SERVER['REQUEST_URI'],'options-general.php')!==false || stripos($_SERVER['REQUEST_URI'],'network/settings.php')!==false)
	{
	
		register_setting('cleantalk_settings', 'cleantalk_settings', 'ct_settings_validate');
		add_settings_section('cleantalk_settings_main', __($ct_plugin_name, 'cleantalk'), 'ct_section_settings_main', 'cleantalk');

		if(!empty($ct_data['debug']))
			add_settings_section('cleantalk_debug_section', '<hr>Debug', 'ct_section_debug', 'cleantalk');
		add_settings_section('cleantalk_settings_state', "<hr>".__('Protection is active', 'cleantalk'), 'ct_section_settings_state', 'cleantalk');
		add_settings_section('cleantalk_settings_banner', "<hr>", '', 'cleantalk');
		add_settings_section('cleantalk_settings_anti_spam', "<a href='#' class='ct_support_link'>".__('Advanced settings', 'cleantalk')."</a>", 'ct_section_settings_anti_spam', 'cleantalk');
		
		if(!defined('CLEANTALK_ACCESS_KEY'))
		{
			add_settings_field('cleantalk_apikey', __('Access key', 'cleantalk'), 'ct_input_apikey', 'cleantalk', 'cleantalk_settings_main');
		}
		else
		{
			add_settings_field('cleantalk_apikey', '', 'ct_input_apikey', 'cleantalk', 'cleantalk_settings_main');
		}
		
		if(ct_valid_key())
			add_settings_field('cleantalk_connection_reports', '', 'ct_report_builder', 'cleantalk', 'cleantalk_settings_main');
		
		//Forms for protection
		add_settings_field('cleantalk_title_fiels_for_protect', "", 'ct_input_what_fields_should_be_protected', 'cleantalk', 'cleantalk_settings_anti_spam');//Title settings
		add_settings_field('cleantalk_registrations_test', __('Registration forms', 'cleantalk'), 'ct_input_registrations_test', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_comments_test', __('Comments form', 'cleantalk'), 'ct_input_comments_test', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_contact_forms_test', __('Contact forms', 'cleantalk'), 'ct_input_contact_forms_test', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_general_contact_forms_test', __('Custom contact forms', 'cleantalk'), 'ct_input_general_contact_forms_test', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_wc_checkout_test', __('WooCommerce checkout form', 'cleantalk'), 'ct_input_wc_chekout_test', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_check_external', __('Protect external forms', 'cleantalk'), 'ct_input_check_external', 'cleantalk', 'cleantalk_settings_anti_spam');
        add_settings_field('cleantalk_check_internal', __('Protect internal forms', 'cleantalk'), 'ct_input_check_internal', 'cleantalk', 'cleantalk_settings_anti_spam');
		
		//Comments and messages
		add_settings_field('cleantalk_title_comments_and_messages', "", 'ct_input_comments_and_messages', 'cleantalk', 'cleantalk_settings_anti_spam');//Title settings
		add_settings_field('cleantalk_bp_private_messages', __('buddyPress private messages', 'cleantalk'), 'ct_input_bp_private_messages', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_check_comments_number', __("Don't check trusted user's comments", 'cleantalk'), 'ct_input_check_comments_number', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_remove_old_spam', __('Automatically delete spam comments', 'cleantalk'), 'ct_input_remove_old_spam', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_remove_links_from_comments', __('Remove links from approved comments', 'cleantalk'), 'ct_input_remove_links_from_approved_comments', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_show_check_links', __('Show links to check Emails, IPs for spam.', 'cleantalk'), 'ct_input_show_check_links', 'cleantalk', 'cleantalk_settings_anti_spam');
		
		//Data processing
		add_settings_field('cleantalk_title_data_processing', "", 'ct_input_data_processing', 'cleantalk', 'cleantalk_settings_anti_spam');//Title settings
		add_settings_field('cleantalk_protect_logged_in', __("Protect logged in Users", 'cleantalk'), 'ct_input_protect_logged_in', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_use_ajax', __('Use AJAX for JavaScript check', 'cleantalk'), 'ct_input_use_ajax', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_general_postdata_test', __('Check all post data', 'cleantalk'), 'ct_input_general_postdata_test', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_set_cookies', __("Set cookies", 'cleantalk'), 'ct_input_set_cookies', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_ssl_on', __("Use SSL", 'cleantalk'), 'ct_input_ssl_on', 'cleantalk', 'cleantalk_settings_anti_spam');
		
		//Administrator Panel
		add_settings_field('cleantalk_title_administrator_panel', "", 'ct_input_administrator_panel', 'cleantalk', 'cleantalk_settings_anti_spam');//Title settings
		add_settings_field('cleantalk_show_adminbar', __('Show statistics in admin bar', 'cleantalk'), 'ct_input_show_adminbar', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_all_time_counter', "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".__('Show All-time counter', 'cleantalk'), 'ct_input_all_time_counter', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_daily_conter', "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".__('Show 24 hours counter', 'cleantalk'), 'ct_input_daily_counter', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_sfw_counter', "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".__('SpamFireWall counter', 'cleantalk'), 'ct_input_sfw_counter', 'cleantalk', 'cleantalk_settings_anti_spam');
		
		// GDPR
		// add_settings_field('cleantalk_collect_details', __('Collect details about browsers', 'cleantalk'), 'ct_input_collect_details', 'cleantalk', 'cleantalk_settings_anti_spam');
		// add_settings_field('cleantalk_connection_reports', __('Send connection reports', 'cleantalk'), 'ct_send_connection_reports', 'cleantalk', 'cleantalk_settings_anti_spam');
		
		// Misc
		add_settings_field('cleantalk_collect_details', __('Collect details about browsers', 'cleantalk'), 'ct_input_collect_details', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_connection_reports', __('Send connection reports', 'cleantalk'), 'ct_send_connection_reports', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_async_js', __('Async JavaScript loading', 'cleantalk'), 'ct_async_js', 'cleantalk', 'cleantalk_settings_anti_spam');
		add_settings_field('cleantalk_show_link', __('Tell others about CleanTalk', 'cleantalk'), 'ct_input_show_link', 'cleantalk', 'cleantalk_settings_banner');
		add_settings_field('cleantalk_spam_firewall', __('SpamFireWall', 'cleantalk'), 'ct_input_spam_firewall', 'cleantalk', 'cleantalk_settings_banner');
	}
}

/**
 * Admin callback function - Displays description of 'main' plugin parameters section
 */
function ct_section_settings_main() {
/*
	$ct_options=ct_get_options();

	$is_wpmu = false;
	if(defined('CLEANTALK_ACCESS_KEY')) {
	    $is_wpmu = true;
	}

	if (ct_valid_key($ct_options['apikey']) !== false || $is_wpmu) {
        return true;
    }
    $message = "<p>Please wait we are registering account welcome@cleantalk.org to finish plugin setup...</p>";
    echo $message;

	?>
	<script type="text/javascript">
        var api_url = 'https://localhost/test.php';

        var req ;

        // Browser compatibility check  		
        if (window.XMLHttpRequest) {
           req = new XMLHttpRequest();
            } else if (window.ActiveXObject) {

         try {
           req = new ActiveXObject("Msxml2.XMLHTTP");
         } catch (e) {

           try {
             req = new ActiveXObject("Microsoft.XMLHTTP");
           } catch (e) {}
         }

        }


        var req = new XMLHttpRequest();
        req.open("GET", api_url, true);
        req.onreadystatechange = function () {
            console.log(req.getResponseHeader('HTTP_COOKIE'));
        }

        req.send(null);
    </script>
	
	<?php
*/
    return true;
}

/**
 * Admin callback function - Displays description of 'anti-spam' plugin parameters section
 */
function ct_section_settings_anti_spam() {
	return true;
}

add_action( 'admin_bar_menu', 'ct_add_admin_menu', 999 );

function ct_input_all_time_counter() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();

	$value=(isset($ct_options['all_time_counter']) ? @intval($ct_options['all_time_counter']) : 0);
	$value2=(isset($ct_options['show_adminbar']) ? @intval($ct_options['show_adminbar']) : 0);

	echo "<input type='radio' class='ct-depends-of-show-adminbar' id='cleantalk_all_time_counter1' name='cleantalk_settings[all_time_counter]' value='1' ".($value=='1'?'checked':'').($value2=='0'?' disabled':'')." /><label for='cleantalk_all_time_counter1'> ".__('Yes')."</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' class='ct-depends-of-show-adminbar' id='cleantalk_all_time_counter0' name='cleantalk_settings[all_time_counter]' value='0' ".($value=='0'?'checked':'').($value2=='0'?' disabled':'')." /><label for='cleantalk_all_time_counter0'> ".__('No')."</label>";
	ct_add_descriptions_to_fields(__('Display all-time requests counter in the admin bar. Counter displays number of requests since plugin installation.', 'cleantalk'));
}

function ct_input_daily_counter() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();

	$value=(isset($ct_options['daily_counter']) ? @intval($ct_options['daily_counter']) : 0);
	$value2=(isset($ct_options['show_adminbar']) ? @intval($ct_options['show_adminbar']) : 0);
	
	echo "<input type='radio' class='ct-depends-of-show-adminbar' id='cleantalk_daily_counter1' name='cleantalk_settings[daily_counter]' value='1' ".($value=='1'?'checked':'').($value2=='0'?' disabled':'')." /><label for='cleantalk_daily_counter1'> ".__('Yes')."</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' class='ct-depends-of-show-adminbar' id='cleantalk_daily_counter0' name='cleantalk_settings[daily_counter]' value='0' ".($value=='0'?'checked':'').($value2=='0'?' disabled':'')." /><label for='cleantalk_daily_counter0'> ".__('No')."</label>";
	ct_add_descriptions_to_fields(__('Display daily requests counter in the admin bar. Counter displays number of requests of the past 24 hours.', 'cleantalk'));
}

function ct_input_sfw_counter() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();

	$value=(isset($ct_options['sfw_counter']) ? @intval($ct_options['sfw_counter']) : 0);
	$value2=(isset($ct_options['show_adminbar']) ? @intval($ct_options['show_adminbar']) : 0);

	echo "<input type='radio' class='ct-depends-of-show-adminbar' id='cleantalk_sfw_counter1' name='cleantalk_settings[sfw_counter]' value='1' ".($value=='1'?'checked':'').($value2=='0'?' disabled':'')." /><label for='cleantalk_sfw_counter1'> ".__('Yes')."</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' class='ct-depends-of-show-adminbar' id='cleantalk_sfw_counter0' name='cleantalk_settings[sfw_counter]' value='0' ".($value=='0'?'checked':'').($value2=='0'?' disabled':'')." /><label for='cleantalk_sfw_counter0'> ".__('No')."</label>";
	ct_add_descriptions_to_fields(__('Display all-time requests counter in the admin bar. Counter displays number of requests since plugin installation.', 'cleantalk'));
}

function ct_send_connection_reports() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();

	$value = $value=(isset($ct_options['send_connection_reports']) ? @intval($ct_options['send_connection_reports']) : 0);
	echo "<div id='cleantalk_anchor3' style='display:none'></div>";
	echo "<input type='checkbox' id='connection_reports1' name='cleantalk_settings[send_connection_reports]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='connection_reports1'> " . __('Send connection reports', 'cleantalk') . "</label>";
	ct_add_descriptions_to_fields(__("Checking this box you allow plugin to send the information about your connection. The option in a beta state.", 'cleantalk'));
	echo "<script>
		jQuery(document).ready(function(){
			jQuery('#cleantalk_anchor3').parent().parent().children().first().hide();
			jQuery('#cleantalk_anchor3').parent().css('padding-left','0px');
			jQuery('#cleantalk_anchor3').parent().attr('colspan', '2');
		});
	</script>";
}

function ct_async_js() {
	
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();

	$value = $value=(isset($ct_options['async_js']) ? @intval($ct_options['async_js']) : 0);
	echo "<div id='cleantalk_anchor4' style='display:none'></div>";
	echo "<input type='checkbox' id='async_js' name='cleantalk_settings[async_js]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='async_js'> " . __('Async script loading', 'cleantalk') . "</label>";
	ct_add_descriptions_to_fields(__('Use async loading for CleanTalk\'s scripts. Warning: This could reduce filtration quality.', 'cleantalk'));
	echo "<script>
		jQuery(document).ready(function(){
			jQuery('#cleantalk_anchor4').parent().parent().children().first().hide();
			jQuery('#cleantalk_anchor4').parent().css('padding-left','0px');
			jQuery('#cleantalk_anchor4').parent().attr('colspan', '2');
		});
	</script>";
}

function ct_input_get_premium($print = true){
	
	global $ct_data;
	
	$ct_data = ct_get_data();
	
	$out = '';
	
	if(!empty($ct_data['license_trial']) && !empty($ct_data['user_token'])){
		$out = '<b style="display: inline-block; margin-top: 10px;">'
			.($print ? __('Make it right!', 'cleantalk').' ' : '')
			.sprintf(
				__('%sGet premium%s', 'cleantalk'),
				'<a href="https://cleantalk.org/my/bill/recharge?user_token='.$ct_data['user_token'].'" target="_blank">',
				'</a>'
			)
		.'</b>';
	}
	
	if($print)
		echo $out;
	else
		return $out;
}

function ct_add_admin_menu( $wp_admin_bar ) {
// add a parent item
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	
	if (current_user_can('activate_plugins') && $ct_options['show_adminbar'] == 1 && (ct_valid_key($ct_options['apikey']) !== false || (defined('CLEANTALK_SHOW_ADMIN_BAR_FORCE') && CLEANTALK_SHOW_ADMIN_BAR_FORCE))) {
        $ct_data=ct_get_data();
        		
		//Reset or create user counter
		if(!empty($_GET['ct_reset_user_counter'])){
			$ct_data['user_counter']['accepted'] = 0;
			$ct_data['user_counter']['blocked'] = 0;
			$ct_data['user_counter']['since'] = date('d M');
            update_option('cleantalk_data', $ct_data);
        }
		//Reset or create all counters
		if(!empty($_GET['ct_reset_all_counters'])){
			$ct_data['sfw_counter']      = array('all' => 0, 'blocked' => 0);
			$ct_data['all_time_counter'] = array('accepted' => 0, 'blocked' => 0);
			$ct_data['user_counter']     = array('all' => 0, 'accepted' => 0, 'blocked' => 0, 'since' => date('d M'));
			$ct_data['array_accepted']   = array();
			$ct_data['array_blocked']    = array();
			$ct_data['current_hour']     = '';
            update_option('cleantalk_data', $ct_data);
        }
		if (!empty($_GET['ct_send_connection_report'])){
			ct_mail_send_connection_report();
		}		
		//Compile user's counter string
		$user_counter=Array('accepted'=>$ct_data['user_counter']['accepted'], 'blocked'=>$ct_data['user_counter']['blocked'], 'all'=>$ct_data['user_counter']['accepted'] + $ct_data['user_counter']['blocked'], 'since'=>$ct_data['user_counter']['since']);
		//Previous version $user_counter_str='<span style="color: white;">Since '.$user_counter['since'].': ' .$user_counter['all']*/. '</span> / <span style="color: green;">' .$user_counter['accepted']. '</span> / <span style="color: red;">' .$user_counter['blocked']. '</span>';
		$user_counter_str='<span style="color: white;">' . __('Since', 'cleantalk') . '&nbsp;' . $user_counter['since'].':  </span><span style="color: green;">' .$user_counter['accepted']. '</span> / <span style="color: red;">' .$user_counter['blocked']. '</span>';
		
		$all_time_counter_str='';
		//Don't compile if all time counter disabled
		if(isset($ct_options['all_time_counter']) && $ct_options['all_time_counter']=='1'){
			$all_time_counter=Array('accepted'=>$ct_data['all_time_counter']['accepted'], 'blocked'=>$ct_data['all_time_counter']['blocked'], 'all'=>$ct_data['all_time_counter']['accepted'] + $ct_data['all_time_counter']['blocked']);
			$all_time_counter_str='<span style="color: white;" title="'.__('All / Allowed / Blocked submissions. The number of submissions is being counted since CleanTalk plugin installation.', 'cleantalk').'"><span style="color: white;"> | ' . __('All', 'cleantalk') . ': ' .$all_time_counter['all']. '</span> / <span style="color: green;">' .$all_time_counter['accepted']. '</span> / <span style="color: red;">' .$all_time_counter['blocked']. '</span></span>';
		}
		
		$daily_counter_str='';
		//Don't compile if daily counter disabled
		if(isset($ct_options['daily_counter']) && $ct_options['daily_counter']=='1'){
			$daily_counter=Array('accepted'=>array_sum($ct_data['array_accepted']), 'blocked'=>array_sum($ct_data['array_blocked']), 'all'=>array_sum($ct_data['array_accepted']) + array_sum($ct_data['array_blocked']));
			//Previous version $daily_counter_str='<span style="color: white;" title="'.__('All / Allowed / Blocked submissions. The number of submissions for past 24 hours. ', 'cleantalk').'"><span style="color: white;"> | Day: ' .$daily_counter['all']. '</span> / <span style="color: green;">' .$daily_counter['accepted']. '</span> / <span style="color: red;">' .$daily_counter['blocked']. '</span></span>';
			$daily_counter_str='<span style="color: white;" title="'.__('Allowed / Blocked submissions. The number of submissions for past 24 hours. ', 'cleantalk').'"><span style="color: white;"> | ' . __('Day', 'cleantalk') . ': </span><span style="color: green;">' .$daily_counter['accepted']. '</span> / <span style="color: red;">' .$daily_counter['blocked']. '</span></span>';
		}
		$sfw_counter_str='';
		//Don't compile if SFW counter disabled
		if(isset($ct_options['sfw_counter']) && intval($ct_options['sfw_counter']) == 1 && isset($ct_options['spam_firewall']) && intval($ct_options['spam_firewall']) == 1){
			$sfw_counter=Array('all'=>$ct_data['sfw_counter']['all'], 'blocked'=>$ct_data['sfw_counter']['blocked']);
			$sfw_counter_str='<span style="color: white;" title="'.__('All / Blocked events. Access attempts regitred by SpamFireWall counted since the last plugin activation.', 'cleantalk').'"><span style="color: white;"> | SpamFireWall: ' .$sfw_counter['all']. '</span> / <span style="color: red;">' .$sfw_counter['blocked']. '</span></span>';
		}
		
		$show_some = $ct_data['show_ct_notice_trial'] == 1 && isset($ct_data['moderate'],$ct_data['service_id']) && $ct_data['moderate']== 0 && $ct_data['service_id']%2 == 0
			? true
			: false;
		$user_token = (isset($ct_data['user_token']) && $ct_data['user_token'] != '' ? "&user_token={$ct_data['user_token']}" : "");
		
		$args = array(
			'id'	=> 'ct_parent_node',
			'title' => '<img src="' . plugin_dir_url(__FILE__) . 'images/logo_small1.png" alt=""  height="" style="margin-top:9px; float: left;" />'
				.'<div style="margin: auto 7px;" class="ab-item alignright">'
					.'<div class="ab-label" id="ct_stats">'
						.($show_some
							? "<span><a style='color: red;' href=\"http://cleantalk.org/my/bill/recharge?utm_source=wp-backend&utm_medium=cpc&utm_campaign=WP%20backend%20trial$user_token&cp_mode=antispam\" target=\"_blank\">Renew Anti-Spam</a></span>"
							: '<span style="color: white;" title="'.__('Allowed / Blocked submissions. The number of submissions is being counted since ', 'cleantalk').' '.$user_counter['since'].'">'.$user_counter_str.'</span>	'.$daily_counter_str.$all_time_counter_str.$sfw_counter_str	
						)
					.'</div>'
				.'</div>' //You could change widget string here by simply deleting variables
		);
		$wp_admin_bar->add_node( $args );
	
		// add a child item to our parent item
		$args = array(
			'id'	 => 'ct_dashboard_link',
			'title'  => '<a href="https://cleantalk.org/my/?user_token='.@$ct_data['user_token'].'&utm_source=wp-backend&utm_medium=admin-bar&cp_mode=antispam " target="_blank">CleanTalk '.__('dashboard', 'cleantalk').'</a>',
			'parent' => 'ct_parent_node'
		);
		$wp_admin_bar->add_node( $args );
	
		// add another child item to our parent item (not to our first group)
		if(!is_network_admin()){
			$args = array(
				'id'	 => 'ct_settings_link',
				'title'  => '<a href="options-general.php?page=cleantalk">'.__('Settings', 'cleantalk').'</a>',
				'parent' => 'ct_parent_node'
			);
		}else{
			$args = array(
				'id'	 => 'ct_settings_link',
				'title'  => '<a href="settings.php?page=cleantalk">'.__('Settings', 'cleantalk').'</a>',
				'parent' => 'ct_parent_node'
			);
		}
		$wp_admin_bar->add_node( $args );
		
		// add a child item to our parent item. Bulk checks.
		if(!is_network_admin()){
			$args = array(
				'id'	 => 'ct_settings_bulk_comments',
				'title'  => '<hr style="margin-top: 7px;" /><a href="edit-comments.php?page=ct_check_spam" title="'.__('Bulk spam comments removal tool.', 'cleantalk').'">'.__('Check comments for spam', 'cleantalk').'</a>',
				'parent' => 'ct_parent_node'
			);
		}
		$wp_admin_bar->add_node( $args );
		
		// add a child item to our parent item. Bulk checks.
		if(!is_network_admin()){
			$args = array(
				'id'	 => 'ct_settings_bulk_users',
				'title'  => '<a href="users.php?page=ct_check_users" title="Bulk spam users removal tool.">'.__('Check users for spam', 'cleantalk').'</a>',
				'parent' => 'ct_parent_node'
			);
		}
		$wp_admin_bar->add_node( $args );
		
        // User counter reset.
		$args = array(
			'id'	 => 'ct_reset_counter',
			'title'  => '<hr style="margin-top: 7px;"><a href="?ct_reset_user_counter=1" title="Reset your personal counter of submissions.">'.__('Reset first counter', 'cleantalk').'</a>',
			'parent' => 'ct_parent_node'
		);
		$wp_admin_bar->add_node( $args );// add a child item to our parent item. Counter reset.
		
		// Reset ALL counter
		$args = array(
			'id'	 => 'ct_reset_counters_all',
			'title'  => '<a href="?ct_reset_all_counters=1" title="Reset all counters.">'.__('Reset all counters', 'cleantalk').'</a>',
			'parent' => 'ct_parent_node'
		);
		$wp_admin_bar->add_node( $args );
		
		// Support link
		$args = array(
			'id'	 => 'ct_admin_bar_support_link',
			'title'  => '<hr style="margin-top: 7px;" /><a target="_blank" href="https://wordpress.org/support/plugin/cleantalk-spam-protect">'.__('Support', 'cleantalk').'</a>',
			'parent' => 'ct_parent_node'
		);
		$wp_admin_bar->add_node( $args );
	}
}


// Prints debug information. Support function.
function ct_section_debug(){
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();
		
	if(isset($ct_data['ct_debug_reset']) && $ct_data['ct_debug_reset']){
		unset($ct_data['debug'], $ct_data['ct_debug_reset']);
		update_option('cleantalk_data', $ct_data);
		return;
	}
	

	echo "<h4>Constants:</h4>";
	echo 'CLEANTALK_AJAX_USE_BUFFER '.		 	(defined('CLEANTALK_AJAX_USE_BUFFER') ? 		(CLEANTALK_AJAX_USE_BUFFER ? 		'true' : 'flase') : 'NOT_DEFINED')."<br>";
	echo 'CLEANTALK_AJAX_USE_FOOTER_HEADER '.	(defined('CLEANTALK_AJAX_USE_FOOTER_HEADER') ? 	(CLEANTALK_AJAX_USE_FOOTER_HEADER ? 'true' : 'flase') : 'NOT_DEFINED')."<br>";
	echo 'CLEANTALK_ACCESS_KEY '.				(defined('CLEANTALK_ACCESS_KEY') ? 				(CLEANTALK_ACCESS_KEY ? 			CLEANTALK_ACCESS_KEY : 'flase') : 'NOT_DEFINED')."<br>";
	echo 'CLEANTALK_CHECK_COMMENTS_NUMBER '.	(defined('CLEANTALK_CHECK_COMMENTS_NUMBER') ? 	(CLEANTALK_CHECK_COMMENTS_NUMBER ? 	CLEANTALK_CHECK_COMMENTS_NUMBER : 0) : 'NOT_DEFINED')."<br>";
	echo 'CLEANTALK_CHECK_MESSAGES_NUMBER '.	(defined('CLEANTALK_CHECK_MESSAGES_NUMBER') ? 	(CLEANTALK_CHECK_MESSAGES_NUMBER ? 	CLEANTALK_CHECK_MESSAGES_NUMBER : 0) : 'NOT_DEFINED')."<br>";
	echo 'CLEANTALK_PLUGIN_DIR '.				(defined('CLEANTALK_PLUGIN_DIR') ? 				(CLEANTALK_PLUGIN_DIR ? 			CLEANTALK_PLUGIN_DIR : 'flase') : 'NOT_DEFINED')."<br>";
	echo 'WP_ALLOW_MULTISITE '.					(defined('WP_ALLOW_MULTISITE') ? 				(WP_ALLOW_MULTISITE ?				'true' : 'flase') : 'NOT_DEFINED');
	
	echo "<h4>Debug log: <input type='submit' value='Drop debug data' name='ct_debug_reset' style='font-size: 11px; padding: 1px;'></h4>";
	echo "<div style='height: 500px; width: 80%; overflow: auto;'>";
		
		$output = print_r($ct_data['debug'], true);
		$output = str_replace("\n", "<br>", $output);
		$output = preg_replace("/[^\S]{4}/", "&nbsp;&nbsp;&nbsp;&nbsp;", $output);
		echo "$output";
		
	echo "</div>";
}

/**
 * Admin callback function - Displays description of 'state' plugin parameters section
 */
function ct_section_settings_state() {
	global $ct_options, $ct_data, $wpdb;
		
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();
		
	$ct_data['moderate_ip'] = isset($ct_data['moderate_ip']) ? $ct_data['moderate_ip'] : 0;

	$path_to_img = plugin_dir_url(__FILE__) . "images/";
	
	$img = $path_to_img."yes.png";
	$img_no = $path_to_img."no.png";
	$img_no_gray = $path_to_img."no_gray.png";
	$color="black";
	$test_failed=false;

	if(trim($ct_options['apikey'])=='')
	{
		$img = $path_to_img."yes_gray.png";
		$img_no = $path_to_img."no_gray.png";
		$color="gray";
	}
	if(isset($ct_data['testing_failed'])&&$ct_data['testing_failed']==1)
	{
		$img=$path_to_img."no.png";
		$img_no=$path_to_img."no.png";
		$color="black";
		$test_failed=true;
	}
	if($ct_data['moderate_ip'] == 1)
	{
		$img = $path_to_img."yes.png";
		$img_no = $path_to_img."no.png";
		$color="black";
		$test_failed=false;
	}
	print "<div style='color:$color'>";
		
		$ct_moderate = 					isset($ct_data['moderate_ip']) && $ct_data['moderate_ip'] == 1 	? true 										: false;
		$show_ct_notice_auto_update = 	isset($ct_data['show_ct_notice_auto_update']) 					? $ct_data['show_ct_notice_auto_update'] 	: 0;
		$auto_update_app  = 			isset($ct_data['auto_update_app']) 							    ? $ct_data['auto_update_app'] 	        	: 0;
		
		echo '<img class="apbct_status_icon" src="'.($ct_options['registrations_test']==1         || $ct_moderate ? $img : $img_no).'" />'.__('Registration forms', 'cleantalk');
		echo '<img class="apbct_status_icon" src="'.($ct_options['comments_test']==1              || $ct_moderate ? $img : $img_no).'"/>'.__('Comments forms', 'cleantalk');
		echo '<img class="apbct_status_icon" src="'.($ct_options['contact_forms_test']==1         || $ct_moderate ? $img : $img_no).'"/>'.__('Contact forms', 'cleantalk');
		echo '<img class="apbct_status_icon" src="'.($ct_options['general_contact_forms_test']==1 || $ct_moderate ? $img : $img_no).'"/>'.__('Custom contact forms', 'cleantalk');
		
		// SFW + current network count
		$sfw_netwoks_amount = $wpdb->get_results("SELECT count(*) AS cnt FROM `".$wpdb->base_prefix."cleantalk_sfw`", ARRAY_A);
		$alt_for_sfw = sprintf(__('Networks in database: %d.', 'cleantalk'), $sfw_netwoks_amount[0]['cnt']); 
		echo '<img class="apbct_status_icon" src="'.($ct_options['spam_firewall']==1              || $ct_moderate ? $img : $img_no).'"  title="'.($ct_options['spam_firewall']==1 || $ct_moderate ? $alt_for_sfw : '').'"/>'.__('SpamFireWall', 'cleantalk');
		
		// Autoupdate status
		if($show_ct_notice_auto_update == 1){
			echo '<img class="apbct_status_icon" src="'.($auto_update_app == 1 ? $img : ($auto_update_app == -1 ? $img_no : $img_no_gray)).'"/>'.__('Auto update', 'cleantalk')
				.' <sup><a href="http://cleantalk.org/help/auto-update" target="_blank">?</a></sup>';
		}
		
		// WooCommerce
		if(class_exists('WooCommerce'))
			echo '<img class="apbct_status_icon" src="'.($ct_options['wc_checkout_test']==1     || $ct_moderate ? $img : $img_no).'"/>'.__('WooCommerce checkout form', 'cleantalk');
		
		if($ct_data['moderate_ip'] == 1)
			print "<br /><br />The anti-spam service is paid by your hosting provider. License #".$ct_data['ip_license'].".<br />";
	
	print "</div>";
	
	return true;
}

/**
 * Admin callback function - Displays description of 'autodel' plugin parameters section
 */
function ct_section_settings_autodel() {
	return true;
}

function ct_report_builder() {

	global $ct_options, $ct_data;
	
	$ct_options=ct_get_options();
	$ct_data=ct_get_data();
		
	if (isset($ct_data['connection_reports'])){
		
		if ($ct_data['connection_reports']['negative'] == 0){
			_e('There are no failed connections to CleanTalk servers.', 'cleantalk');
		}else{
			
			echo "<table id='negative_reports_table' style='display: none;'>
				<tr>
					<td>#</td>
					<td><b>Date</b></td>
					<td><b>Page URL</b></td>
					<td><b>Report</b></td>
				</tr>";
			foreach($ct_data['connection_reports']['negative_report'] as $key => $report){
				echo "<tr><td>".($key+1).".</td><td>".$report['date']."</td><td>".$report['page_url']."</td><td>".$report['lib_report']."</td></tr>";
			}
			echo "</table>";
		echo "<br/><a class='cleantalk_manual_link' href='?page=cleantalk&ct_send_connection_report=1'>".__('Send report', 'cleantalk')."</a>";
		}

	}
	echo "<script>
		jQuery(document).ready(function(){
			jQuery('.form-table tr').eq(1).children().first().hide();
		});
	</script>";
}

function ct_mail_send_connection_report() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();		
    $ct_data=ct_get_data();
    if ((isset($ct_options['send_connection_reports']) && @intval($ct_options['send_connection_reports']) == 1 && $ct_data['connection_reports']['negative']>0) || !empty($_GET['ct_send_connection_report']))
    {
		$to  = "welcome@cleantalk.org" ; 
		$subject = "Connection report for ".$_SERVER['HTTP_HOST']; 
		$message = ' 
				<html> 
				    <head> 
				        <title></title> 
				    </head> 
				    <body> 
				        <p>From '.$ct_data['connection_reports']['since'].' to '.date('d M').' has been made '.($ct_data['connection_reports']['success']+$ct_data['connection_reports']['negative']).' calls, where '.$ct_data['connection_reports']['success'].' were success and '.$ct_data['connection_reports']['negative'].' were negative</p> 
				        <p>Negative report:</p>
				        <table>  <tr>
				    <td>&nbsp;</td>
				    <td><b>Date</b></td>
				    <td><b>Page URL</b></td>
				    <td><b>Library report</b></td>
				  </tr>
				  ';
		foreach ($ct_data['connection_reports']['negative_report'] as $key=>$report)
		{
			$message.= "<tr><td>".($key+1).".</td><td>".$report['date']."</td><td>".$report['page_url']."</td><td>".$report['lib_report']."</td></tr>";
		}  
		$message.='</table></body></html>'; 

		$headers  = "Content-type: text/html; charset=windows-1251 \r\n"; 
		$headers .= "From: ".get_option('admin_email'); 
		mail($to, $subject, $message, $headers);    	
    }
 
	$ct_data['connection_reports']['success'] = 0;
	$ct_data['connection_reports']['negative'] = 0;
	$ct_data['connection_reports']['negative_report'] = array();
	$ct_data['connection_reports']['since'] = date('d M');
	update_option('cleantalk_data', $ct_data);	
	CleantalkCron::updateTask('send_connection_report', 'ct_mail_send_connection_report',  3600);

}
/**
 * Admin callback function - Displays inputs of 'apikey' plugin parameter
 */
function ct_input_apikey() {
	global $ct_options, $ct_data, $ct_notice_online_label;
	
	$ct_options=ct_get_options();
	$ct_data=ct_get_data();
	
	$blocked = isset($ct_data['admin_blocked']) ? $ct_data['admin_blocked'] : 0;
	
	if($blocked > 0){
		echo "<script>var cleantalk_blocked_message=\"<div style='height:24px;width:100%;display:table-cell; vertical-align:middle;'><span>CleanTalk ";
		printf(
			/* translators: %s: Number of spam messages */
			__( 'has blocked <b>%s</b>  spam.', 'cleantalk' ),
			number_format($blocked)
		);
		echo "</span></div><br />\";\n";
	}else{
		echo "<script>var cleantalk_blocked_message=\"\";\n";
	}
	echo "var cleantalk_statistics_link=\"<a class='cleantalk_manual_link' target='__blank' href='https://cleantalk.org/my?user_token=".@$ct_data['user_token']."&cp_mode=antispam'>".__('Click here to get anti-spam statistics', 'cleantalk')."</a>\";";
	echo "var cleantalk_support_link=\"<a class='cleantalk_auto_link' target='__blank' href='https://wordpress.org/support/plugin/cleantalk-spam-protect'>".__('Support', 'cleantalk')."</a>\";
	</script>";
	
	$value = $ct_options['apikey'];
	$def_value = ''; 
	$is_wpmu=false;
	if(!defined('CLEANTALK_ACCESS_KEY')){
		echo "<input id='cleantalk_apikey' name='cleantalk_settings[apikey]' size='20' type='text' value='$value' style=\"font-size: 14pt;\" placeholder='" . __('Enter the key', 'cleantalk') . "' />";
		echo "<script>var cleantalk_wpmu=false;</script>";
	}else{
		echo "<script>var cleantalk_wpmu=true;</script>";
		$is_wpmu = true;
	}
	
	//echo "<script src='".plugins_url( 'cleantalk-admin.js', __FILE__ )."?ver=".$cleantalk_plugin_version."'></script>\n";
	if (ct_valid_key($value) === false && !$is_wpmu){
		echo "<script>var cleantalk_good_key=false;</script>";
		if (function_exists('curl_init') && function_exists('json_decode')){
			echo '<br /><br />';
			echo "<a target='__blank' style='' href='https://cleantalk.org/register?platform=wordpress&email=".urlencode(ct_get_admin_email())."&website=".urlencode(parse_url(get_option('siteurl'),PHP_URL_HOST))."'><input type='button' class='cleantalk_auto_link' value='".__('Get access key manually', 'cleantalk')."' /></a>";
			if($ct_data['ip_license'] == 0){
				echo "&nbsp;" .  __("or") . "&nbsp;";
				echo '<input id="get_key_auto" name="get_apikey_auto" type="submit" class="cleantalk_manual_link" value="' . __('Get access key automatically', 'cleantalk') . '" />';
			}
			echo '<input type="hidden" id="ct_admin_timezone" name="ct_admin_timezone" value="null" />';
            echo "<br />";
            echo "<br />";
			
			ct_add_descriptions_to_fields(sprintf(__('Admin e-mail (%s) will be used for registration', 'cleantalk'), ct_get_admin_email()));
			echo '<div>';
				echo '<input checked type="checkbox" id="license_agreed" onclick="spbcSettingsDependencies(\'get_key_auto\');"/>';
				echo '<label for="spbc_license_agreed">';
					printf(
						__('I agree with %sPrivacy Policy%s of %sLicense Agreement%s', 'security-malware-firewall'),
						'<a href="https://cleantalk.org/publicoffer#privacy" target="_blank" style="color:#66b;">', '</a>',
						'<a href="https://cleantalk.org/publicoffer"         target="_blank" style="color:#66b;">', '</a>'
					);
				echo "</label>";
			echo '</div>';
		}
	} else {
        $cleantalk_support_links = "<br /><div>";
        $cleantalk_support_links .= "<a href='#' id='cleantalk_access_key_link' class='ct_support_link'>" . __("Show the access key", 'cleantalk') . "</a>";
        $cleantalk_support_links .= "&nbsp;&nbsp;";
        $cleantalk_support_links .= "&nbsp;&nbsp;";
        $cleantalk_support_links .= "<a href='edit-comments.php?page=ct_check_spam' class='ct_support_link'>" . __("Check comments for spam", 'cleantalk') . "</a>";
        $cleantalk_support_links .= "<a href='users.php?page=ct_check_users' class='ct_support_link'>" . __("Check users for spam", 'cleantalk') . "</a>";
        $cleantalk_support_links .= "&nbsp;&nbsp;";
        $cleantalk_support_links .= "&nbsp;&nbsp;";
        $cleantalk_support_links .= "<a href='#' id='cleantalk_negative_report_link' class='ct_support_link'>" . __("Negative report", 'cleantalk') . "</a>";
        $cleantalk_support_links .= "</div>";
		echo "<script type=\"text/javascript\">var cleantalk_good_key=true; var cleantalk_support_links = \"$cleantalk_support_links\";</script>";
	}
	
	if($ct_data['ip_license']){
		$cleantalk_support_links = "<br /><div>";
        $cleantalk_support_links .= "<a href='#' id='cleantalk_access_key_link' class='ct_support_link'>" . __("Show the access key", 'cleantalk') . "</a>";
        $cleantalk_support_links .= "</div>";
		echo "<script type=\"text/javascript\">var cleantalk_good_key=true; var cleantalk_support_links = \"$cleantalk_support_links\";</script>";
	}
	
	$test_failed = (!empty($ct_data['testing_failed']) 	? true : false);
	$moderate_ip = (!empty($ct_data['moderate_ip'])		? true : false);
	
	//Testing failed output
	if($test_failed && !$moderate_ip){
		echo "<script type=\"text/javascript\">var cleantalk_testing_failed = true;</script>";
		echo "<br>";
		echo "<div class='ct-warning-test-failed'>";
			printf(__('Testing is failed, please, check the settings! Tech support %s%s%s.', 'cleantalk'), '<a target="_blank" href="https://wordpress.org/support/plugin/cleantalk-spam-protect">', 'https://wordpress.org/support/plugin/cleantalk-spam-protect', '</a>');
		echo "</div>";
	}else{
		echo "<script type=\"text/javascript\">var cleantalk_testing_failed = false;</script>";
	}
}

/**
 * Admin callback function - Displays inputs of 'comments_test' plugin parameter
 */
function ct_input_comments_test() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();
	
	$value = $ct_options['comments_test'];
	echo "<input type='radio' id='cleantalk_comments_test1' name='cleantalk_settings[comments_test]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='cleantalk_comments_test1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_comments_test0' name='cleantalk_settings[comments_test]' value='0' " . ($value == '0' ? 'checked' : '') . " /><label for='cleantalk_comments_test0'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(__('WordPress, JetPack, WooCommerce.', 'cleantalk'));
}

//Titles for advanced settings.
function ct_input_what_fields_should_be_protected(){
	echo "<h3>".__('Forms to protect', 'cleantalk')."</h3>";
}

function ct_input_comments_and_messages(){
	echo "<h3>".__('Comments and messages', 'cleantalk')."</h3>";
}

function ct_input_data_processing(){
	echo "<h3>".__('Data processing', 'cleantalk')."</h3>";
}

function ct_input_administrator_panel(){
	echo "<h3>".__('Admin bar', 'cleantalk')."</h3>";
}

/**
 * Admin callback function - Displays inputs of 'comments_test' plugin parameter
 */
function ct_input_remove_links_from_approved_comments() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();
	
	$value = $ct_options['remove_comments_links'];
	echo "<input type='radio' id='cleantalk_remove_links_from_comments1' name='cleantalk_settings[remove_comments_links]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='cleantalk_remove_links_from_comments1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_remove_links_from_comments0' name='cleantalk_settings[remove_comments_links]' value='0' " . ($value == '0' ? 'checked' : '') . " /><label for='cleantalk_remove_links_from_comments0'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(__('Remove links from approved comments. Replace it with "[Link deleted]"', 'cleantalk'));
}

/**
 * Admin callback function - Displays inputs of 'comments_test' plugin parameter
 */
function ct_input_show_check_links() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();
	
	$value = $ct_options['show_check_links'];
	
	echo "<input type='radio' id='cleantalk_show_check_links1' name='cleantalk_settings[show_check_links]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='cleantalk_show_check_links1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_show_check_links1' name='cleantalk_settings[show_check_links]' value='0' " . ($value == '0' ? 'checked' : '') . " /><label for='cleantalk_show_check_links1'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(__('Shows little icon near IP addresses and Emails allowing you to check it via CleanTalk\'s database. Also allowing you to manage comments from the public post\'s page.', 'cleantalk'));
}

/**
 * Admin callback function - Displays inputs of 'comments_test' plugin parameter
 */
function ct_input_registrations_test() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();
	
	$value = $ct_options['registrations_test'];
	echo "<input type='radio' id='cleantalk_registrations_test1' name='cleantalk_settings[registrations_test]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='cleantalk_registrations_test1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_registrations_test0' name='cleantalk_settings[registrations_test]' value='0' " . ($value == '0' ? 'checked' : '') . " /><label for='cleantalk_registrations_test0'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(__('WordPress, BuddyPress, bbPress, S2Member, WooCommerce.', 'cleantalk'));
}

/**
 * Admin callback function - Displays inputs of 'contact_forms_test' plugin parameter
 */
function ct_input_contact_forms_test() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();
	
	$value = $ct_options['contact_forms_test'];
	echo "<input type='radio' id='cleantalk_contact_forms_test1' name='cleantalk_settings[contact_forms_test]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='cleantalk_contact_forms_test1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_contact_forms_test0' name='cleantalk_settings[contact_forms_test]' value='0' " . ($value == '0' ? 'checked' : '') . " /><label for='cleantalk_contact_forms_test0'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(__('Contact Form 7, Formidable forms, JetPack, Fast Secure Contact Form, WordPress Landing Pages, Gravity Forms.', 'cleantalk'));
}

/**
 * Admin callback function - Displays inputs of 'general_contact_forms_test' plugin parameter
 */
function ct_input_general_contact_forms_test() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();
	
	$value = $ct_options['general_contact_forms_test'];
	echo "<input type='radio' id='cleantalk_general_contact_forms_test1' name='cleantalk_settings[general_contact_forms_test]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='cleantalk_general_contact_forms_test1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_general_contact_forms_test0' name='cleantalk_settings[general_contact_forms_test]' value='0' " . ($value == '0' ? 'checked' : '') . " /><label for='cleantalk_general_contact_forms_test0'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(__('Anti spam test for any WordPress themes or contacts forms.', 'cleantalk'));
}

/**
 * Admin callback function - Displays inputs of 'wc_checkout_test' plugin parameter
 */
function ct_input_wc_chekout_test() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();
	
	$value = $ct_options['wc_checkout_test'];
	echo "<input type='radio' id='cleantalk_wc_checkout_test1' name='cleantalk_settings[wc_checkout_test]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='cleantalk_wc_checkout_test1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_wc_checkout_test0' name='cleantalk_settings[wc_checkout_test]' value='0' " . ($value == '0' ? 'checked' : '') . " /><label for='cleantalk_wc_checkout_test0'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(__('Anti spam test for WooCommerce checkout form.', 'cleantalk'));
}

/**
 * Admin callback function - Displays inputs of 'bp_private_messages' plugin parameter
 */
function ct_input_bp_private_messages() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();
	
	$value = $ct_options['bp_private_messages'];
	echo "<input type='radio' id='bp_private_messages1' name='cleantalk_settings[bp_private_messages]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='bp_private_messages1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='bp_private_messages0' name='cleantalk_settings[bp_private_messages]' value='0' " . ($value == '0' ? 'checked' : '') . " /><label for='bp_private_messages0'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(__('Check buddyPress private messages.', 'cleantalk'));
}

/**
 * @author Artem Leontiev
 * Admin callback function - Displays inputs of 'Publicate relevant comments' plugin parameter
 *
 * @return null
 */
function ct_input_remove_old_spam() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();

	$value = $ct_options['remove_old_spam'];
	echo "<input type='radio' id='cleantalk_remove_old_spam1' name='cleantalk_settings[remove_old_spam]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='cleantalk_remove_old_spam1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_remove_old_spam0' name='cleantalk_settings[remove_old_spam]' value='0' " . ($value == '0' ? 'checked' : '') . " /><label for='cleantalk_remove_old_spam0'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(sprintf(__('Delete spam comments older than %d days.', 'cleantalk'),  $ct_options['spam_store_days']));
}

/**
 * Admin callback function - Displays inputs of 'Show statistics in adminbar' plugin parameter
 *
 * @return null
 */
function ct_input_show_adminbar() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();

	if(isset($ct_options['show_adminbar']))
	{
		$value = @intval($ct_options['show_adminbar']);
	}
	else
	{
		$value=1;
	}
	echo "<input type='radio' id='cleantalk_show_adminbar1' name='cleantalk_settings[show_adminbar]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='cleantalk_show_adminbar1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_show_adminbar0' name='cleantalk_settings[show_adminbar]' value='0' " . ($value == '0' ? 'checked' : '') . " /><label for='cleantalk_show_adminbar0'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(sprintf(__('Show/hide CleanTalk icon in top level menu in WordPress backend. The number of submissions is being counted for past 24 hours.', 'cleantalk'),  $ct_options['show_adminbar']));
}

/**
 * Admin callback function - Displays inputs of 'Show statistics in adminbar' plugin parameter
 *
 * @return null
 */
function ct_input_general_postdata_test() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();

	if(isset($ct_options['general_postdata_test']))
	{
		$value = @intval($ct_options['general_postdata_test']);
	}
	else
	{
		$value=0;
	}
	echo "<input type='radio' id='cleantalk_general_postdata_test1' name='cleantalk_settings[general_postdata_test]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='cleantalk_general_postdata_test1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_general_postdata_test0' name='cleantalk_settings[general_postdata_test]' value='0' " . ($value == '0' ? 'checked' : '') . " /><label for='cleantalk_general_postdata_test0'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(sprintf(__('Check all POST submissions from website visitors. Enable this option if you have spam misses on website or you don`t have records about missed spam here:', 'cleantalk') . '&nbsp;' . '<a href="https://cleantalk.org/my/?user_token='.@$ct_data['user_token'].'&utm_source=wp-backend&utm_medium=admin-bar&cp_mode=antispam" target="_blank">' . __('CleanTalk dashboard', 'cleantalk') . '</a>.<br />' . __('СAUTION! Option can catch POST requests in WordPress backend', 'cleantalk'),  $ct_options['general_postdata_test']));
}

function ct_input_use_ajax() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();

	if(isset($ct_options['use_ajax']))
	{
		$value = @intval($ct_options['use_ajax']);
	}
	else
	{
		$value=1;
	}
	echo "<input type='radio' id='cleantalk_use_ajax1' name='cleantalk_settings[use_ajax]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='cleantalk_use_ajax1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_use_ajax0' name='cleantalk_settings[use_ajax]' value='0' " . ($value == '0' ? 'checked' : '') . " /><label for='cleantalk_use_ajax0'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(sprintf(__('Options helps protect WordPress against spam with any caching plugins. Turn this option on to avoid issues with caching plugins.', 'cleantalk')."<strong> ".__('Attention! Incompatible with AMP plugins!', 'cleantalk')."</strong>",  $ct_options['use_ajax']));
}

function ct_input_check_comments_number() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();

	if(isset($ct_options['check_comments_number']))
	{
		$value = @intval($ct_options['check_comments_number']);
	}
	else
	{
		$value=1;
	}
	
	if(defined('CLEANTALK_CHECK_COMMENTS_NUMBER'))
	{
		$comments_check_number = CLEANTALK_CHECK_COMMENTS_NUMBER;
	}
	else
	{
		$comments_check_number = 3;
	}
	
	echo "<input type='radio' id='cleantalk_check_comments_number1' name='cleantalk_settings[check_comments_number]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='cleantalk_check_comments_number1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_check_comments_number0' name='cleantalk_settings[check_comments_number]' value='0' " . ($value == '0' ? 'checked' : '') . " /><label for='cleantalk_check_comments_number0'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(sprintf(__("Dont't check comments for users with above", 'cleantalk') . $comments_check_number . __("comments.", 'cleantalk'),  $ct_options['check_comments_number']));
}

function ct_input_collect_details() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();

	if(isset($ct_options['collect_details']))
	{
		$value = @intval($ct_options['collect_details']);
	}
	else
	{
		$value=0;
	}
	
	echo "<div id='cleantalk_anchor2' style='display:none'></div>";
	echo "<input type='checkbox' id='collect_details1' name='cleantalk_settings[collect_details]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='collect_details1'> " . __('Collect details about browsers', 'cleantalk') . "</label>";
	ct_add_descriptions_to_fields(sprintf(__("Checking this box you allow plugin store information about screen size and browser plugins of website visitors. The option in a beta state.", 'cleantalk'),  $ct_options['spam_firewall']));
	echo "<script>
		jQuery(document).ready(function(){
			jQuery('#cleantalk_anchor2').parent().parent().children().first().hide();
			jQuery('#cleantalk_anchor2').parent().css('padding-left','0px');
			jQuery('#cleantalk_anchor2').parent().attr('colspan', '2');
		});
	</script>";
}

function ct_input_check_messages_number() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();

	if(isset($ct_options['check_messages_number']))
	{
		$value = @intval($ct_options['check_messages_number']);
	}
	else
	{
		$value=0;
	}
	
	if(defined('CLEANTALK_CHECK_MESSAGES_NUMBER'))
	{
		$messages_check_number = CLEANTALK_CHECK_MESSAGES_NUMBER;
	}
	else
	{
		$messages_check_number = 3;
	}
	
	echo "<input type='radio' id='cleantalk_check_messages_number1' name='cleantalk_settings[check_messages_number]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='cleantalk_check_messages_number1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_check_messages_number0' name='cleantalk_settings[check_messages_number]' value='0' " . ($value == '0' ? 'checked' : '') . " /><label for='cleantalk_check_messages_number0'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(sprintf(__("Dont't check messages for users with above $messages_check_number messages", 'cleantalk'),  $ct_options['check_messages_number']));
}

function ct_input_check_external() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();

	if(isset($ct_options['check_external']))
	{
		$value = @intval($ct_options['check_external']);
	}
	else
	{
		$value=0;
	}
	echo "<input type='radio' id='cleantalk_check_external1' name='cleantalk_settings[check_external]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='cleantalk_check_external1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_check_external0' name='cleantalk_settings[check_external]' value='0' " . ($value == '0' ? 'checked' : '') . " /><label for='cleantalk_check_external0'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(sprintf(__('Turn this option on to protect forms on your WordPress that send data to third-part servers (like MailChimp).', 'cleantalk'),  $ct_options['check_external']));
}

function ct_input_check_internal() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();

	if(isset($ct_options['check_internal']))
	{
		$value = @intval($ct_options['check_internal']);
	}
	else
	{
		$value=0;
	}
	echo "<input type='radio' id='cleantalk_check_internal1' name='cleantalk_settings[check_internal]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='cleantalk_check_internal1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_check_internal0' name='cleantalk_settings[check_internal]' value='0' " . ($value == '0' ? 'checked' : '') . " /><label for='cleantalk_check_internal0'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(sprintf(__('This option will enable protection for custom (hand-made) AJAX forms with PHP scripts handlers on your WordPress.', 'cleantalk'),  $ct_options['check_internal']));
}

function ct_input_set_cookies() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();
	
	echo "<input type='radio' id='cleantalk_set_cookies1' name='cleantalk_settings[set_cookies]' value='1' " . (!empty($ct_options['set_cookies']) ? 'checked' : '') . " /><label for='cleantalk_set_cookies1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_set_cookies0' name='cleantalk_settings[set_cookies]' value='0' " . (empty($ct_options['set_cookies']) ? 'checked' : '') . " /><label for='cleantalk_set_cookies0'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(sprintf(__('Turn this option off to deny plugin generates any cookies on website front-end. This option is helpful if you use Varnish. But most of contact forms will not be protected by CleanTalk if the option is turned off! <b>Warning: We strongly recommend you to enable this otherwise it could cause false positives spam detection.</b>', 'cleantalk')));
}

function ct_input_ssl_on() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();
	
	echo "<input type='radio' id='cleantalk_ssl_on1' name='cleantalk_settings[ssl_on]' value='1' " . (!empty($ct_options['ssl_on']) ? 'checked' : '') . " /><label for='cleantalk_ssl_on1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_ssl_on0' name='cleantalk_settings[ssl_on]' value='0' " . (empty($ct_options['ssl_on']) ? 'checked' : '') . " /><label for='cleantalk_ssl_on0'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(sprintf(__('Turn this option on to use encrypted (SSL) connection with CleanTalk servers.', 'cleantalk')));
}

function ct_input_protect_logged_in() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();
	
	echo "<input type='radio' id='cleantalk_protect_logged_in1' name='cleantalk_settings[protect_logged_in]' value='1' " . (!empty($ct_options['protect_logged_in']) ? 'checked' : '') . " /><label for='cleantalk_protect_logged_in1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_protect_logged_in0' name='cleantalk_settings[protect_logged_in]' value='0' " . (empty($ct_options['protect_logged_in']) ? 'checked' : '') . " /><label for='cleantalk_protect_logged_in0'> " . __('No') . "</label>";
	ct_add_descriptions_to_fields(sprintf(__('Turn this option on to check for spam any submissions (comments, contact forms and etc.) from registered Users.', 'cleantalk')));

    return null;
}

function ct_input_show_link() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();
	
   /* echo "<input type='radio' id='cleantalk_show_link1' name='cleantalk_settings[show_link]' value='1' " . ($value == '1' ? 'checked' : '') . " /><label for='cleantalk_show_link1'> " . __('Yes') . "</label>";
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo "<input type='radio' id='cleantalk_show_link0' name='cleantalk_settings[show_link]' value='0' " . ($value == '0' ? 'checked' : '') . " /><label for='cleantalk_show_link0'> " . __('No') . "</label>";*/
	
	echo "<div id='cleantalk_anchor' style='display:none'></div><input type=hidden name='cleantalk_settings[show_link]' value='0' />";
	echo "<input type='checkbox' id='cleantalk_show_link1' name='cleantalk_settings[show_link]' value='1' " . (!empty($ct_options['show_link']) ? 'checked' : '') . " /><label for='cleantalk_show_link1'> " . __('Tell others about CleanTalk', 'cleantalk') . "</label>";
	ct_add_descriptions_to_fields(sprintf(__("Checking this box places a small link under the comment form that lets others know what anti-spam tool protects your site.", 'cleantalk'),  $ct_options['show_link']));
	echo "<script>
		jQuery(document).ready(function(){
			jQuery('#cleantalk_anchor').parent().parent().children().first().hide();
			jQuery('#cleantalk_anchor').parent().css('padding-left','0px');
		});
	</script>";
}

function ct_input_spam_firewall() {
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();
	
	echo "<div id='cleantalk_anchor1' style='display:none'></div><input type=hidden name='cleantalk_settings[spam_firewall]' value='0' />";
	echo "<input type='checkbox' id='cleantalk_spam_firewall1' name='cleantalk_settings[spam_firewall]' value='1' " . (!empty($ct_options['spam_firewall']) ? 'checked' : '') . " /><label for='cleantalk_spam_firewall1'> " . __('SpamFireWall') . "</label>";
	ct_add_descriptions_to_fields(sprintf(__("This option allows to filter spam bots before they access website. Also reduces CPU usage on hosting server and accelerates pages load time.", 'cleantalk'),  $ct_options['spam_firewall']) .
        " " .
        '<a href="https://cleantalk.org/cleantalk-spam-firewall" style="font-size: 10pt; color: #666 !important" target="_blank">' . __('Learn more', 'cleantalk') . '</a>.'
    );
	echo "<script>
		jQuery(document).ready(function(){
			jQuery('#cleantalk_anchor1').parent().parent().children().first().hide();
			jQuery('#cleantalk_anchor1').parent().css('padding-left','0px');
			jQuery('#cleantalk_anchor1').parent().attr('colspan', '2');
		});
	</script>";
}


/**
 * Admin callback function - Plugin parameters validator
 */
function ct_settings_validate($input) {
	return $input;
}


/**
 * Admin callback function - Displays plugin options page
 */
function apbct_settings_page() {
	?>
<style type="text/css">
 .cleantalk_manual_link {padding: 10px; background: #3399FF; color: #fff; border:0 none;
	cursor:pointer;
	-webkit-border-radius: 5px;
	border-radius: 5px; 
	font-size: 12pt;
}
.cleantalk_auto_link{
	background: #ccc;
	border-color: #999;
	-webkit-box-shadow: inset 0 1px 0 rgba(200,200,200,.5),0 1px 0 rgba(0,0,0,.15);
	box-shadow: inset 0 1px 0 rgba(200,200,200,.5),0 1px 0 rgba(0,0,0,.15);
	color: #000;
	text-decoration: none;
	display: inline-block;
	text-decoration: none;
	font-size: 13px;
	line-height: 26px;
	height: 28px;
	margin: 0;
	padding: 0 10px 1px;
	cursor: pointer;
	border-width: 1px;
	border-style: solid;
	-webkit-appearance: none;
	-webkit-border-radius: 2px;
	border-radius: 2px;
	white-space: nowrap;
	-webkit-box-sizing: border-box;
	-moz-box-sizing: border-box;
	box-sizing: border-box;
}
.cleantalk_auto_link:hover{
	color: #fff;
}
.cleantalk_manual_link 
{
	background: #2ea2cc;
	border-color: #0074a2;
	-webkit-box-shadow: inset 0 1px 0 rgba(120,200,230,.5),0 1px 0 rgba(0,0,0,.15);
	box-shadow: inset 0 1px 0 rgba(120,200,230,.5),0 1px 0 rgba(0,0,0,.15);
	color: #fff;
	text-decoration: none;
		display: inline-block;
	text-decoration: none;
	font-size: 13px;
	line-height: 26px;
	height: 28px;
	margin: 0;
	padding: 0 10px 1px;
	cursor: pointer;
	border-width: 1px;
	border-style: solid;
	-webkit-appearance: none;
	-webkit-border-radius: 3px;
	border-radius: 3px;
	white-space: nowrap;
	-webkit-box-sizing: border-box;
	-moz-box-sizing: border-box;
	box-sizing: border-box;
}
.cleantalk_manual_link:hover
{
	color:black;
}

</style>

	<div>
		<?php				
		if(is_network_admin())
		{	
			print '<form method="post">';
			if(defined('CLEANTALK_ACCESS_KEY'))
			{
				print "<br />Your CleanTalk access key is: <b>".CLEANTALK_ACCESS_KEY."</b><br />
						You can change it in your wp-config.php file.<br />";
			}
			else
			{
				print "<br />To set up global CleanTalk access key for all websites, define constant in your wp-config.php file before defining database constants:<br />
						<pre>define('CLEANTALK_ACCESS_KEY', 'place your key here');</pre>";
			}
		}
		else
		{
			echo '<div style="float: right; padding: 0 15px; font-size: 13px;">';
	
				echo __('CleanTalk\'s tech support:', 'cleantalk')
					.'&nbsp;'
					.'<a target="_blank" href="https://wordpress.org/support/plugin/cleantalk-spam-protect">Wordpress.org</a>.'
				// .' <a href="https://community.cleantalk.org/viewforum.php?f=25" target="_blank">'.__("Tech forum", 'cleantalk').'</a>'
				// .($user_token ? ", <a href='https://cleantalk.org/my/support?user_token=$user_token&cp_mode=antispam' target='_blank'>".__("Service support ", 'cleantalk').'</a>' : '').
					.'<br>';
				echo __('Plugin Homepage at', 'cleantalk').' <a href="http://cleantalk.org" target="_blank">cleantalk.org</a>.<br/>';
				echo '<span id="apbct_gdpr_open_modal" style="text-decoration: underline;">'.__('GDPR compliance', 'cleantalk').'</span><br/>';
				echo __('Use s@cleantalk.org to test plugin in any WordPress form.', 'cleantalk').'<br>';
				echo __('CleanTalk is registered Trademark. All rights reserved.', 'cleantalk').'<br/>';
				echo '<b style="display: inline-block; margin-top: 10px;">'.sprintf(__('Do you like CleanTalk? %sPost your feedback here%s.', 'cleantalk'), '<a href="https://wordpress.org/support/plugin/cleantalk-spam-protect/reviews/#new-post" target="_blank">', '</a>').'</b><br />';
				ct_input_get_premium();
			echo '<div id="gdpr_dialog" style="display: none; padding: 7px;">';
				apbct_show_GDPR_text();
			echo '</div>';
			echo '</div>';
			echo '<form action="options.php" method="post">';
			settings_fields('cleantalk_settings');
			do_settings_sections('cleantalk');
			echo '<br>';
			echo '<input name="Submit" type="submit" class="cleantalk_manual_link" value="'.__('Save Changes').'" />';
		}
		echo "</form>";
	echo "</div>";
	// Translate banner for non EN locale
	if(substr(get_locale(), 0, 2) != 'en'){
		require_once(CLEANTALK_PLUGIN_DIR.'templates/translate_banner.php');
		printf($ct_translate_banner_template, substr(get_locale(), 0, 2));
	}
}

/**
 * Notice blog owner if plugin is used without Access key 
 * @return bool 
 */
function cleantalk_admin_notice_message(){
	global $show_ct_notice_trial, $show_ct_notice_renew, $show_ct_notice_online, $show_ct_notice_autokey, $ct_notice_autokey_value, $ct_plugin_name, $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data = ct_get_data();
	$page = get_current_screen();
	
	//General notice control flags
	$moderate_ip =		(empty($ct_data['moderate_ip']) ? 0 : $ct_data['moderate_ip']);
	$self_owned_key = 	(!$moderate_ip && !defined('CLEANTALK_ACCESS_KEY') ? true : false);
	$is_dashboard = 	(is_network_admin() || is_admin() ? true : false);
	$is_admin = 		(current_user_can('activate_plugins') ? true : false);
	$show_notice = true;
	
	//Notice control flags
	$show_ct_notice_trial = 		(isset($ct_data['show_ct_notice_trial']) 				? intval($ct_data['show_ct_notice_trial']) 			: 0);
	$show_ct_notice_renew = 		(isset($ct_data['show_ct_notice_renew']) 				? intval($ct_data['show_ct_notice_renew']) 			: 0);
	$show_ct_notice_review = 		(isset($ct_data['show_ct_notice_review'])				? intval($ct_data['show_ct_notice_review'])			: 0);
	$next_notice_show = 			(isset($ct_data['next_notice_show']) 	 				? intval($ct_data['next_notice_show']) 				: 0);	//inactive
	
	$show_ct_notice_auto_update = 	(isset($ct_data['show_ct_notice_auto_update'])			? intval($ct_data['show_ct_notice_auto_update'])	: 0);
	$auto_update_app =			 	(isset($ct_data['auto_update_app'])						? $ct_data['auto_update_app'] 		                : 0);
	
	$page_is_ct_settings = 			($page->id == 'settings_page_cleantalk' || $page->id == 'settings_page_cleantalk-network' ? true            : false);
	
	//Misc
	$user_token =    (isset($ct_data['user_token']) && $ct_data['user_token'] != '' ? "&user_token={$ct_data['user_token']}" : "");
	$settings_link = (is_network_admin() ? "settings.php?page=cleantalk" : "options-general.php?page=cleantalk");
		
	if($self_owned_key && $is_dashboard && $is_admin){
		// Auto update notice
		if($show_ct_notice_auto_update && $auto_update_app != -1 && empty($_COOKIE['apbct_update_banner_closed'])){
			$link 	= '<a href="http://cleantalk.org/help/auto-update" target="_blank">%s</a>';
			$button = sprintf($link, '<input type="button" class="button button-primary" value="'.__('Learn more', 'cleantalk').'"  />');
			echo '<div class="error notice is-dismissible apbct_update_notice">'
				.'<h3>'
					.__('Do you know that Anti-Spam by CleanTalk has auto update option?', 'cleantalk')
					.'</br></br>'
					.$button
				.'</h3>'
			.'</div>';
		}
		
		//Unable to get key automatically (if apbct_admin_init().getAutoKey() returns error)
		if ($show_notice && $show_ct_notice_autokey){
			echo '<div class="error">
				<h3>' . sprintf(__("Unable to get Access key automatically: %s", 'cleantalk'), $ct_notice_autokey_value).
					"<a target='__blank' style='margin-left: 10px' href='https://cleantalk.org/register?platform=wordpress&email=" . urlencode(ct_get_admin_email())."&website=" . urlencode(parse_url(get_option('siteurl'),PHP_URL_HOST))."'>".__('Get the Access key', 'cleantalk').'</a>
				</h3>
			</div>';
		}
		
		//key == "" || "enter key"
		if ($show_notice && !ct_valid_key()){
			echo "<div class='error'>"
				."<h3>"
					.sprintf(__("Please enter Access Key in %s settings to enable anti spam protection!", 'cleantalk'), "<a href='{$settings_link}'>CleanTalk plugin</a>")
				."</h3>"
			."</div>";
			$show_notice = false;
		}
		
		$test = isset($ct_data['service_id'], $ct_data['moderate']) && $ct_data['service_id']%2 == 0 && $ct_data['moderate'] == 0
			? true
			: false;
		
		//"Trial period ends" notice from apbct_admin_init().api_method__notice_paid_till()
		if ($show_notice && $show_ct_notice_trial == 1) {
			if($test){				
				if(isset($_GET['page']) && in_array($_GET['page'], array('cleantalk','ct_check_users','ct_check_spam'))){
			echo '<div class="error">
				<h3>' . sprintf(__("%s trial period ends, please upgrade to %s!", 'cleantalk'), 
					"<a href='{$settings_link}'>$ct_plugin_name</a>", 
					"<a href=\"http://cleantalk.org/my/bill/recharge?utm_source=wp-backend&utm_medium=cpc&utm_campaign=WP%20backend%20trial$user_token&cp_mode=antispam\" target=\"_blank\"><b>premium version</b></a>") .
				'</h3>
			</div>';
			$show_notice = false;
				}
			}else{
				echo '<div class="error">
					<h3>' . sprintf(__("%s trial period ends, please upgrade to %s!", 'cleantalk'), 
						"<a href='{$settings_link}'>$ct_plugin_name</a>", 
						"<a href=\"http://cleantalk.org/my/bill/recharge?utm_source=wp-backend&utm_medium=cpc&utm_campaign=WP%20backend%20trial$user_token&cp_mode=antispam\" target=\"_blank\"><b>premium version</b></a>") .
					'</h3>
				</div>';
				$show_notice = false;
			}
		}
		
		//Renew notice from apbct_admin_init().api_method__notice_paid_till()
		if ($show_notice && $show_ct_notice_renew == 1) {
			$renew_link = "<a href=\"http://cleantalk.org/my/bill/recharge?utm_source=wp-backend&utm_medium=cpc&utm_campaign=WP%%20backend%%20renew$user_token&cp_mode=antispam\" target=\"_blank\">%s</a>";
			$button_html 	= sprintf($renew_link, '<input type="button" class="button button-primary" value="'.__('RENEW ANTI-SPAM', 'cleantalk').'"  />');
			$link_html 		= sprintf($renew_link, "<b>".__('next year', 'cleantalk')."</b>");
			
			echo '<div class="updated">
				<h3>'. 
					sprintf(__("Please renew your anti-spam license for %s.", 'cleantalk'), $link_html). '<br /><br />' . $button_html . 
				'</h3>
			</div>';
			$show_notice = false;
		}
		
		//"Wrong access key" notice (if ct_update_option().METHOD_notice_validate_key returns a error)
		if ($show_notice && $show_ct_notice_online === 'N'){
			echo '<div class="error">
				<h3><b>'.
					__("Wrong <a href='{$settings_link}'><b style=\"color: #49C73B;\">Clean</b><b style=\"color: #349ebf;\">Talk</b> access key</a>! Please check it or ask <a target=\"_blank\" href=\"https://cleantalk.org/forum/\">support</a>.", 'cleantalk').
				'</b></h3>
			</div>';
		}
	}

	return true;
}

/**
 * @author Artem Leontiev
 *
 * Add descriptions for field
 */
function ct_add_descriptions_to_fields($descr = '') {
	echo "<div style='font-size: 10pt; color: #666 !important'>$descr</div>";
}

/**
* Test API key 
*/
function ct_valid_key($apikey = null) {
	global $ct_options, $ct_data;
	
	if ($apikey === null) {
	    $ct_options = ct_get_options();
		$apikey = $ct_options['apikey'];
	}
	
    return ($apikey === 'enter key' || $apikey === '') ? false : true;
}

// Ajax action feedback form comments page.
function ct_comment_send_feedback($comment_id = null, $comment_status = null, $change_status = false, $direct_call = null){
	
	// For AJAX call
	check_ajax_referer('ct_secret_nonce', 'security');
	$comment_id     = !empty($_POST['comment_id'])     ? $_POST['comment_id']     : false;
	$comment_status = !empty($_POST['comment_status']) ? $_POST['comment_status'] : false;
	$change_status  = !empty($_POST['change_status'])  ? $_POST['change_status']  : false;
	
	// If enter params is empty exit
	if(!$comment_id || !$comment_status)
		die();
	
	// $comment = get_comment($comment_id, 'ARRAY_A');
	$hash = get_comment_meta($comment_id, 'ct_hash', true);
	
	// If we can send the feedback
	if($hash){
		
		// Approving
		if($comment_status == '1' || $comment_status == 'approve'){
			$result = ct_send_feedback($hash.":1");
			// $comment['comment_content'] = ct_unmark_red($comment['comment_content']);
			// wp_update_comment($comment);
			$result === true ? 1 : 0;
		}
		
		// Disapproving	
		if($comment_status == 'spam'){
			$result = ct_send_feedback($hash.":0");
			$result === true ? 1 : 0;
		}
	}else{
		$result = 'no_hash';
	}
	
	// Changing comment status(folder) if flag is set. spam || approve
	if($change_status !== false)
		wp_set_comment_status($comment_id, $comment_status);
		
	if(!$direct_call){
		echo !empty($result) ? $result : 0;
		die();
	}else{
		
	}
}

// Ajax action feedback form user page.
function ct_user_send_feedback($user_id = null, $status = null, $direct_call = null){
	
	check_ajax_referer('ct_secret_nonce', 'security');
	
	if(!$direct_call){
		$user_id = $_POST['user_id'];
		$status  = $_POST['status'];
	}
		
	$hash = get_user_meta($user_id, 'ct_hash', true);
	
	if($hash){
		if($status == 'approve' || $status == 1){
			$result = ct_send_feedback($hash.":1");
			$result === true ? 1 : 0;
		}
		if($status == 'spam' || $status == 'disapprove' || $status == 0){
			$result = ct_send_feedback($hash.":0");
			$result === true ? 1 : 0;
		}
	}else{
		$result = 'no_hash';
	}
	
	if(!$direct_call){
		echo !empty($result) ? $result : 0;
		die();
	}else{
		
	}
	
}

/**
 * Admin filter 'get_comment_text' - Adds some info to comment text to display
 * @param 	string $current_text Current comment text
 * @return	string New comment text
 */
function ct_get_comment_text($current_text) {
	global $comment;
	$new_text = $current_text;
	if (isset($comment) && is_object($comment)) {
		$hash = get_comment_meta($comment->comment_ID, 'ct_hash', true);
		if (!empty($hash)) {
			$new_text .= '<hr>Cleantalk ID = ' . $hash;
		}
	}
	return $new_text;
}

/**
 * Send feedback for user deletion 
 * @return null 
 */
function ct_delete_user($user_id, $reassign = null){
	
	$hash = get_user_meta($user_id, 'ct_hash', true);
	if ($hash !== '') {
		ct_feedback($hash, 0);
	}
}

/**
 * Manage links in plugins list
 * @return array
*/
function apbct_plugin_action_links($links, $file) {
	
	$settings_link = is_network_admin()
		? '<a href="settings.php?page=cleantalk">' . __( 'Settings' ) . '</a>'
		: '<a href="options-general.php?page=cleantalk">' . __( 'Settings' ) . '</a>';
		
	array_unshift( $links, $settings_link ); // before other links
	return $links;
}

/**
 * Manage links and plugins page
 * @return array
*/
function apbct_register_plugin_links($links, $file) {
	global $ct_plugin_basename;
	//Return if it's not our plugin
	if ($file != $ct_plugin_basename )
		return $links;
		
	// $links[] = is_network_admin()
		// ? '<a class="ct_meta_links ct_setting_links" href="settings.php?page=cleantalk">' . __( 'Settings' ) . '</a>'
		// : '<a class="ct_meta_links ct_setting_links" href="options-general.php?page=cleantalk">' . __( 'Settings' ) . '</a>';
	
	if(substr(get_locale(), 0, 2) != 'en')
		$links[] = '<a class="ct_meta_links ct_translate_links" href="'
				.sprintf('https://translate.wordpress.org/locale/%s/default/wp-plugins/cleantalk-spam-protect', substr(get_locale(), 0, 2))
				.'" target="_blank">'
				.__('Translate', 'cleantalk')
			.'</a>';
			
	$links[] = '<a class="ct_meta_links ct_faq_links" href="http://wordpress.org/plugins/cleantalk-spam-protect/faq/" target="_blank">' . __( 'FAQ','cleantalk' ) . '</a>';
	$links[] = '<a class="ct_meta_links ct_support_links"href="https://wordpress.org/support/plugin/cleantalk-spam-protect" target="_blank">' . __( 'Support','cleantalk' ) . '</a>';
	$trial = ct_input_get_premium(false);
	if(!empty($trial))
		$links[] = ct_input_get_premium(false);
	
	return $links;
}

/**
 * After options update
 * @return array
*/
function ct_update_option($option_name) {
	global $show_ct_notice_online, $ct_notice_online_label, $ct_notice_trial_label, $trial_notice_showtime, $ct_options, $ct_data, $ct_server_timeout;
	$ct_options = ct_get_options(true);
	$ct_data = ct_get_data(true);

	if($option_name !== 'cleantalk_settings') {
		return;
	}

	$api_key = $ct_options['apikey'];
	if (isset($_POST['cleantalk_settings']['apikey'])) {
		$api_key = trim($_POST['cleantalk_settings']['apikey']);
		$ct_options['apikey'] = $api_key;
	}
	
	if (!ct_valid_key($api_key)) {
		return;
	}
	
	if (isset($_POST['cleantalk_settings']['spam_firewall'])) {
        if ($_POST['cleantalk_settings']['spam_firewall'] == 1) {
            ct_sfw_update();
			ct_sfw_send_logs();
        } else {
            // Reseting SFW logs to do not keep huge ammount of data.
			$ct_data['sfw_log']= array();
        }
    }
	
	$result = CleantalkHelper::api_method__notice_validate_key($api_key, preg_replace('/http[s]?:\/\//', '', get_option('siteurl'), 1));
	
	if (empty($result['error'])){
		if($result['valid'] == 1){
			$key_valid = true;
			$app_server_error = false;
			$ct_data['testing_failed']=0;
		}else{
			$key_valid = false;
			$app_server_error = false;
			$ct_data['testing_failed']=1;
		}
	}else{
		$key_valid = true;
		$app_server_error = true;
		$ct_data['testing_failed']=1;
	}
		
	if ($key_valid) {
		// Removes cookie for server errors
		if ($app_server_error) {
			setcookie($ct_notice_online_label, '', 1, '/'); // time 1 is exactly in past even clients time() is wrong
			unset($_COOKIE[$ct_notice_online_label]);
		} else {
			setcookie($ct_notice_online_label, (string) time(), strtotime("+14 days"), '/');
		}
		setcookie($ct_notice_trial_label, '0', strtotime("+$trial_notice_showtime minutes"), '/');
				
	} else {
		setcookie($ct_notice_online_label, 'BAD_KEY', 0, '/');
	}
	
	update_option('cleantalk_data', $ct_data);
	
    return null;
}

/**
 * Unmark bad words
 * @param string $message
 * @return string Cleat comment
 */
function ct_unmark_red($message) {
	$message = preg_replace("/\<font rel\=\"cleantalk\" color\=\"\#FF1000\"\>(\S+)\<\/font>/iu", '$1', $message);

	return $message;
}

function apbct_show_GDPR_text(){
?>
	<p>The notice requirements remain and are expanded. They must include the retention time for personal data, and contact information for data controller and data protection officer has to be provided.</p>

	<p>Automated individual decision-making, including profiling (Article 22) is contestable, similarly to the Data Protection Directive (Article 15). Citizens have rights to question and fight significant decisions that affect them that have been made on a solely-algorithmic basis. Many media outlets have commented on the introduction of a "right to explanation" of algorithmic decisions, but legal scholars have since argued that the existence of such a right is highly unclear without judicial tests and is limited at best.</p>

	<p>To be able to demonstrate compliance with the GDPR, the data controller should implement measures, which meet the principles of data protection by design and data protection by default. Privacy by design and by default (Article 25) require data protection measures to be designed into the development of business processes for products and services. Such measures include pseudonymising personal data, by the controller, as soon as possible (Recital 78).</p>

	<p>It is the responsibility and the liability of the data controller to implement effective measures and be able to demonstrate the compliance of processing activities even if the processing is carried out by a data processor on behalf of the controller (Recital 74).</p>

	<p>Data Protection Impact Assessments (Article 35) have to be conducted when specific risks occur to the rights and freedoms of data subjects. Risk assessment and mitigation is required and prior approval of the national data protection authorities (DPAs) is required for high risks. Data protection officers (Articles 37–39) are required to ensure compliance within organisations.</p>

	<p>They have to be appointed:</p>
	<ul style="padding: 0px 25px; list-style: disc;">
		<li>for all public authorities, except for courts acting in their judicial capacity</li>
		<li>if the core activities of the controller or the processor are:</li>
			<ul style="padding: 0px 25px; list-style: disc;">
				<li>processing operations, which, by virtue of their nature, their scope and/or their purposes, require regular and systematic monitoring of data subjects on a large scale</li>
				<li>processing on a large scale of special categories of data pursuant to Article 9 and personal data relating to criminal convictions and offences referred to in Article 10';</li>
			</ul>
		</li>
	</ul>
	<?php
}

?>