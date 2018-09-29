<?php
/*
  Plugin Name: Anti-Spam by CleanTalk
  Plugin URI: http://cleantalk.org
  Description: Max power, all-in-one, no Captcha, premium anti-spam plugin. No comment spam, no registration spam, no contact spam, protects any WordPress forms.
  Version: 5.105
  Author: СleanTalk <welcome@cleantalk.org>
  Author URI: http://cleantalk.org
*/

$cleantalk_executed = false;

// Getting version form main file (look above)
$plugin_info = get_file_data(__FILE__, array('Version' => 'Version', 'Name' => 'Plugin Name',));

// Common params
define('APBCT_NAME',             $plugin_info['Name']);
define('APBCT_VERSION',          $plugin_info['Version']);
define('APBCT_URL_PATH',         plugins_url('', __FILE__));          //HTTP path.   Plugin root folder without '/'.
define('APBCT_DIR_PATH',         plugin_dir_path(__FILE__));          //System path. Plugin root folder with '/'.
define('APBCT_PLUGIN_BASE_NAME', plugin_basename(__FILE__));          //Plugin base name.
define('APBCT_CASERT_PATH',      file_exists(ABSPATH.WPINC.'/certificates/ca-bundle.crt') ? ABSPATH.WPINC.'/certificates/ca-bundle.crt' : ''); // SSL Serttificate path

// API params
define('CLEANTALK_AGENT',        'wordpress-'.str_replace('.', '', $plugin_info['Version']));
define('CLEANTALK_API_URL',      'https://api.cleantalk.org');      //Api URL
define('CLEANTALK_MODERATE_URL', 'https://moderate.cleantalk.org'); //Api URL

// Option names
define('APBCT_DATA',             'cleantalk_data');             //Option name with different plugin data.
define('APBCT_SETTINGS',         'cleantalk_settings');         //Option name with plugin settings.
define('APBCT_NETWORK_SETTINGS', 'cleantalk_network_settings'); //Option name with plugin network settings.
define('APBCT_DEBUG',            'cleantalk_debug');            //Option name with a debug data. Empty by default.

// Different params
define('APBCT_REMOTE_CALL_SLEEP', 10); // Minimum time between remote call

// Database parameters
global $wpdb;
define('APBCT_TBL_FIREWALL_DATA', $wpdb->base_prefix . 'cleantalk_sfw');      // Table with firewall data.
define('APBCT_TBL_FIREWALL_LOG',  $wpdb->base_prefix . 'cleantalk_sfw_logs'); // Table with firewall logs.
define('APBCT_TBL_SESSIONS',      $wpdb->base_prefix . 'cleantalk_sessions'); // Table with session data.
define('APBCT_SELECT_LIMIT',   5000); // Select limit for logs.
define('APBCT_WRITE_LIMIT',    5000); // Write limit for firewall data.

if(!defined('CLEANTALK_PLUGIN_DIR')){
	
    define('CLEANTALK_PLUGIN_DIR', plugin_dir_path(__FILE__));
    
    require_once( CLEANTALK_PLUGIN_DIR . 'lib/CleantalkDB_Wordpress.php');      // State class
	
	require_once( CLEANTALK_PLUGIN_DIR . 'lib/cleantalk-php-patch.php'); // Pathces fpr different functions which not exists
	require_once( CLEANTALK_PLUGIN_DIR . 'lib/CleantalkHelper.php');     // Helper class. Different useful functions
	require_once( CLEANTALK_PLUGIN_DIR . 'lib/CleantalkAPI.php');        // Helper class. Different useful functions
	require_once( CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk.php');           // Main class for request
	require_once( CLEANTALK_PLUGIN_DIR . 'lib/CleantalkRequest.php');    // Holds request data
	require_once( CLEANTALK_PLUGIN_DIR . 'lib/CleantalkResponse.php');   // Holds response data
	require_once( CLEANTALK_PLUGIN_DIR . 'lib/CleantalkCron.php');       // Cron handling
    require_once( CLEANTALK_PLUGIN_DIR . 'lib/CleantalkState.php');      // State class
    require_once( CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-common.php');	
	
	// define('APBCT_HOSTER_API_KEY', '123');
	// define('APBCT_WHITELABLE',     true);
	
	// Global ArrayObject with settings and other global varables
	global $apbct;
	$apbct = new CleantalkState('cleantalk', array('settings', 'data', 'debug', 'errors'));
	
	// Customize CleantalkState
	// Account status
	$apbct->plugin_name = APBCT_NAME;
	$apbct->base_name = 'cleantalk-spam-protect/cleantalk.php';
	
	$apbct->logo                 = plugin_dir_url(__FILE__) . '/inc/images/logo.png';
	$apbct->logo__small          = plugin_dir_url(__FILE__) . '/inc/images/logo_small.png';
	$apbct->logo__small__colored = plugin_dir_url(__FILE__) . '/inc/images/logo_color.png';
	
	$apbct->key_is_ok          = !empty($apbct->data['key_is_ok']) ? $apbct->data['key_is_ok'] : 0;
	$apbct->key_is_ok          = isset($apbct->data['testing_failed']) && $apbct->data['testing_failed'] == 0 ? 1 : $apbct->key_is_ok;
	
	$apbct->data['user_counter']['since']       = isset($apbct->data['user_counter']['since']) ? $apbct->data['user_counter']['since'] : date('d M');
	$apbct->data['connection_reports']['since'] = isset($apbct->data['connection_reports']['since']) ? $apbct->data['user_counter']['since'] : date('d M');
	
	// White label reassignments
	$apbct->white_label = defined('APBCT_WHITELABLE') && APBCT_WHITELABLE == true ? true : false;
	if($apbct->white_label){
		// $apbct->plugin_name = $apcbt->data['white_label_data']['plugin_name'];
		
		// $apbct->logo                 = $apcbt->data['white_label_data']['logo']
		// $apbct->logo__small          = $apcbt->data['white_label_data']['logo__small']
		// $apbct->logo__small__colored = $apcbt->data['white_label_data']['logo__small__colored']
		
		$apbct->plugin_name = 'Some plugin';
	}else{
		require_once( CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-widget.php');
		$apbct->settings['apikey'] = defined('CLEANTALK_ACCESS_KEY') ? CLEANTALK_ACCESS_KEY : $apbct->settings['apikey'];
	}
	
	// Self cron
	if(!defined('DOING_CRON') || (defined('DOING_CRON') && DOING_CRON !== true)){
		
		$ct_cron = new CleantalkCron();
		$ct_cron->checkTasks();
		
		if(!empty($ct_cron->tasks_to_run)){
			
			define('CT_CRON', true); // Letting know functions that they are running under CT_CRON
			$ct_cron->runTasks();
			unset($ct_cron);
			
		}
	}
	
	//Delete cookie for admin trial notice
	add_action('wp_logout', 'ct_wp_logout');
	
	// Set cookie only for unauthorized users and for non-AJAX requests
	if (!is_admin() && (!defined('DOING_AJAX') || (defined('DOING_AJAX') && !DOING_AJAX))){
		add_action('template_redirect','apbct_cookie', 2);
	}
		
	// Early checks
	// Facebook
	if ($apbct->settings['general_contact_forms_test'] == 1
		&& (!empty($_POST['action']) && $_POST['action'] == 'fb_intialize')
		&& !empty($_POST['FB_userdata'])
	){
		require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public.php');
		if (ct_is_user_enable()){
			$ct_check_post_result=false;
			ct_registration_errors(null);
		}
		
	}
	
    if(isset($_SERVER['REQUEST_URI']) && stripos($_SERVER['REQUEST_URI'],'admin-ajax.php') !== false && sizeof($_POST) > 0 && isset($_GET['action']) && $_GET['action']=='ninja_forms_ajax_submit')
    	$_POST['action']='ninja_forms_ajax_submit';
    
	if(!is_admin() && !defined('DOING_AJAX')){
		
		// Remote calls
		if(isset($_GET['spbc_remote_call_token'], $_GET['spbc_remote_call_action'], $_GET['plugin_name']) && in_array($_GET['plugin_name'], array('antispam','anti-spam', 'apbct'))){
			apbct_remote_call__perform();
		}
	}
		
	// SpamFireWall check
	if( $apbct->settings['spam_firewall'] == 1 && !is_admin() || $apbct->settings['spam_firewall'] ==1 && defined( 'DOING_AJAX' ) && DOING_AJAX && $_SERVER["REQUEST_METHOD"] == 'GET'){
		apbct_sfw__check();
    }
		
    // Activation/deactivation functions must be in main plugin file.
    // http://codex.wordpress.org/Function_Reference/register_activation_hook
    register_activation_hook( __FILE__, 'apbct_activation' );
    register_deactivation_hook( __FILE__, 'apbct_deactivation' );
	
	// Async loading for JavaScript
	add_filter('script_loader_tag', 'apbct_add_async_attribute', 10, 3);
	
    // Redirect admin to plugin settings.
    if(!defined('WP_ALLOW_MULTISITE') || defined('WP_ALLOW_MULTISITE') && WP_ALLOW_MULTISITE == false)
    	add_action('admin_init', 'apbct_plugin_redirect');
       
    // After plugin loaded - to load locale as described in manual
    add_action('plugins_loaded', 'apbct_plugin_loaded' );
    
    if(	!empty($apbct->settings['use_ajax']) && 
    	stripos($_SERVER['REQUEST_URI'],'.xml')===false && 
    	stripos($_SERVER['REQUEST_URI'],'.xsl')===false)
    {
		add_action( 'wp_ajax_nopriv_ct_get_cookie', 'ct_get_cookie',1 );
		add_action( 'wp_ajax_ct_get_cookie', 'ct_get_cookie',1 );
	}
    	
	if($apbct->settings['show_link'] == 1)
		add_action('comment_form_after', 'ct_show_comment_link');

	if(is_admin() || is_network_admin()){
		require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-comments.php');
		require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-users.php');
	}
	
	// Admin panel actions
    if (is_admin() || is_network_admin()){
		
		require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-admin.php');
		require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-settings.php');
		
		if (!(defined( 'DOING_AJAX' ) && DOING_AJAX)){
			
			add_action('admin_init',            'apbct_admin__init', 1);
			add_action('admin_menu',            'apbct_settings__add_page');
			add_action('network_admin_menu',    'apbct_settings__add_page');
			add_action('admin_notices',         'apbct_admin__notice_message');
			add_action('network_admin_notices', 'apbct_admin__notice_message');
			
			//Show widget only if not IP license
			if(!$apbct->moderate_ip)
				add_action('wp_dashboard_setup', 'ct_dashboard_statistics_widget' );
		}
		
		if (defined( 'DOING_AJAX' ) && DOING_AJAX||isset($_POST['cma-action'])){
			
			// Feedback for comments
			if(isset($_POST['action']) && $_POST['action'] == 'ct_feedback_comment'){
				add_action( 'wp_ajax_nopriv_ct_feedback_comment', 'apbct_comment__send_feedback',1 );
				add_action( 'wp_ajax_ct_feedback_comment',        'apbct_comment__send_feedback',1 );
			}
			if(isset($_POST['action']) && $_POST['action'] == 'ct_feedback_user'){
				add_action( 'wp_ajax_nopriv_ct_feedback_user', 'apbct_user__send_feedback',1 );
				add_action( 'wp_ajax_ct_feedback_user',        'apbct_user__send_feedback',1 );
			}
			
			$cleantalk_hooked_actions = array();
			$cleantalk_ajax_actions_to_check = array();
			require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public.php');
			require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-ajax.php');
			
			// Check AJAX requests
				// if User is not logged in
				// if Unknown action or Known action with mandatory check
			if(	defined('LOGGED_IN_COOKIE') && !isset($_COOKIE[LOGGED_IN_COOKIE]) &&
				isset($_POST['action']) && (!in_array($_POST['action'], $cleantalk_hooked_actions) || in_array($_POST['action'], $cleantalk_ajax_actions_to_check))
			){
				ct_ajax_hook();			
			}
			
			//QAEngine Theme answers
			if (intval($apbct->settings['general_contact_forms_test']))
				add_filter('et_pre_insert_question', 'ct_ajax_hook', 1, 1); // Questions
				add_filter('et_pre_insert_answer',   'ct_ajax_hook', 1, 1); // Answers
			
            //
            // Some of plugins to register a users use AJAX context.
            //
            add_filter('registration_errors', 'ct_registration_errors', 1, 3);
			add_filter('registration_errors', 'ct_check_registration_erros', 999999, 3);
            add_action('user_register', 'ct_user_register');
			
		}
		
		require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public.php');
		
		//Bitrix24 contact form
		if (ct_is_user_enable()) {

			if ($apbct->settings['general_contact_forms_test'] == 1 &&
				!empty($_POST['your-phone']) &&
				!empty($_POST['your-email']) &&
				!empty($_POST['your-message'])
			){
				$ct_check_post_result=false;
				ct_contact_form_validate();
			}
		}

		add_action('admin_enqueue_scripts', 'apbct_admin__enqueue_scripts');

		// Sends feedback to the cloud about comments
		// add_action('wp_set_comment_status', 'ct_comment_send_feedback', 10, 2);

		// Sends feedback to the cloud about deleted users
		global $pagenow;
	    if($pagenow=='users.php')
	    	add_action('delete_user', 'apbct_user__delete__hook', 10, 2);
		
	    if($pagenow=='plugins.php' || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'],'plugins.php') !== false)){
			
	    	add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'apbct_admin__plugin_action_links', 10, 2);
			add_filter('network_admin_plugin_action_links_'.plugin_basename(__FILE__), 'apbct_admin__plugin_action_links', 10, 2);
			
	    	add_filter('plugin_row_meta', 'apbct_admin__register_plugin_links', 10, 2);
	    }
	
	// Public pages actions
    }else{
		
		require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public.php');
		
		add_action('wp_enqueue_scripts', 'ct_enqueue_scripts_public');
		
		// Init action.
		add_action('plugins_loaded', 'apbct_init', 1);
		
		// Comments
		add_filter('preprocess_comment', 'ct_preprocess_comment', 1, 1);     // param - comment data array
		add_filter('comment_text', 'ct_comment_text' );

		// Registrations
		add_action('register_form','ct_register_form');
		add_filter('registration_errors', 'ct_registration_errors', 1, 3);
		add_filter('registration_errors', 'ct_check_registration_erros', 999999, 3);
		add_action('user_register', 'ct_user_register');

		// Multisite registrations
		add_action('signup_extra_fields','ct_register_form');
		add_filter('wpmu_validate_user_signup', 'ct_registration_errors_wpmu', 10, 3);

		// Login form - for notifications only
		add_filter('login_message', 'ct_login_message');
		
		// Comments output hook
		add_filter('wp_list_comments_args', 'ct_wp_list_comments_args');
		
		// Ait-Themes fix
		if(isset($_GET['ait-action']) && $_GET['ait-action']=='register'){
			$tmp=$_POST['redirect_to'];
			unset($_POST['redirect_to']);
			ct_contact_form_validate();
			$_POST['redirect_to']=$tmp;
		}
    }
	
	// Short code for GDPR
	add_shortcode('cleantalk_gdpr_form', 'apbct_shrotcode_hadler__GDPR_public_notice__form');
	
}

/**
* Function preforms remote call
*/
function apbct_remote_call__perform()
{
	global $apbct;
	
/**
* Temporary disabled IP check because of false blocks
* @date 04.09.2018
*/
/*
	// Comparing with cleantalk's IP
	$spbc_remote_ip = CleantalkHelper::ip_get(array('real'), false);
	
	if(!empty($spbc_remote_ip)){
		
		$resolved = gethostbyaddr($spbc_remote_ip);
			
		if($resolved !== false){
			
			if(preg_match('/cleantalk\.org$/', $resolved) === 1 || $resolved === 'back'){
*/
				if(time() - $apbct->last_remote_call > APBCT_REMOTE_CALL_SLEEP){
					
					$apbct->data['last_remote_call'] = time();
					$apbct->saveData();
					
					if(strtolower($_GET['spbc_remote_call_token']) == strtolower(md5($apbct->api_key))){
						
						// Close renew banner
						if($_GET['spbc_remote_call_action'] == 'close_renew_banner'){
							$apbct->data['notice_trial'] = 0;
							$apbct->data['notice_renew'] = 0;
							$apbct->saveData();
							CleantalkCron::updateTask('check_account_status', 'ct_account_status_check',  86400);
							die('OK');
						// SFW update
						}elseif($_GET['spbc_remote_call_action'] == 'sfw_update'){
							$result = ct_sfw_update();
							die(empty($result['error']) ? 'OK' : 'FAIL '.json_encode(array('error' => $result['error_string'])));
						// SFW send logs
						}elseif($_GET['spbc_remote_call_action'] == 'sfw_send_logs'){
							$rc_result = ct_sfw_send_logs();
							die(empty($result['error']) ? 'OK' : 'FAIL '.json_encode(array('error' => $result['error_string'])));
						// Update plugin
						}elseif($_GET['spbc_remote_call_action'] == 'update_plugin'){
							add_action('template_redirect', 'apbct_update', 1);
						}else
							die('FAIL '.json_encode(array('error' => 'UNKNOWN_ACTION')));
					}else
						die('FAIL '.json_encode(array('error' => 'WRONG_TOKEN')));
				}else
					die('FAIL '.json_encode(array('error' => 'TOO_MANY_ATTEMPTS')));
/*
			}else
				die('FAIL '.json_encode(array('error' => 'WRONG_IP')));
		}else
			die('FAIL '.json_encode(array('error' => 'COULDNT_RESOLVE_IP')));
	}else
		die('FAIL '.json_encode(array('error' => 'COULDNT_RECONIZE_IP')));
*/
}
	
/**
* Function for SpamFireWall check
*/
function apbct_sfw__check()
{
	global $apbct, $cleantalk_url_exclusions;
	
	// Turn off the SpamFireWall if current url in the exceptions list and WordPress core pages
	 if (!empty($cleantalk_url_exclusions) && is_array($cleantalk_url_exclusions)) {
		$core_page_to_skip_check = array('/feed');
		foreach (array_merge($cleantalk_url_exclusions, $core_page_to_skip_check) as $v) {
			if (stripos($_SERVER['REQUEST_URI'], $v) !== false) {
				return;
			}
		} 
	}
	
	include_once(CLEANTALK_PLUGIN_DIR . "lib/CleantalkSFW_Base.php");
	include_once(CLEANTALK_PLUGIN_DIR . "lib/CleantalkSFW.php");
	
	$is_sfw_check = true;
	$sfw = new CleantalkSFW();
	$sfw->ip_array = (array)CleantalkSFW::ip_get(array('real'), true);
	
	foreach($sfw->ip_array as $ct_cur_ip){
		if(isset($_COOKIE['ct_sfw_pass_key']) && $_COOKIE['ct_sfw_pass_key'] == md5($ct_cur_ip.$apbct->api_key)){
			$is_sfw_check=false;
			if(isset($_COOKIE['ct_sfw_passed'])){
				$sfw->logs__update($ct_cur_ip, 'passed');
				$apbct->data['sfw_counter']['all']++;
				$apbct->saveData();
				if(!headers_sent())
					setcookie ('ct_sfw_passed', '0', 1, "/");
			}
		}else{
			$is_sfw_check=true;
		}
	}
	
	// Skip the check
	if(!empty($_GET['access'])){
		$spbc_settings = get_option('spbc_settings');
		$spbc_key = !empty($spbc_settings['spbc_key']) ? $spbc_settings['spbc_key'] : false;
		if($_GET['access'] === $apbct->api_key || ($spbc_key !== false && $_GET['access'] === $spbc_key)){
			$is_sfw_check = false;
			setcookie ('spbc_firewall_pass_key', md5($_SERVER['REMOTE_ADDR'].$spbc_key),             time()+1200, '/');
			setcookie ('ct_sfw_pass_key',        md5($_SERVER['REMOTE_ADDR'].$apbct->api_key), time()+1200, '/');
		}
		unset($spbc_settings, $spbc_key);
	}
	
	if($is_sfw_check){
		$sfw->ip_check();
		if($sfw->result){
			$sfw->logs__update($sfw->blocked_ip, 'blocked');
			$apbct->data['sfw_counter']['blocked']++;
			$apbct->saveData();
			$sfw->sfw_die($apbct->api_key);
		}else{
			if(!empty($apbct->settings['set_cookies']))
				setcookie ('ct_sfw_pass_key', md5($sfw->passed_ip.$apbct->api_key), 0, "/");
		}
	}
	unset($is_sfw_check, $sfw, $sfw_ip, $ct_cur_ip);
}

/**
 * On activation, set a time, frequency and name of an action hook to be scheduled.
 */
function apbct_activation() {
	
	global $wpdb;
	
	$wpdb->query("CREATE TABLE IF NOT EXISTS `".$wpdb->base_prefix."cleantalk_sfw` (
		`network` int(11) unsigned NOT NULL,
		`mask` int(11) unsigned NOT NULL,
		INDEX (  `network` ,  `mask` )
		) ENGINE = MYISAM ;"
	);
		
	$wpdb->query("CREATE TABLE IF NOT EXISTS `".$wpdb->base_prefix."cleantalk_sfw_logs` (
		`ip` VARCHAR(15) NOT NULL,
		`all_entries` INT NOT NULL,
		`blocked_entries` INT NOT NULL,
		`entries_timestamp` INT NOT NULL,
		PRIMARY KEY (`ip`)) 
		ENGINE = MYISAM;"
	);
	
	// Cron tasks
	CleantalkCron::addTask('check_account_status', 'ct_account_status_check',  3600,  time()+1800); // Checks account status
	CleantalkCron::addTask('delete_spam_comments', 'ct_delete_spam_comments',  3600,  time()+3500); // Formerly ct_hourly_event_hook()
	CleantalkCron::addTask('send_feedback',        'ct_send_feedback',         3600,  time()+3500); // Formerly ct_hourly_event_hook()
	CleantalkCron::addTask('sfw_update',           'ct_sfw_update',            86400, time()+43200);// SFW update
	CleantalkCron::addTask('send_sfw_logs',        'ct_sfw_send_logs',         3600,  time()+1800); // SFW send logs
	CleantalkCron::addTask('get_brief_data',       'cleantalk_get_brief_data', 86400, time()+3500); // Get data for dashboard widget
	CleantalkCron::addTask('send_connection_report','ct_mail_send_connection_report', 86400, time()+3500); // Send connection report to welcome@cleantalk.org
	
	// Additional options
	add_option('ct_plugin_do_activation_redirect', true);
	
	// Updating SFW
	ct_sfw_update();
}

/**
 * On deactivation, clear schedule.
 */
function apbct_deactivation() {
	
	global $wpdb;

	// Deleting SFW tables
	$wpdb->query("DROP TABLE IF EXISTS `".$wpdb->base_prefix."cleantalk_sfw`;");
	$wpdb->query("DROP TABLE IF EXISTS `".$wpdb->base_prefix."cleantalk_sfw_logs`;");

	// Deleting cron entries
	delete_option('cleantalk_cron'); 
}

/**
 * Redirects admin to plugin settings after activation. 
 */
function apbct_plugin_redirect()
{	
	if (get_option('ct_plugin_do_activation_redirect', false) && !isset($_GET['activate-multi'])){
		delete_option('ct_plugin_do_activation_redirect');
		wp_redirect("options-general.php?page=cleantalk");
	}
}

function ct_add_event($event_type)
{
	global $apbct, $cleantalk_executed;
	
    //
    // To migrate on the new version of ct_add_event(). 
    //
    switch ($event_type) {
        case '0': $event_type = 'no';break;
        case '1': $event_type = 'yes';break;
    }

	$current_hour = intval(date('G'));
	
	// Updating current hour
	if($current_hour!=$apbct->data['current_hour']){
		$apbct->data['current_hour'] = $current_hour;
		$apbct->data['array_accepted'][$current_hour] = 0;
		$apbct->data['array_blocked'][$current_hour]  = 0;
	}
	
	//Add 1 to counters
	if($event_type=='yes'){
		$apbct->data['array_accepted'][$current_hour]++;
		$apbct->data['all_time_counter']['accepted']++;
		$apbct->data['user_counter']['accepted']++;
	}
	if($event_type=='no'){
		$apbct->data['array_blocked'][$current_hour]++;
		$apbct->data['all_time_counter']['blocked']++;
		$apbct->data['user_counter']['blocked']++;
	}	
	
	$apbct->saveData();
	
	$cleantalk_executed=true;
}

/**
 * return new cookie value
 */
function ct_get_cookie()
{
	global $ct_checkjs_def;
	$ct_checkjs_key = ct_get_checkjs_value(true); 
	print $ct_checkjs_key;
	die();
}

function ct_show_comment_link(){
	
	print "<div style='font-size:10pt;'><a href='https://cleantalk.org/wordpress-anti-spam-plugin' target='_blank'>".__( 'WordPress spam', 'cleantalk' )."</a> ".__( 'blocked by', 'cleantalk' )." CleanTalk.</div>";
	
}

add_action( 'right_now_content_table_end', 'my_add_counts_to_dashboard' );

function ct_sfw_update(){
	
	global $apbct;
	
    if($apbct->settings['spam_firewall'] == 1){
		
		include_once(CLEANTALK_PLUGIN_DIR . "lib/CleantalkSFW_Base.php");
		include_once(CLEANTALK_PLUGIN_DIR . "lib/CleantalkSFW.php");
	
		$sfw = new CleantalkSFW();
		$result = $sfw->sfw_update($apbct->api_key);
		unset($sfw);
		return $result;
	}
	
	return array('error' => true, 'error_string' => 'SFW_DISABLED');
	
}

function ct_sfw_send_logs()
{
	global $apbct;
	
	if($apbct->settings['spam_firewall'] == 1){
		
		include_once(CLEANTALK_PLUGIN_DIR . "lib/CleantalkSFW_Base.php");
		include_once(CLEANTALK_PLUGIN_DIR . "lib/CleantalkSFW.php");
		
		$sfw = new CleantalkSFW();
		$result = $sfw->logs__send($apbct->api_key);
		unset($sfw);
		return $result;
		
	}
	
	return array('error' => true, 'error_string' => 'SFW_DISABLED');	
}

function apbct_update(){
	
	//Upgrade params
	$plugin      = 'cleantalk-spam-protect/cleantalk.php';
	$plugin_slug = 'cleantalk-spam-protect';
	$title 	     = __('Update Plugin');
	$nonce 	     = 'upgrade-plugin_' . $plugin;
	$url 	     = 'update.php?action=upgrade-plugin&plugin=' . urlencode( $plugin );
	
	$prev_version = APBCT_VERSION;
	
	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
	include_once( ABSPATH . 'wp-admin/includes/file.php' );
	include_once( ABSPATH . 'wp-admin/includes/misc.php' );
	
	include_once( CLEANTALK_PLUGIN_DIR . 'lib/CleantalkUpgrader.php' );
	include_once( CLEANTALK_PLUGIN_DIR . 'lib/CleantalkUpgraderSkin.php' );
	
	$upgrader = new CleantalkUpgrader( new CleantalkUpgraderSkin( compact('title', 'nonce', 'url', 'plugin') ) );
    $upgrader->upgrade($plugin);
	
	if($upgrader->apbct_result === 'OK'){
		
		$result = activate_plugins( $plugin );
		
		if(is_wp_error($result))
			die('FAIL '. json_encode(array('error' => 'COULD_NOT_ACTIVATE', 'wp_error' => $result->get_error_message())));
		if($result === false)
			die('FAIL '. json_encode(array('error' => 'COULD_NOT_ACTIVATE')));
		
		$httpResponseCode =  CleantalkHelper::http__request(get_option('siteurl'), array(), 'get_code');
		
		if( strpos($httpResponseCode, '200') === false ){
			
			// Rollback
			$rollback = new CleantalkUpgrader( new CleantalkUpgraderSkin( compact('title', 'nonce', 'url', 'plugin_slug', 'prev_version') ) );
			$rollback->rollback($plugin);
			
			$response = array(
				'error'           => 'BAD_HTTP_CODE',
				'http_code'       => $httpResponseCode,
				'output'          => substr(file_get_contents(get_option('siteurl')), 0, 900),
				'rollback_result' => $rollback->apbct_result,
			);
			
			die('FAIL '.json_encode($response));
		}
		
		$plugin_data = get_plugin_data(__FILE__);
		$apbct_agent = 'wordpress-'.str_replace('.', '', $plugin_data['Version']);
		ct_send_feedback('0:' . $apbct_agent);
		
		die('OK '.json_encode(array('agent' => $apbct_agent)));
		
	}else{
		die('FAIL '. json_encode(array('error' => $upgrader->apbct_result)));
	}	
}

function cleantalk_get_brief_data(){
	
    global $apbct;
	
	$apbct->data['brief_data'] = CleantalkAPI::method__get_antispam_report_breif($apbct->api_key);
	$apbct->saveData();
	
	return;
}

//Delete cookie for admin trial notice
function ct_wp_logout(){
	if(!headers_sent())
		setcookie('ct_trial_banner_closed', '', time()-3600);
}

/*
 * Set Cookies test for cookie test
 * Sets cookies with pararms timestamp && landing_timestamp && pervious_referer
 * Sets test cookie with all other cookies
 */
function apbct_cookie(){
	
	global $apbct;
	
	if(
		empty($apbct->settings['set_cookies']) ||   // Do not set cookies if option is disabled (for Varnish cache).
		!empty($apbct->flags__cookies_setuped)         // Cookies already set
	)
		return false;
	
	// Cookie names to validate
	$cookie_test_value = array(
		'cookies_names' => array(),
		'check_value' => $apbct->api_key,
	);
		
	// Submit time
	if(empty($_POST['ct_multipage_form'])){ // Do not start reset page timer if it is multipage form (Gravitiy forms))
		$apbct_timestamp = time();
		setcookie('apbct_timestamp', $apbct_timestamp, 0, '/');
		$cookie_test_value['cookies_names'][] = 'apbct_timestamp';
		$cookie_test_value['check_value'] .= $apbct_timestamp;
	}

	// Pervious referer
	if(!empty($_SERVER['HTTP_REFERER'])){
		setcookie('apbct_prev_referer', $_SERVER['HTTP_REFERER'], 0, '/');
		$cookie_test_value['cookies_names'][] = 'apbct_prev_referer';
		$cookie_test_value['check_value'] .= $_SERVER['HTTP_REFERER'];
	}
	
	// Landing time
	if(isset($_COOKIE['apbct_site_landing_ts'])){
		$site_landing_timestamp = $_COOKIE['apbct_site_landing_ts'];
	}else{
		$site_landing_timestamp = time();
		setcookie('apbct_site_landing_ts', $site_landing_timestamp, 0, '/');
	}
	$cookie_test_value['cookies_names'][] = 'apbct_site_landing_ts';
	$cookie_test_value['check_value'] .= $site_landing_timestamp;
	
	// Page hits
	$page_hits = isset($_COOKIE['apbct_page_hits']) && apbct_cookies_test() ? $_COOKIE['apbct_page_hits'] + 1 : 1;
	setcookie('apbct_page_hits', $page_hits, 0, '/');
	$cookie_test_value['cookies_names'][] = 'apbct_page_hits';
	$cookie_test_value['check_value'] .= $page_hits;
	
	// Cookies test
	$cookie_test_value['check_value'] = md5($cookie_test_value['check_value']);
	setcookie('apbct_cookies_test', json_encode($cookie_test_value), 0, '/');
	
	$apbct->flags__cookies_setuped = true;
	
}

/**
 * Cookies test for sender 
 * Also checks for valid timestamp in $_COOKIE['apbct_timestamp'] and other apbct_ COOKIES
 * @return null|0|1;
 */
function apbct_cookies_test()
{
	global $apbct;
	
	if(isset($_COOKIE['apbct_cookies_test'])){
		
		$cookie_test = json_decode(stripslashes($_COOKIE['apbct_cookies_test']), true);
		
		if(!is_array($cookie_test))
			return 0;
		
		$check_srting = $apbct->api_key;
		foreach($cookie_test['cookies_names'] as $cookie_name){
			$check_srting .= isset($_COOKIE[$cookie_name]) ? $_COOKIE[$cookie_name] : '';
		} unset($cokie_name);
		
		if($cookie_test['check_value'] == md5($check_srting)){
			return 1;
		}else{
			return 0;
		}
	}else{
		return null;
	}
}

function apbct_cookies__delete($cookie){
	if(isset($_COOKIE[$cookie]))
		setcookie($cookie, '', time()-3600);
}

function apbct_cookies__delete_all(){
	if(count($_COOKIE)){
		foreach($_COOKIE as $key => $val){
			if(preg_match("/apbct_|ct_/", $key)){
				setcookie($key, '', time()-3600);
			}       
		} unset($key, $val);
	}
	return false;
}

/**
 * Gets submit time
 * Uses Cookies with check via apbct_cookies_test()
 * @return null|int;
 */
function apbct_get_submit_time()
{	
	return apbct_cookies_test() == 1 ? time() - (int)$_COOKIE['apbct_timestamp'] : null;
}

function apbct_is_user_logged_in(){
	if(count($_COOKIE)){
		foreach($_COOKIE as $key => $val){
			if(preg_match("/wordpress_logged_in/", $key)){
				return true;
}
		} unset($key, $val);
}
	return false;
}

/*
 * Inner function - Account status check
 * Scheduled in 1800 seconds for default!
 */
function ct_account_status_check($api_key = null){
	
	global $apbct;
	
	$api_key = $api_key ? $api_key : $apbct->api_key;
	
	$result = CleantalkAPI::method__notice_paid_till($api_key);	
	
	if(empty($result['error'])){
		
		// Notices
		$apbct->data['notice_show']        = isset($result['show_notice'])             ? (int)$result['show_notice']             : 0;
		$apbct->data['notice_renew']       = isset($result['renew'])                   ? (int)$result['renew']                   : 0;
		$apbct->data['notice_trial']       = isset($result['trial'])                   ? (int)$result['trial']                   : 0;
		$apbct->data['notice_review']      = isset($result['show_review'])             ? (int)$result['show_review']             : 0;
		$apbct->data['notice_auto_update'] = isset($result['show_auto_update_notice']) ? (int)$result['show_auto_update_notice'] : 0;
		
		// Other
		$apbct->data['service_id']         = isset($result['service_id'])      ? (int)$result['service_id']      : 0;
		$apbct->data['valid']              = isset($result['valid'])           ? (int)$result['valid']           : 0;
		$apbct->data['moderate']           = isset($result['moderate'])        ? (int)$result['moderate']        : 0;
		$apbct->data['moderate_ip']        = isset($result['moderate_ip'])     ? (int)$result['moderate_ip']     : 0;
		$apbct->data['ip_license']         = isset($result['ip_license'])      ? (int)$result['ip_license']      : 0;
		$apbct->data['spam_count']         = isset($result['spam_count'])      ? (int)$result['spam_count']      : 0;
		$apbct->data['auto_update']        = isset($result['auto_update_app']) ? (int)$result['auto_update_app'] : 0;
		$apbct->data['user_token']         = isset($result['user_token'])      ? (string)$result['user_token']   : '';
		$apbct->data['license_trial']      = isset($result['license_trial'])   ? (int)$result['license_trial']   : 0;
		
		if($apbct->data['notice_show'] == 1 && $apbct->data['notice_trial'] == 1)
			CleantalkCron::updateTask('check_account_status', 'ct_account_status_check',  3600);
		
		if($apbct->data['notice_show'] == 1 && $apbct->data['notice_renew'] == 1)
			CleantalkCron::updateTask('check_account_status', 'ct_account_status_check',  1800);
		
		if($apbct->data['notice_show'] == 0)
			CleantalkCron::updateTask('check_account_status', 'ct_account_status_check',  86400);
		
		$apbct->saveData();
		$apbct->error_delete('account_check', 'save');
		
	}else{
		$apbct->error_add('account_check', $result);
	}
}

function ct_mail_send_connection_report() {
	
	global $apbct;
	
    if (($apbct->settings['send_connection_reports'] == 1 && $apbct->connection_reports['negative'] > 0) || !empty($_GET['ct_send_connection_report']))
    {
		$to  = "welcome@cleantalk.org" ; 
		$subject = "Connection report for ".$_SERVER['HTTP_HOST']; 
		$message = ' 
				<html> 
				    <head> 
				        <title></title> 
				    </head> 
				    <body> 
				        <p>From '.$apbct->connection_reports['since'].' to '.date('d M').' has been made '.($apbct->connection_reports['success']+$apbct->connection_reports['negative']).' calls, where '.$apbct->connection_reports['success'].' were success and '.$apbct->connection_reports['negative'].' were negative</p> 
				        <p>Negative report:</p>
				        <table>  <tr>
				    <td>&nbsp;</td>
				    <td><b>Date</b></td>
				    <td><b>Page URL</b></td>
				    <td><b>Library report</b></td>
				  </tr>
				  ';
		foreach ($apbct->connection_reports['negative_report'] as $key=>$report)
		{
			$message.= "<tr><td>".($key+1).".</td><td>".$report['date']."</td><td>".$report['page_url']."</td><td>".$report['lib_report']."</td></tr>";
		}  
		$message.='</table></body></html>'; 

		$headers  = "Content-type: text/html; charset=windows-1251 \r\n"; 
		$headers .= "From: ".get_option('admin_email'); 
		mail($to, $subject, $message, $headers);    	
    }
 
	$apbct->data['connection_reports']['success'] = 0;
	$apbct->data['connection_reports']['negative'] = 0;
	$apbct->data['connection_reports']['negative_report'] = array();
	$apbct->data['connection_reports']['since'] = date('d M');
	$apbct->saveData();
}

//* Write $message to the plugin's debug option
function apbct_log($message = 'empty', $func = null, $params = array())
{
	$debug = get_option( APBCT_DEBUG );
	
	$function = $func                         ? $func : '';
	$cron     = in_array('cron', $params)     ? true  : false;
	$data     = in_array('data', $params)     ? true  : false;
	$settings = in_array('settings', $params) ? true  : false;
	
	if(is_array($message) or is_object($message))
		$message = print_r($message, true);
	
	if($message)  $debug[date("H:i:s", microtime(true))."_ACTION_".strval(current_action())."_FUNCTION_".strval($func)]         = $message;
	if($cron)     $debug[date("H:i:s", microtime(true))."_ACTION_".strval(current_action())."_FUNCTION_".strval($func).'_cron'] = $apbct->cron;
	if($data)     $debug[date("H:i:s", microtime(true))."_ACTION_".strval(current_action())."_FUNCTION_".strval($func).'_data'] = $apbct->data;
	if($settings) $debug[date("H:i:s", microtime(true))."_ACTION_".strval(current_action())."_FUNCTION_".strval($func).'_settings'] = $apbct->settings;
	
	update_option(APBCT_DEBUG, $debug);
}