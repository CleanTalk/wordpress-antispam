<?php
/*
  Plugin Name: Anti-Spam by CleanTalk
  Plugin URI: https://cleantalk.org
  Description: Max power, all-in-one, no Captcha, premium anti-spam plugin. No comment spam, no registration spam, no contact spam, protects any WordPress forms.
  Version: 5.141.1
  Author: СleanTalk <welcome@cleantalk.org>
  Author URI: https://cleantalk.org
  Text Domain: cleantalk-spam-protect
  Domain Path: /i18n
*/

$cleantalk_executed = false;

// Getting version form main file (look above)
$plugin_info = get_file_data(__FILE__, array('Version' => 'Version', 'Name' => 'Plugin Name',));

// Common params
define('APBCT_NAME',             $plugin_info['Name']);
define('APBCT_VERSION',          $plugin_info['Version']);
define('APBCT_URL_PATH',         plugins_url('', __FILE__));  //HTTP path.   Plugin root folder without '/'.
define('APBCT_DIR_PATH',         dirname(__FILE__ ) . '/');          //System path. Plugin root folder with '/'.
define('APBCT_PLUGIN_BASE_NAME', plugin_basename(__FILE__));          //Plugin base name.
define('APBCT_CASERT_PATH',      file_exists(ABSPATH . WPINC . '/certificates/ca-bundle.crt') ? ABSPATH . WPINC . '/certificates/ca-bundle.crt' : ''); // SSL Serttificate path

// API params
define('APBCT_AGENT',        'wordpress-'.str_replace('.', '', $plugin_info['Version']));
define('APBCT_MODERATE_URL', 'http://moderate.cleantalk.org'); //Api URL

// Option names
define('APBCT_DATA',             'cleantalk_data');             //Option name with different plugin data.
define('APBCT_SETTINGS',         'cleantalk_settings');         //Option name with plugin settings.
define('APBCT_NETWORK_SETTINGS', 'cleantalk_network_settings'); //Option name with plugin network settings.
define('APBCT_DEBUG',            'cleantalk_debug');            //Option name with a debug data. Empty by default.

// Multisite
define('APBCT_WPMS', (is_multisite() ? true : false)); // WMPS is enabled

// Sessions
define('APBCT_SEESION__LIVE_TIME', 86400*2);
define('APBCT_SEESION__CHANCE_TO_CLEAN', 100);

// Different params
define('APBCT_REMOTE_CALL_SLEEP', 5); // Minimum time between remote call

if( !defined( 'CLEANTALK_PLUGIN_DIR' ) ){
	
    define('CLEANTALK_PLUGIN_DIR', dirname(__FILE__ ) . '/');
    
	// PHP functions patches
	require_once(CLEANTALK_PLUGIN_DIR . 'lib/cleantalk-php-patch.php');  // Pathces fpr different functions which not exists
	
	// Base classes
    require_once(CLEANTALK_PLUGIN_DIR . 'lib/autoloader.php');                // Autoloader
	require_once(CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk/Antispam/API.php');    // API
    require_once(CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk/Antispam/DB.php');     // Database driver
	require_once(CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk/Antispam/Helper.php'); // Helper
	include_once(CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk/Antispam/SFW.php');    // SpamFireWall
	include_once(CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk/Arr.php');             // Array functions
	
	// Child classes
	require_once(CLEANTALK_PLUGIN_DIR . 'lib/CleantalkAPI.php');    // API for Wordpress
    require_once(CLEANTALK_PLUGIN_DIR . 'lib/CleantalkDB.php');     // Database class for Wordpress
	require_once(CLEANTALK_PLUGIN_DIR . 'lib/CleantalkHelper.php'); // Helper for Worpdress
	include_once(CLEANTALK_PLUGIN_DIR . "lib/CleantalkSFW.php");    // SpamFireWall for Wordpress
	
	require_once(CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk.php');            // Main class for request
	require_once(CLEANTALK_PLUGIN_DIR . 'lib/CleantalkRequest.php');     // Holds request data
	require_once(CLEANTALK_PLUGIN_DIR . 'lib/CleantalkResponse.php');    // Holds response data
	
	require_once(CLEANTALK_PLUGIN_DIR . 'lib/CleantalkCron.php');        // Cron handling
    require_once(CLEANTALK_PLUGIN_DIR . 'lib/CleantalkState.php');       // State class
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-pluggable.php');  // Pluggable functions
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-common.php');
    
	// Global ArrayObject with settings and other global varables
	global $apbct;
	$apbct = new CleantalkState('cleantalk', array('settings', 'data', 'debug', 'errors', 'remote_calls', 'stats'));
	
	$apbct->base_name = 'cleantalk-spam-protect/cleantalk.php';
	
	$apbct->logo                 = plugin_dir_url(__FILE__) . 'inc/images/logo.png';
	$apbct->logo__small          = plugin_dir_url(__FILE__) . 'inc/images/logo_small.png';
	$apbct->logo__small__colored = plugin_dir_url(__FILE__) . 'inc/images/logo_color.png';
	
	// Customize CleantalkState
	// Account status
	
	$apbct->white_label      = $apbct->network_settings['white_label'];
	$apbct->allow_custom_key = $apbct->network_settings['allow_custom_key'];
	$apbct->plugin_name      = $apbct->network_settings['white_label__plugin_name'] ? $apbct->network_settings['white_label__plugin_name'] : APBCT_NAME;
	$apbct->api_key          = !APBCT_WPMS || $apbct->allow_custom_key || $apbct->white_label ? $apbct->settings['apikey'] : $apbct->network_settings['apikey'];
	$apbct->key_is_ok        = !APBCT_WPMS || $apbct->allow_custom_key || $apbct->white_label ? $apbct->data['key_is_ok']  : $apbct->network_data['key_is_ok'];
	$apbct->moderate         = !APBCT_WPMS || $apbct->allow_custom_key || $apbct->white_label ? $apbct->data['moderate']   : $apbct->network_data['moderate'];
	
	$apbct->data['user_counter']['since']       = isset($apbct->data['user_counter']['since'])       ? $apbct->data['user_counter']['since'] : date('d M');
	$apbct->data['connection_reports']['since'] = isset($apbct->data['connection_reports']['since']) ? $apbct->data['user_counter']['since'] : date('d M');
	
	$apbct->settings_link = is_network_admin() ? 'settings.php?page=cleantalk' : 'options-general.php?page=cleantalk';
	
	if(!$apbct->white_label){
		require_once( CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-widget.php');
	}
	
	// Disabling comments
	if($apbct->settings['disable_comments__all'] || $apbct->settings['disable_comments__posts'] || $apbct->settings['disable_comments__pages'] || $apbct->settings['disable_comments__media']){
		require_once(CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk/Templates/Singleton.php');
		require_once(CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk/DisableComments.php');
		\Cleantalk\DisableComments::getInstance();
	}
	
	// Passing JS key to frontend
	add_action('wp_ajax_apbct_js_keys__get',        'apbct_js_keys__get__ajax');
	add_action('wp_ajax_nopriv_apbct_js_keys__get', 'apbct_js_keys__get__ajax');
	
	// Database prefix
	global $wpdb;
	$apbct->db_prefix = !APBCT_WPMS || $apbct->allow_custom_key || $apbct->white_label ? $wpdb->prefix : $wpdb->base_prefix;
	$apbct->db_prefix = !$apbct->white_label && defined('CLEANTALK_ACCESS_KEY') ? $wpdb->base_prefix : $wpdb->prefix;
	// Database constants
	define('APBCT_TBL_FIREWALL_DATA', $apbct->db_prefix . 'cleantalk_sfw');      // Table with firewall data.
	define('APBCT_TBL_FIREWALL_LOG',  $apbct->db_prefix . 'cleantalk_sfw_logs'); // Table with firewall logs.
	define('APBCT_TBL_SESSIONS',      $apbct->db_prefix . 'cleantalk_sessions'); // Table with session data.
    define('APBCT_SPAMSCAN_LOGS',     $apbct->db_prefix . 'cleantalk_spamscan_logs'); // Table with session data.
	define('APBCT_SELECT_LIMIT',      5000); // Select limit for logs.
	define('APBCT_WRITE_LIMIT',       5000); // Write limit for firewall data.
	
	/** @todo HARDCODE FIX */
	if($apbct->plugin_version === '1.0.0')
		$apbct->plugin_version = '5.100';
	
	// Do update actions if version is changed
	apbct_update_actions();

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
	add_action('wp_logout', 'apbct__hook__wp_logout__delete_trial_notice_cookie');
	
	// Set cookie only for public pages and for non-AJAX requests
	if (!is_admin() && !apbct_is_ajax() && !defined('DOING_CRON')
		&& empty($_POST['ct_checkjs_register_form']) // Buddy press registration fix
		&& empty($_GET['ct_checkjs_search_default']) // Search form fix
		&& empty($_POST['action']) //bbPress
	){
		add_action('template_redirect','apbct_cookie', 2);
		add_action('template_redirect','apbct_store__urls', 2);
		if (empty($_POST) && empty($_GET)){
			apbct_cookie();
			apbct_store__urls();
		}
	}
		
	// Early checks
	
	// Iphorm
	if( isset( $_POST['iphorm_ajax'], $_POST['iphorm_id'], $_POST['iphorm_uid'] ) 	){
		require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public.php');
		require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-ajax.php');
		ct_ajax_hook();
	}
	
	// Facebook
	if ($apbct->settings['general_contact_forms_test'] == 1
		&& (!empty($_POST['action']) && $_POST['action'] == 'fb_intialize')
		&& !empty($_POST['FB_userdata'])
	){
		require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public.php');
		if (apbct_is_user_enable()){
			$ct_check_post_result=false;
			ct_registration_errors(null);
		}
		
	}

    $apbct_active_integrations = array(
        'ContactBank'          => array( 'hook' => 'contact_bank_frontend_ajax_call', 'ajax' => true ),
        'FluentForm'           => array( 'hook' => 'fluentform_before_insert_submission', 'ajax' => false ),
        'ElfsightContactForm'  => array( 'hook' => 'elfsight_contact_form_mail', 'ajax' => true ),
        'SimpleMembership'     => array( 'hook' => 'swpm_front_end_registration_complete_user_data', 'ajax' => false )
    );
    new  \Cleantalk\Antispam\Integrations( $apbct_active_integrations );
	
	// Ninja Forms. Making GET action to POST action
    if( apbct_is_in_uri( 'admin-ajax.php' ) && sizeof($_POST) > 0 && isset($_GET['action']) && $_GET['action']=='ninja_forms_ajax_submit' )
    	$_POST['action']='ninja_forms_ajax_submit';
    
	add_action( 'wp_ajax_nopriv_ninja_forms_ajax_submit', 'apbct_form__ninjaForms__testSpam', 1);
	add_action( 'wp_ajax_ninja_forms_ajax_submit',        'apbct_form__ninjaForms__testSpam', 1);
	add_action( 'wp_ajax_nopriv_nf_ajax_submit',          'apbct_form__ninjaForms__testSpam', 1);
	add_action( 'wp_ajax_nf_ajax_submit',                 'apbct_form__ninjaForms__testSpam', 1);
	add_action( 'ninja_forms_process',                    'apbct_form__ninjaForms__testSpam', 1); // Depricated ?

    // SeedProd Coming Soon Page Pro integration
    add_action( 'wp_ajax_seed_cspv5_subscribe_callback',          'apbct_form__seedprod_coming_soon__testSpam', 1 );
    add_action( 'wp_ajax_nopriv_seed_cspv5_subscribe_callback',   'apbct_form__seedprod_coming_soon__testSpam', 1 );
    add_action( 'wp_ajax_seed_cspv5_contactform_callback',        'apbct_form__seedprod_coming_soon__testSpam', 1 );
    add_action( 'wp_ajax_nopriv_seed_cspv5_contactform_callback', 'apbct_form__seedprod_coming_soon__testSpam', 1 );

    // The 7 theme contact form integration
    add_action( 'wp_ajax_nopriv_dt_send_mail', 'apbct_form__the7_contact_form', 1 );
    add_action( 'wp_ajax_dt_send_mail', 'apbct_form__the7_contact_form', 1 );

    // Elementor Pro page builder forms
    add_action( 'wp_ajax_elementor_pro_forms_send_form',        'apbct_form__elementor_pro__testSpam' );
    add_action( 'wp_ajax_nopriv_elementor_pro_forms_send_form', 'apbct_form__elementor_pro__testSpam' );

    // Custom register form (ticket_id=13668)
    add_action('website_neotrends_signup_fields_check',function( $username, $fields ){
        $ip = CleantalkHelper::ip__get( array('real'), false );
        $ct_result = ct_test_registration( $username, $fields['email'], $ip );
        if( $ct_result['allow'] == 0 ) {
            ct_die_extended( $ct_result['comment'] );
        }
    }, 1, 2);

    // INEVIO theme integration
    add_action( 'wp_ajax_contact_form_handler',        'apbct_form__inevio__testSpam', 1 );
    add_action( 'wp_ajax_nopriv_contact_form_handler', 'apbct_form__inevio__testSpam', 1 );

    // Enfold Theme contact form
	add_filter( 'avf_form_send', 'apbct_form__enfold_contact_form__test_spam', 4, 10 );

    //Hooks for updating/adding settings
    add_action ('added_option', 'apbct_after_options_added', 10, 3);
    add_action ('updated_option', 'apbct_after_options_updated', 10, 3);

	// Public actions
	if(!is_admin() && !apbct_is_ajax()){
		
		// Default search
		//add_filter( 'get_search_form',  'apbct_forms__search__addField' );
		add_filter( 'get_search_query', 'apbct_forms__search__testSpam' );
        add_action( 'wp_head', 'apbct_search_add_noindex', 1 );
		
		// Remote calls
		if(isset($_GET['spbc_remote_call_token'], $_GET['spbc_remote_call_action'], $_GET['plugin_name']) && in_array($_GET['plugin_name'], array('antispam','anti-spam', 'apbct'))){
			apbct_remote_call__perform();
		}
		
		// SpamFireWall check
		if( $apbct->plugin_version == APBCT_VERSION && // Do not call with first start
			$apbct->settings['spam_firewall'] == 1 &&
            apbct_is_get() &&
            ! apbct_wp_doing_cron()
		){
			apbct_sfw__check();
	    }
		
	}
		
		
    // Activation/deactivation functions must be in main plugin file.
    // http://codex.wordpress.org/Function_Reference/register_activation_hook
    register_activation_hook( __FILE__, 'apbct_activation' );
    register_deactivation_hook( __FILE__, 'apbct_deactivation' );
	
	// Hook for newly added blog
	add_action('wpmu_new_blog', 'apbct_activation__new_blog', 10, 6);
	
	// Async loading for JavaScript
	add_filter('script_loader_tag', 'apbct_add_async_attribute', 10, 3);
	
    // Redirect admin to plugin settings.
    if(!defined('WP_ALLOW_MULTISITE') || defined('WP_ALLOW_MULTISITE') && WP_ALLOW_MULTISITE == false)
    	add_action('admin_init', 'apbct_plugin_redirect');
	
	// Deleting SFW tables when deleting websites
	if(defined('WP_ALLOW_MULTISITE') && WP_ALLOW_MULTISITE === true)
		add_action( 'delete_blog', 'apbct_sfw__delete_tables', 10, 2 );
       
    // After plugin loaded - to load locale as described in manual
    add_action('plugins_loaded', 'apbct_plugin_loaded' );
    
    if(	!empty($apbct->settings['use_ajax']) && 
    	! apbct_is_in_uri( '.xml' ) &&
    	! apbct_is_in_uri( '.xsl' ) )
    {
		add_action( 'wp_ajax_nopriv_ct_get_cookie', 'ct_get_cookie',1 );
		add_action( 'wp_ajax_ct_get_cookie', 'ct_get_cookie',1 );
	}
	
	// Admin panel actions
    if (is_admin() || is_network_admin()){

        require_once( CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-find-spam.php' );
		require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-admin.php');
		require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-settings.php');
				
		if (!(defined( 'DOING_AJAX' ) && DOING_AJAX)){
			
			add_action('admin_enqueue_scripts', 'apbct_admin__enqueue_scripts');
			
			add_action('admin_init',            'apbct_admin__init', 1);
			add_action('admin_menu',            'apbct_settings_add_page');
			add_action('network_admin_menu',    'apbct_settings_add_page');
			add_action('admin_notices',         'apbct_admin__notice_message');
			add_action('network_admin_notices', 'apbct_admin__notice_message');
			
			//Show widget only if not IP license
			if(!$apbct->moderate_ip)
				add_action('wp_dashboard_setup', 'ct_dashboard_statistics_widget' );
		}
		
		if(apbct_is_ajax() || isset($_POST['cma-action'])){
			
			$cleantalk_hooked_actions = array();
			$cleantalk_ajax_actions_to_check = array();
			
			require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public.php');
			require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-ajax.php');
			
			// Feedback for comments
			if(isset($_POST['action']) && $_POST['action'] == 'ct_feedback_comment'){
				add_action( 'wp_ajax_nopriv_ct_feedback_comment', 'apbct_comment__send_feedback',1 );
				add_action( 'wp_ajax_ct_feedback_comment',        'apbct_comment__send_feedback',1 );
			}
			if(isset($_POST['action']) && $_POST['action'] == 'ct_feedback_user'){
				add_action( 'wp_ajax_nopriv_ct_feedback_user', 'apbct_user__send_feedback',1 );
				add_action( 'wp_ajax_ct_feedback_user',        'apbct_user__send_feedback',1 );
			}
			
			// Check AJAX requests
				// if User is not logged in
				// if Unknown action or Known action with mandatory check
			if(	( ! apbct_is_user_logged_in() || $apbct->settings['protect_logged_in'] == 1)  &&
				isset( $_POST['action'] ) &&
                ( ! in_array( $_POST['action'], $cleantalk_hooked_actions ) || in_array( $_POST['action'], $cleantalk_ajax_actions_to_check ) ) &&
                ! array_search( $_POST['action'], array_column( $apbct_active_integrations, 'hook' ) )
			){
				ct_ajax_hook();
			}
			
			//QAEngine Theme answers
			if (intval($apbct->settings['general_contact_forms_test']))
				add_filter('et_pre_insert_question', 'ct_ajax_hook', 1, 1); // Questions
				add_filter('et_pre_insert_answer',   'ct_ajax_hook', 1, 1); // Answers
			
			// Formidable
			add_filter( 'frm_entries_before_create', 'apbct_rorm__formidable__testSpam', 10, 2 );
			add_action( 'frm_entries_footer_scripts', 'apbct_rorm__formidable__footerScripts', 20, 2 );
			
            // Some of plugins to register a users use AJAX context.
            add_filter('registration_errors', 'ct_registration_errors', 1, 3);
			add_filter('registration_errors', 'ct_check_registration_erros', 999999, 3);
            add_action('user_register', 'apbct_user_register');
			
			if(class_exists('BuddyPress')){
				require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public.php');
				add_filter('bp_activity_is_spam_before_save', 'apbct_integration__buddyPres__activityWall', 999 ,2); /* ActivityWall */
				add_action('bp_locate_template', 'apbct_integration__buddyPres__getTemplateName', 10, 6); 
			}
			
		}
				
			require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public.php');
		//Bitrix24 contact form
		if ($apbct->settings['general_contact_forms_test'] == 1 &&
			!empty($_POST['your-phone']) &&
			!empty($_POST['your-email']) &&
			!empty($_POST['your-message'])
		){
			$ct_check_post_result=false;
			ct_contact_form_validate();
		}
		
		// Sends feedback to the cloud about comments
		// add_action('wp_set_comment_status', 'ct_comment_send_feedback', 10, 2);	
		
		// Sends feedback to the cloud about deleted users
		global $pagenow;
		if($pagenow=='users.php')
			add_action('delete_user', 'apbct_user__delete__hook', 10, 2);

		if( $pagenow=='plugins.php' || apbct_is_in_uri( 'plugins.php' ) ){

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
		add_filter('wp_die_handler', 'apbct_comment__sanitize_data__before_wp_die', 1); // Check comments after validation

		// Registrations
	    if(!isset($_POST['wp-submit'])){
		    add_action('login_form_register', 'apbct_cookie');
		    add_action('login_form_register', 'apbct_store__urls');
	    }
	    add_action('login_enqueue_scripts', 'apbct_login__scripts');
		add_action('register_form',       'ct_register_form');
		add_filter('registration_errors', 'ct_registration_errors', 1, 3);
		add_filter('registration_errors', 'ct_check_registration_erros', 999999, 3);
		add_action('user_register',       'apbct_user_register');

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
	if($apbct->settings['gdpr_enabled'])
		add_shortcode('cleantalk_gdpr_form', 'apbct_shrotcode_handler__GDPR_public_notice__form');
	
}

/**
* Hook for updating settings
*/
function apbct_after_options_updated($option, $old_value, $value) {
	global $apbct;

	if ($option == 'cleantalk_settings') {
		// SFW actions
		if($value['spam_firewall'] == 1){
			$result = ct_sfw_update(true, $value['apikey']);
			if( ! empty( $result['error'] ) )
				$apbct->error_add('sfw_update', $result['error']);
			$result = ct_sfw_send_logs(true, $value['apikey']);
			if( ! empty( $result['error'] ) )
				$apbct->error_add('sfw_send_logs', $result['error']);
		}
	}
}	

/**
* Hook for adding settings
*/
function apbct_after_options_added($option, $value) {
	global $apbct;
	
	if ($option == 'cleantalk_settings') {
		// SFW actions
		if($value['spam_firewall'] == 1){
			sleep(6);
			$result = ct_sfw_update(true, $value['apikey']);
			if( ! empty( $result['error'] ) )
				$apbct->error_add('sfw_update', $result['error']);
			$result = ct_sfw_send_logs(true, $value['apikey']);
			if( ! empty( $result['error'] ) )
				$apbct->error_add('sfw_send_logs', $result['error']);
		}
	}
}

/**
* Function preforms remote call
*/
function apbct_remote_call__perform()
{
	global $apbct;
	
	$remote_action = $_GET['spbc_remote_call_action'];

	if( isset( $apbct->remote_calls[$remote_action] ) ){
		if(time() - $apbct->remote_calls[$remote_action]['last_call'] > APBCT_REMOTE_CALL_SLEEP || ($remote_action == 'sfw_update' && isset($_GET['file_urls']))) {
			
			$apbct->remote_calls[$remote_action]['last_call'] = time();
			$apbct->save('remote_calls');

			if(strtolower($_GET['spbc_remote_call_token']) == strtolower(md5($apbct->api_key))){
				
				// Flag to let plugin know that Remote Call is running.
				$apbct->rc_running = true;
				
				switch ($remote_action) {
					
				// Close renew banner
					case 'close_renew_banner':
						$apbct->data['notice_trial'] = 0;
						$apbct->data['notice_renew'] = 0;
						$apbct->saveData();
						CleantalkCron::updateTask('check_account_status', 'ct_account_status_check',  86400);
						die('OK');	
						break;
					
				// SFW update
					case 'sfw_update':
						$result = ct_sfw_update(false, '', true);
						/**
						 * @todo CRUNCH
						 */
						if(is_string($result) && strpos($result, 'FAIL') !== false){
							$result = json_decode(substr($result, 5), true);
						}
						die(empty($result['error']) ? 'OK' : 'FAIL '.json_encode(array('error' => $result['error'])));
						break;
				
				// SFW send logs
					case 'sfw_send_logs':
						$result = ct_sfw_send_logs();
						die(empty($result['error']) ? 'OK' : 'FAIL '.json_encode(array('error' => $result['error'])));
						break;
					
				// Update plugin
					case 'update_plugin':
						add_action('wp', 'apbct_rc__update', 1);
						break;
					
				// Install plugin
					case 'install_plugin':
						add_action('wp', 'apbct_rc__install_plugin', 1);
						break;
					// Activate plugin
					case 'activate_plugin':
						$result = apbct_rc__activate_plugin($_GET['plugin']);
						die(empty($result['error'])
							? 'OK'
							: 'FAIL '.json_encode(array('error' => $result['error'])));
						break;
					
					// Insert API key
					case 'insert_auth_key':
						$result = apbct_rc__insert_auth_key($_GET['auth_key'], $_GET['plugin']);
						die(empty($result['error'])
							? 'OK'
							: 'FAIL '.json_encode(array('error' => $result['error'])));
						break;
					
					// Update settins
					case 'update_settings':
						$result = apbct_rc__update_settings($_GET);
						die(empty($result['error'])
							? 'OK'
							: 'FAIL '.json_encode(array('error' => $result['error'])));
						break;
					// Deactivate plugin
					case 'deactivate_plugin':
						add_action('plugins_loaded', 'apbct_rc__deactivate_plugin', 1);
						break;
					
					// Uninstall plugin
					case 'uninstall_plugin':
						add_action('plugins_loaded', 'apbct_rc__uninstall_plugin', 1);
						break;
					// No action found
					default:
						die('FAIL '.json_encode(array('error' => 'UNKNOWN_ACTION_2')));
						break;
				}
				
			}else
				die('FAIL '.json_encode(array('error' => 'WRONG_TOKEN')));
		}else
			die('FAIL '.json_encode(array('error' => 'TOO_MANY_ATTEMPTS')));
	}else
		die('FAIL '.json_encode(array('error' => 'UNKNOWN_ACTION')));
}
	
/**
* Function for SpamFireWall check
*/
function apbct_sfw__check()
{
	global $apbct, $spbc, $cleantalk_url_exclusions;
	
	// Turn off the SpamFireWall if current url in the exceptions list and WordPress core pages
	 if (!empty($cleantalk_url_exclusions) && is_array($cleantalk_url_exclusions)) {
		$core_page_to_skip_check = array('/feed');
		foreach (array_merge($cleantalk_url_exclusions, $core_page_to_skip_check) as $v) {
			if ( apbct_is_in_uri( $v ) ) {
				return;
			}
		} 
	}
	
	// Turn off the SpamFireWall if Remote Call is in progress
	if($apbct->rc_running || (!empty($spbc) && $spbc->rc_running))
		return;
	
	$is_sfw_check = true;
	$sfw = new CleantalkSFW();
	$sfw->ip_array = (array)$sfw->ip__get(array('real'), true);
	
	// Skip by cookie
	foreach($sfw->ip_array as $ct_cur_ip){
		if(isset($_COOKIE['ct_sfw_pass_key']) && $_COOKIE['ct_sfw_pass_key'] == md5($ct_cur_ip.$apbct->api_key)){
			$is_sfw_check=false;
			if(isset($_COOKIE['ct_sfw_passed'])){
				$sfw->logs__update($ct_cur_ip, 'passed');
				$apbct->data['sfw_counter']['all']++;
				$apbct->saveData();
				if(!headers_sent())
                    \Cleantalk\Antispam\Helper::apbct_cookie__set ('ct_sfw_passed', '0', time()+86400*3, '/', null, false, true, 'Lax' );
			}
			break;
		}else{
			$is_sfw_check = true;
		}
	}
	
	// Skip the check
	if(!empty($_GET['access'])){
		$spbc_settings = get_option('spbc_settings');
		$spbc_key = !empty($spbc_settings['spbc_key']) ? $spbc_settings['spbc_key'] : false;
		if($_GET['access'] === $apbct->api_key || ($spbc_key !== false && $_GET['access'] === $spbc_key)){
			$is_sfw_check = false;
            \Cleantalk\Antispam\Helper::apbct_cookie__set('spbc_firewall_pass_key', md5(apbct_get_server_variable( 'REMOTE_ADDR' ) . $spbc_key),       time()+1200, '/', '');
            \Cleantalk\Antispam\Helper::apbct_cookie__set('ct_sfw_pass_key',        md5(apbct_get_server_variable( 'REMOTE_ADDR' ) . $apbct->api_key), time()+1200, '/', null);
		}
		unset($spbc_settings, $spbc_key);
	}
	
	if($is_sfw_check){
		
		$sfw->ip_check();
		
		// Pass remote calls
		if($sfw->pass === false){
			if(isset($_GET['spbc_remote_call_token'], $_GET['spbc_remote_call_action'], $_GET['plugin_name'])){
				foreach($sfw->blocked_ips as $ip){
					$resolved = CleantalkHelper::ip__resolve($ip['ip']);
					if($resolved && preg_match('/cleantalk\.org/', $resolved) === 1 || $resolved === 'back'){
						$sfw->pass = true;
					}
				} unset($ip);
			}
		}
		
//		if($sfw->test){
//			$sfw->sfw_die($apbct->api_key, '', parse_url(get_option('siteurl'),PHP_URL_HOST), 'test');
//		}
		
		if($sfw->pass === false){
			foreach($sfw->blocked_ips as $ip){
				$sfw->logs__update($ip['ip'], 'blocked');
			}
			$apbct->data['sfw_counter']['blocked']++;
			$apbct->saveData();
			$sfw->sfw_die($apbct->api_key, '', parse_url(get_option('siteurl'),PHP_URL_HOST));
		}else{
			reset($sfw->passed_ips);
			if(!empty($apbct->settings['set_cookies']) && !headers_sent() && key($sfw->passed_ips))
                \Cleantalk\Antispam\Helper::apbct_cookie__set( 'ct_sfw_pass_key', md5( $sfw->passed_ips[ key( $sfw->passed_ips ) ]['ip'] . $apbct->api_key ), time() + 86400 * 30, '/', null, false );
		}
	}
	unset($is_sfw_check, $sfw, $sfw_ip, $ct_cur_ip);
}

/**
 * On activation, set a time, frequency and name of an action hook to be scheduled.
 */
function apbct_activation( $network = false ) {
	
	global $wpdb;
	
	// SFW data
	$sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_sfw` (
		`network` int(11) unsigned NOT NULL,
		`mask` int(11) unsigned NOT NULL,
		`status` TINYINT(1) NOT NULL DEFAULT 0,
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
		`name` VARCHAR(40) NOT NULL,
		`value` TEXT NULL DEFAULT NULL,
		`last_update` DATETIME NULL DEFAULT NULL,
		PRIMARY KEY (`name`(40), `id`(64)));';
	
	$sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_spamscan_logs` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
        `scan_type` varchar(11) NOT NULL,
        `start_time` datetime NOT NULL,
        `finish_time` datetime NOT NULL,
        `count_to_scan` int(11) DEFAULT NULL,
        `found_spam` int(11) DEFAULT NULL,
        `found_bad` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`));';
		
	if($network && !defined('CLEANTALK_ACCESS_KEY')){
		$initial_blog  = get_current_blog_id();
		$blogs = array_keys($wpdb->get_results('SELECT blog_id FROM '. $wpdb->blogs, OBJECT_K));
		foreach ($blogs as $blog) {
			switch_to_blog($blog);
			apbct_activation__create_tables($sqls);
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
	}else{
		
		// Cron tasks
		CleantalkCron::addTask('check_account_status',  'ct_account_status_check',        3600,  time()+1800); // Checks account status
		CleantalkCron::addTask('delete_spam_comments',  'ct_delete_spam_comments',        3600,  time()+3500); // Formerly ct_hourly_event_hook()
		CleantalkCron::addTask('send_feedback',         'ct_send_feedback',               3600,  time()+3500); // Formerly ct_hourly_event_hook()
		CleantalkCron::addTask('sfw_update',            'ct_sfw_update',                  86400, time()+43200);  // SFW update
		CleantalkCron::addTask('send_sfw_logs',         'ct_sfw_send_logs',               3600,  time()+1800); // SFW send logs
		CleantalkCron::addTask('get_brief_data',        'cleantalk_get_brief_data',       86400, time()+3500); // Get data for dashboard widget
		CleantalkCron::addTask('send_connection_report','ct_mail_send_connection_report', 86400, time()+3500); // Send connection report to welcome@cleantalk.org
		
		apbct_activation__create_tables($sqls);
		ct_sfw_update(); // Updating SFW
		ct_account_status_check(null, false);
	}
	
	// Additional options
	add_option('ct_plugin_do_activation_redirect', true);
}

function apbct_activation__create_tables($sqls) {
    global $wpdb;
	$wpdb->show_errors = false;
	foreach($sqls as $sql){
		$sql = sprintf($sql, $wpdb->prefix); // Adding current blog prefix
		$result = $wpdb->query($sql);
		if($result === false)
			$errors[] = "Failed.\nQuery: {$wpdb->last_query}\nError: {$wpdb->last_error}";
	}
	$wpdb->show_errors = true;
	
	// Logging errors
	if(!empty($errors))
		apbct_log($errors);
}

function apbct_activation__new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta) {
    if (apbct_is_plugin_active_for_network('cleantalk-spam-protect/cleantalk.php')){

		$settings = get_option('cleantalk_settings');

        switch_to_blog($blog_id);
		
		global $wpdb;
		
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
			`name` TEXT NOT NULL,
			`value` TEXT NULL DEFAULT NULL,
			`last_update` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`(64), `name`(64)));';
	
	    $sqls[] = 'CREATE TABLE IF NOT EXISTS `%scleantalk_spamscan_logs` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
        `scan_type` varchar(11) NOT NULL,
        `start_time` datetime NOT NULL,
        `finish_time` datetime NOT NULL,
        `count_to_scan` int(11) DEFAULT NULL,
        `found_spam` int(11) DEFAULT NULL,
        `found_bad` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`));';
		
		// Cron tasks
		CleantalkCron::addTask('check_account_status',  'ct_account_status_check',        3600,  time()+1800); // Checks account status
		CleantalkCron::addTask('delete_spam_comments',  'ct_delete_spam_comments',        3600,  time()+3500); // Formerly ct_hourly_event_hook()
		CleantalkCron::addTask('send_feedback',         'ct_send_feedback',               3600,  time()+3500); // Formerly ct_hourly_event_hook()
		CleantalkCron::addTask('sfw_update',            'ct_sfw_update',                  86400, time()+43200);  // SFW update
		CleantalkCron::addTask('send_sfw_logs',         'ct_sfw_send_logs',               3600,  time()+1800); // SFW send logs
		CleantalkCron::addTask('get_brief_data',        'cleantalk_get_brief_data',       86400, time()+3500); // Get data for dashboard widget
		CleantalkCron::addTask('send_connection_report','ct_mail_send_connection_report', 86400, time()+3500); // Send connection report to welcome@cleantalk.org
		apbct_activation__create_tables($sqls);
		ct_sfw_update(); // Updating SFW
		ct_account_status_check(null, false);

		if (isset($settings['use_settings_template_apply_for_new']) && $settings['use_settings_template_apply_for_new'] == 1) {
			update_option('cleantalk_settings', $settings);
		}
        restore_current_blog();
    }
}

/**
 * On deactivation, clear schedule.
 */
function apbct_deactivation( $network ) {
	
	global $apbct, $wpdb;
	
	// Deactivation for network
	if(is_multisite() && $network){
		
		$initial_blog  = get_current_blog_id();
		$blogs = array_keys($wpdb->get_results('SELECT blog_id FROM '. $wpdb->blogs, OBJECT_K));
		foreach ($blogs as $blog) {
			switch_to_blog($blog);
			apbct_deactivation__delete_blog_tables();
			delete_option('cleantalk_cron'); // Deleting cron entries
			
			if($apbct->settings['complete_deactivation']){
				apbct_deactivation__delete_all_options();
                apbct_deactivation__delete_meta();
				apbct_deactivation__delete_all_options__in_network();
			}
			
		}
		switch_to_blog($initial_blog);
		
	// Deactivation for blog
	}elseif(is_multisite()){
		
		apbct_deactivation__delete_common_tables();
		delete_option('cleantalk_cron'); // Deleting cron entries
		
		if($apbct->settings['complete_deactivation']) {
            apbct_deactivation__delete_all_options();
            apbct_deactivation__delete_meta();
        }
		
	// Deactivation on standalone blog
	}elseif(!is_multisite()){
		
		apbct_deactivation__delete_common_tables();
		delete_option('cleantalk_cron'); // Deleting cron entries
		
		if($apbct->settings['complete_deactivation']) {
			apbct_deactivation__delete_all_options();
			apbct_deactivation__delete_meta();
		}
	
	}
}

/**
 * Delete all cleantalk_* entries from _options table
 */
function apbct_deactivation__delete_all_options(){
	delete_option('cleantalk_settings');
	delete_option('cleantalk_data');
	delete_option('cleantalk_cron');
	delete_option('cleantalk_errors');
	delete_option('cleantalk_remote_calls');
	delete_option('cleantalk_server');
	delete_option('cleantalk_stats');
	delete_option('cleantalk_timelabel_reg');
	delete_option('cleantalk_debug');
}

/**
 * Delete all cleantalk_* entries from _sitemeta table
 */
function apbct_deactivation__delete_all_options__in_network(){
	delete_site_option('cleantalk_network_settings');
	delete_site_option('cleantalk_network_data');
}

function apbct_deactivation__delete_common_tables() {
	global $wpdb;
	$wpdb->query('DROP TABLE IF EXISTS `'. $wpdb->base_prefix.'cleantalk_sfw`;');           // Deleting SFW data
	$wpdb->query('DROP TABLE IF EXISTS `'. $wpdb->base_prefix.'cleantalk_sfw_logs`;');      // Deleting SFW logs
	$wpdb->query('DROP TABLE IF EXISTS `'. $wpdb->base_prefix.'cleantalk_sessions`;');      // Deleting session table
	$wpdb->query('DROP TABLE IF EXISTS `'. $wpdb->base_prefix.'cleantalk_spamscan_logs`;'); // Deleting user/comments scan result table
}

function apbct_deactivation__delete_blog_tables() {
	global $wpdb;
	$wpdb->query('DROP TABLE IF EXISTS `'. $wpdb->prefix.'cleantalk_sfw`;');                // Deleting SFW data
	$wpdb->query('DROP TABLE IF EXISTS `'. $wpdb->prefix.'cleantalk_sfw_logs`;');           // Deleting SFW logs
	$wpdb->query('DROP TABLE IF EXISTS `'. $wpdb->prefix.'cleantalk_sessions`;');           // Deleting session table
	$wpdb->query('DROP TABLE IF EXISTS `'. $wpdb->prefix.'cleantalk_spamscan_logs`;'); // Deleting user/comments scan result table
}

function apbct_deactivation__delete_meta(){
	global $wpdb;
	$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('ct_bad', 'ct_checked', 'ct_checked_now', 'ct_marked_as_spam', 'ct_hash');");
}

/**
 * Redirects admin to plugin settings after activation. 
 */
function apbct_plugin_redirect()
{
	global $apbct;
	if (get_option('ct_plugin_do_activation_redirect', false) && !isset($_GET['activate-multi'])){
		delete_option('ct_plugin_do_activation_redirect');
		wp_redirect($apbct->settings_link);
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
	$ct_checkjs_key = ct_get_checkjs_value();
	print $ct_checkjs_key;
	die();
}

function ct_sfw_update($manual = false, $api_key = '', $immediate = false){
	
	global $apbct;

	$api_key = !empty($api_key) ? $api_key : $apbct->api_key;

    if($manual || ( $apbct->settings['spam_firewall'] == 1 && !empty($api_key) ) ) {
		
		$sfw = new CleantalkSFW();

	    $file_urls = isset($_GET['file_urls']) ? urldecode( $_GET['file_urls'] ) : null;
	    $file_urls = isset($file_urls) ? explode(',', $file_urls) : null;

		if( ! $file_urls ){

			//Reset previous entries count
			$apbct->stats['sfw']['entries'] = 0;
			$apbct->stats['sfw']['update_in_process'] = true;
			$apbct->save('stats');

            $result = $sfw->sfw_update($api_key, null, $immediate);
			
			return ! empty( $result['error'] )
				? $result
				: true;
			
		}elseif( is_array( $file_urls ) && count( $file_urls ) ){

			$result = $sfw->sfw_update($api_key, $file_urls[0], $immediate);
			
			if( empty( $result['error'] ) ){

				array_shift($file_urls);

				//Increment sfw entries
				$apbct->stats['sfw']['entries'] += $result;
				$apbct->save('stats');

				if (count($file_urls)) {
					CleantalkHelper::http__request(
						get_option('siteurl'),
						array(
							'spbc_remote_call_token'  => md5($api_key),
							'spbc_remote_call_action' => 'sfw_update',
							'plugin_name'             => 'apbct',
							'file_urls'               => implode(',', $file_urls),
						),
						array('get', 'async')
					);
				} else {
					//Files array is empty update sfw time
					$apbct->stats['sfw']['last_update_time'] = time();
					$apbct->stats['sfw']['update_in_process'] = false;
					$apbct->save('stats');

					return $result;
				}
			}else
				return $result;
		}else
			return array('error' => 'SFW_UPDATE WRONG_FILE_URLS');
	}
	
	return array('error' => 'SFW_DISABLED');
}

function ct_sfw_send_logs($manual = false, $api_key = '')
{
	global $apbct;

	$api_key = !empty($apbct->api_key) ? $apbct->api_key : $api_key;

	if($manual || ( $apbct->settings['spam_firewall'] == 1 && !empty($api_key) )) {
		
		$sfw = new CleantalkSFW();
		$result = $sfw->logs__send($api_key);
		
		if(empty($result['error'])){
			$apbct->stats['sfw']['last_send_time'] = time();
			$apbct->stats['sfw']['last_send_amount'] = $result['rows'];
			$apbct->save('stats');
		}

		return $result;
		
	}
	
	return array('error' => 'SFW_DISABLED');
}

/**
 * Wrapper for Cleantalk's remote calls
 *
 * @param string $action            What you want to do?
 * @param array  $additional_params Additional GET parameters for RC
 * @param string $presets           Presets for CleantalkHelper::http__request(). 'async' maybe?
 * @param string $plugin_name       Plugin name 'antispam' by default
 * @param string $call_token        RC securirty token
 * @param string $url               Current site URL by default
 *
 * @return array|bool
 */
function apbct_rc__send($action, $additional_params = array(), $presets = 'get', $plugin_name = 'antispam', $call_token = '', $url = ''){
	
	global $apbct;
	
	$default_params = array(
		'plugin_name'             => $plugin_name,
		'spbc_remote_call_token'  => $call_token ? $call_token : md5($apbct->api_key),
		'spbc_remote_call_action' => $action,
	);
	
	$params = array_merge($additional_params, $default_params);
	
	return apbct_rc__parse_result(
		CleantalkHelper::http__request(
			$url ? $url : get_option('siteurl'),
			$params,
			$presets
		)
	);
}

/**
 * Parse different types of remote call results
 *
 * @param array|string $rc_result
 * string - 'FAIL {"some":"result}'
 * string - 'OK {"some":"result}'
 *
 * @return array|string
 */
function apbct_rc__parse_result($rc_result){
	if(is_string($rc_result)){
		$rc_result = preg_replace('/^(OK\s?|FAIL\s?)(.*)/', '$2', $rc_result, 1);
		$rc_result = json_decode($rc_result, true);
		$rc_result = $rc_result
			? $rc_result
			: array('error' => 'FAIL_TO_PARSE_RC_RESULT');
	}
	return $rc_result;
}

/**
 * Install plugin from wordpress catalog
 *
 * @param WP     $wp
 * @param string $plugin_slug
 */
function apbct_rc__install_plugin($wp = null, $plugin = null){
	global $wp_version;

	$plugin = $plugin ? $plugin : (isset($_GET['plugin']) ? $_GET['plugin'] : null);
	
	if($plugin){
		
		if(preg_match('/[a-zA-Z-\d]+[\/\\][a-zA-Z-\d]+\.php/', $plugin)){
			
			$plugin_slug = preg_replace('@([a-zA-Z-\d]+)[\\\/].*@', '$1', $plugin);
			
			if($plugin_slug){
				
				require_once(ABSPATH.'wp-admin/includes/plugin-install.php');
				$result = plugins_api(
					'plugin_information',
					array(
						'slug' => $plugin_slug,
						'fileds' => array('version' => true, 'download_link' => true,),
					)
				);
				
				if(!is_wp_error($result)){
					
					require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
					include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
					include_once( ABSPATH . 'wp-admin/includes/file.php' );
					include_once( ABSPATH . 'wp-admin/includes/misc.php' );
					include_once( CLEANTALK_PLUGIN_DIR . 'lib/CleantalkUpgrader.php' );

					if (version_compare(PHP_VERSION, '5.6.0') >= 0 && version_compare($wp_version, '5.3') >= 0) {
                        include_once( CLEANTALK_PLUGIN_DIR . 'lib/CleantalkUpgraderSkin.php' );
                        $installer= new CleantalkUpgrader( new CleantalkUpgraderSkin() );
                    } else {
                        include_once( CLEANTALK_PLUGIN_DIR . 'lib/CleantalkUpgraderSkin_Deprecated.php' );
                        $installer= new CleantalkUpgrader( new CleantalkUpgraderSkin_Deprecated() );
                    }

					$installer->install($result->download_link);
					
					if($installer->apbct_result === 'OK'){
						die('OK');
						
					}else
						die('FAIL '. json_encode(array('error' => $installer->apbct_result)));
				}else
					die('FAIL '. json_encode(array('error' => 'FAIL_TO_GET_LATEST_VERSION', 'details' => $result->get_error_message(),)));
			}else
				die('FAIL '. json_encode(array('error' => 'PLUGIN_SLUG_INCORRECT')));
		}else
			die('FAIL '. json_encode(array('error' => 'PLUGIN_NAME_IS_INCORRECT')));
	}else
		die('FAIL '. json_encode(array('error' => 'PLUGIN_NAME_IS_UNSET')));
}

function apbct_rc__activate_plugin($plugin){
	
	$plugin = $plugin ? $plugin : (isset($_GET['plugin']) ? $_GET['plugin'] : null);
	
	if($plugin){
		
		if(preg_match('@[a-zA-Z-\d]+[\\\/][a-zA-Z-\d]+\.php@', $plugin)){
			
			require_once (ABSPATH .'/wp-admin/includes/plugin.php');
			
			$result = activate_plugins($plugin);
			
			if($result && !is_wp_error($result)){
				return array('success' => true);
			}else
				return array('error' => 'FAIL_TO_ACTIVATE', 'details' => (is_wp_error($result) ? ' '.$result->get_error_message() : ''));
		}else
			return array('error' => 'PLUGIN_NAME_IS_INCORRECT');
	}else
		return array('error' => 'PLUGIN_NAME_IS_UNSET');
}

/**
 * Uninstall plugin from wordpress catalog
 *
 * @param null $plugin_name
 */
function apbct_rc__deactivate_plugin($plugin = null){
	
	global $apbct;
	
	$plugin = $plugin ? $plugin : (isset($_GET['plugin']) ? $_GET['plugin'] : null);
	
	if($plugin){
		
		// Switching complete deactivation for security
		if($plugin == 'security-malware-firewall/security-malware-firewall.php' && !empty($_GET['complete_deactivation'])){
			$spbc_settings = get_option('spbc_settings');
			$spbc_settings['complete_deactivation'] = intval($_GET['complete_deactivation']);
			update_option('spbc_settings', $spbc_settings);
		}
		
		require_once (ABSPATH .'/wp-admin/includes/plugin.php');
		
		if(is_plugin_active( $plugin )){
			// Hook to set flag if the plugin is deactivated
			add_action( 'deactivate_'.$plugin, 'apbct_rc__uninstall_plugin__check_deactivate' );
			deactivate_plugins($plugin, false, is_multisite() ? true : false);
		}else{
			$apbct->plugin_deactivated = true;
		}
		
		// Hook to set flag if the plugin is deactivated
		add_action( 'deactivate_'.$plugin, 'apbct_rc__uninstall_plugin__check_deactivate' );
		deactivate_plugins($plugin, false, is_multisite() ? true : false);
		
		if($apbct->plugin_deactivated){
			die('OK');
		}else
			die('FAIL '. json_encode(array('error' => 'PLUGIN_STILL_ACTIVE')));
	}else
		die('FAIL '. json_encode(array('error' => 'PLUGIN_NAME_IS_UNSET')));
}


/**
 * Uninstall plugin from wordpress. Delete files.
 *
 * @param null $plugin
 */
function apbct_rc__uninstall_plugin($plugin = null){
	
	global $apbct;
	
	$plugin = $plugin ? $plugin : (isset($_GET['plugin']) ? $_GET['plugin'] : null);
	
	if($plugin){
		
		// Switching complete deactivation for security
		if($plugin == 'security-malware-firewall/security-malware-firewall.php' && !empty($_GET['complete_deactivation'])){
			$spbc_settings = get_option('spbc_settings');
			$spbc_settings['complete_deactivation'] = intval($_GET['complete_deactivation']);
			update_option('spbc_settings', $spbc_settings);
		}
		
		require_once (ABSPATH .'/wp-admin/includes/plugin.php');
		
		if(is_plugin_active( $plugin )){
			// Hook to set flag if the plugin is deactivated
			add_action( 'deactivate_'.$plugin, 'apbct_rc__uninstall_plugin__check_deactivate' );
			deactivate_plugins($plugin, false, is_multisite() ? true : false);
		}else{
			$apbct->plugin_deactivated = true;
		}
		
		if($apbct->plugin_deactivated){
			
			require_once (ABSPATH .'/wp-admin/includes/file.php');
			
			$result = delete_plugins(array($plugin));
			
			if($result && !is_wp_error($result)){
				die('OK');
			}else
				die('FAIL '. json_encode(array('error' => 'PLUGIN_STILL_EXISTS', 'details' => (is_wp_error($result) ? ' '.$result->get_error_message() : ''))));
		}else
			die('FAIL '. json_encode(array('error' => 'PLUGIN_STILL_ACTIVE')));
	}else
		die('FAIL '. json_encode(array('error' => 'PLUGIN_NAME_IS_UNSET')));
}

function apbct_rc__uninstall_plugin__check_deactivate(){
	global $apbct;
	$apbct->plugin_deactivated = true;
}

function apbct_rc__update(){
	global $wp_version;

	//Upgrade params
	$plugin      = 'cleantalk-spam-protect/cleantalk.php';
	$plugin_slug = 'cleantalk-spam-protect';
	$title 	     = __('Update Plugin');
	$nonce 	     = 'upgrade-plugin_' . $plugin;
	$url 	     = 'update.php?action=upgrade-plugin&plugin=' . urlencode( $plugin );
    $activate_for_network = false;
    if( APBCT_WPMS && is_main_site() && array_key_exists( $plugin, get_site_option( 'active_sitewide_plugins' ) ) ) {
        $activate_for_network = true;
    }
	
	$prev_version = APBCT_VERSION;
	
	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
	include_once( ABSPATH . 'wp-admin/includes/file.php' );
	include_once( ABSPATH . 'wp-admin/includes/misc.php' );
	
	include_once( CLEANTALK_PLUGIN_DIR . 'lib/CleantalkUpgrader.php' );
	
	apbct_maintance_mode__enable( 30 );
	
	if (version_compare(PHP_VERSION, '5.6.0') >= 0 && version_compare($wp_version, '5.3') >= 0){
		include_once( CLEANTALK_PLUGIN_DIR . 'lib/CleantalkUpgraderSkin.php' );
		$upgrader = new CleantalkUpgrader( new CleantalkUpgraderSkin( compact('title', 'nonce', 'url', 'plugin') ) );
	}else{
		include_once( CLEANTALK_PLUGIN_DIR . 'lib/CleantalkUpgraderSkin_Deprecated.php' );
		$upgrader = new CleantalkUpgrader( new CleantalkUpgraderSkin_Deprecated( compact('title', 'nonce', 'url', 'plugin') ) );
	}
	
    $upgrader->upgrade($plugin);
	
	apbct_maintance_mode__disable();
	
	$result = activate_plugins( $plugin, '', $activate_for_network );
	
	// Changing response UP_TO_DATE to OK
	if($upgrader->apbct_result === 'UP_TO_DATE')
		$upgrader->apbct_result = 'OK';
	
	if($upgrader->apbct_result === 'OK'){
		
		if(is_wp_error($result)){
			die('FAIL '. json_encode(array('error' => 'COULD_NOT_ACTIVATE', 'wp_error' => $result->get_error_message())));
		}
		
		$httpResponseCode =  CleantalkHelper::http__request(get_option('siteurl'), array(), 'get_code');
		
		if( strpos($httpResponseCode, '200') === false ){
			
			apbct_maintance_mode__enable( 30 );
			
			// Rollback
			if (version_compare(PHP_VERSION, '5.6.0') >= 0 && version_compare($wp_version, '5.3') >= 0)
				$rollback = new CleantalkUpgrader( new CleantalkUpgraderSkin( compact('title', 'nonce', 'url', 'plugin_slug', 'prev_version') ) );
			else
				$rollback = new CleantalkUpgrader( new CleantalkUpgraderSkin_Deprecated( compact('title', 'nonce', 'url', 'plugin_slug', 'prev_version') ) );
			$rollback->rollback($plugin);
			
			apbct_maintance_mode__disable();
			
			// @todo add execution time
			
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

function apbct_rc__update_settings($source) {
    
	global $apbct;
	
	foreach($apbct->def_settings as $setting => $def_value){
		if(array_key_exists($setting, $source)){
			$var = $source[$setting];
			$type = gettype($def_value);
			settype($var, $type);
			if($type == 'string')
				$var = preg_replace(array('/=/', '/`/'), '', $var);
			$apbct->settings[$setting] = $var;
		}
	}
	
	$apbct->save('settings');
	
	return true;
}

function apbct_rc__insert_auth_key($key, $plugin){
	
	global $apbct;
	
	if($plugin === 'security-malware-firewall/security-malware-firewall.php'){
		
		require_once (ABSPATH .'/wp-admin/includes/plugin.php');
		
		if(is_plugin_active( $plugin )){
			
			$key = trim($key);
			
			if($key && preg_match('/^[a-z\d]{3,15}$/', $key)){
				
				$result = CleantalkAPI::method__notice_paid_till(
					$key,
					preg_replace('/http[s]?:\/\//', '', get_option('siteurl'), 1), // Site URL
					'security'
				);
				
				if( empty( $result['error'] ) ) {
					
					if( $result['valid'] ){
						
						// Set account params
						$data = get_option('spbc_data', array());
						$data['user_token']       = $result['user_token'];
						$data['notice_show']      = $result['show_notice'];
						$data['notice_renew']     = $result['renew'];
						$data['notice_trial']     = $result['trial'];
						$data['auto_update_app']  = isset($result['show_auto_update_notice']) ? $result['show_auto_update_notice'] : 0;
						$data['service_id']       = $result['service_id'];
						$data['moderate']         = $result['moderate'];
						$data['auto_update_app '] = isset($result['auto_update_app']) ? $result['auto_update_app'] : 0;
						$data['license_trial']    = isset($result['license_trial']) ? $result['license_trial'] : 0;
						$data['account_name_ob']  = isset($result['account_name_ob']) ? $result['account_name_ob'] : '';
						$data['key_is_ok']        = true;
						update_option('spbc_data', $data);
						
						// Set key
						$settings = get_option('spbc_settings', array());
						$settings['spbc_key'] = $key;
						update_option('spbc_settings', $settings);
						
						return 'OK';
					}else
						return array('error' => 'KEY_IS_NOT_VALID');
				}else
					return array('error' => $result);
			}else
				return array('error' => 'KEY_IS_NOT_CORRECT');
		}else
			return array('error' => 'PLUGIN_IS_NOT_ACTIVE_OR_NOT_INSTALLED');
	}else
		return array('error' => 'PLUGIN_SLUG_INCORRECT');
}

/**
 * Putting Wordpress to maintenance mode.
 * For given duration in seconds
 *
 * @param $duration
 *
 * @return bool
 */
function apbct_maintance_mode__enable( $duration ) {
	apbct_maintance_mode__disable();
	$content = "<?php\n\n"
	           . '$upgrading = ' . (time() - ( 60 * 10 ) + $duration) . ';';
	
	return (bool)file_put_contents( ABSPATH . '.maintenance', $content );
}

/**
 * Disabling maintenance mode by deleting .maintenance file.
 *
 * @return void
 */
function apbct_maintance_mode__disable() {
	$maintenance_file = ABSPATH . '.maintenance';
	if ( file_exists( $maintenance_file ) ) {
		unlink( $maintenance_file );
	}
}

function cleantalk_get_brief_data(){
	
    global $apbct;
	
	$apbct->data['brief_data'] = CleantalkAPI::method__get_antispam_report_breif($apbct->api_key);
	$apbct->saveData();
	
	return;
}

//Delete cookie for admin trial notice
function apbct__hook__wp_logout__delete_trial_notice_cookie(){
	if(!headers_sent())
		setcookie('ct_trial_banner_closed', '', time()-3600);
}

function apbct_alt_session__id__get(){
	$id = CleantalkHelper::ip__get(array('real'))
		 .apbct_get_server_variable( 'HTTP_USER_AGENT' )
		 .apbct_get_server_variable( 'HTTP_ACCEPT_LANGUAGE' );
	return hash('sha256', $id);
}

function apbct_alt_sessions__remove_old(){
	if(rand(0, 1000) < APBCT_SEESION__CHANCE_TO_CLEAN){
		global $wpdb;
		$wpdb->query(
			'DELETE
				FROM `'. APBCT_TBL_SESSIONS .'`
				WHERE last_update < NOW() - INTERVAL '. APBCT_SEESION__LIVE_TIME .' SECOND
				LIMIT 100000;'
		);
	}
}

function apbct_alt_session__save($name, $value){
	
	global $wpdb;
	
	$session_id = apbct_alt_session__id__get();
	
	$wpdb->query(
		$wpdb->prepare(
			'INSERT INTO '. APBCT_TBL_SESSIONS .'
				(id, name, value, last_update)
				VALUES (%s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE
				value = %s,
				last_update = %s',
			$session_id, $name, $value, date('Y-m-d H:i:s'), $value, date('Y-m-d H:i:s')
		)
	);
	
}

function apbct_alt_session__get($name){
	global $wpdb;
    $session_id = apbct_alt_session__id__get();
	$result = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT value 
				FROM `'. APBCT_TBL_SESSIONS .'`
				WHERE id = %s AND name = %s;',
			$session_id, $name
		),
		OBJECT
	);
	
	$result = isset($result->value)
		? strpos($result->value, '{') === 0
			? (array)json_decode($result->value, true) // JSON
			: $result->value
		: false;
		
	return $result ? $result : null;
}

function apbct_store__urls(){
	
    global $apbct;
	
	if($apbct->settings['store_urls'] && empty($apbct->flags__url_stored) && !headers_sent()){
		
		// URLs HISTORY
		// Get current url
		$current_url = apbct_get_server_variable( 'HTTP_HOST' ) . apbct_get_server_variable( 'REQUEST_URI' );

		$current_url = $current_url ? substr($current_url, 0,256) : 'UNKNOWN';
		
		// Get already stored URLs
		$urls = $apbct->settings['store_urls__sessions']
			? (array)apbct_alt_session__get('apbct_urls')
			: (array)json_decode(filter_input(INPUT_COOKIE, 'apbct_urls'), true);
		
		$urls[$current_url][] = time();
		
		// Rotating. Saving only latest 10
		$urls[$current_url] = count($urls[$current_url]) > 10 ? array_slice($urls[$current_url], 1, 10) : $urls[$current_url];
		$urls               = count($urls) > 10               ? array_slice($urls, 1, 10)               : $urls;
		
		// Saving
		$apbct->settings['store_urls__sessions']
			? apbct_alt_session__save('apbct_urls', json_encode($urls))
			: \Cleantalk\Antispam\Helper::apbct_cookie__set('apbct_urls', json_encode($urls), time()+86400*3, '/', parse_url(get_option('siteurl'),PHP_URL_HOST), false, true, 'Lax');
		
		// REFERER
		// Get current fererer
		$new_site_referer = apbct_get_server_variable( 'HTTP_REFERER' );
		$new_site_referer = $new_site_referer ? $new_site_referer : 'UNKNOWN';
		
		// Get already stored referer
		$site_referer = $apbct->settings['store_urls__sessions']
			? apbct_alt_session__get('apbct_site_referer')
			: filter_input(INPUT_COOKIE, 'apbct_site_referer');
		
		// Save if empty
		if( !$site_referer || parse_url($new_site_referer, PHP_URL_HOST) !== apbct_get_server_variable( 'HTTP_HOST' ) ){
			
			$apbct->settings['store_urls__sessions']
				? apbct_alt_session__save('apbct_site_referer', $new_site_referer)
				: \Cleantalk\Antispam\Helper::apbct_cookie__set('apbct_site_referer', $new_site_referer, time()+86400*3, '/', parse_url(get_option('siteurl'),PHP_URL_HOST), false, true, 'Lax');
		}
		
		$apbct->flags__url_stored = true;
		
	}
}

/**
 * Universal method to adding cookies.
 * Use \Cleantalk\Antispam\Helper::apbct_cookie__set() instead.
 * @deprecated
 */
function apbct_cookie__set($name, $value = '', $expires = 0, $path = '', $domain = null, $secure = false, $httponly = false, $samesite = 'Lax' ){
	
	// For PHP 7.3+ and above
	if( version_compare( phpversion(), '7.3.0', '>=' ) ){
		
		$params = array(
			'expires'  => $expires,
			'path'     => $path,
			'domain'   => $domain,
			'secure'   => $secure,
			'httponly' => $httponly,
		);
		
		if($samesite)
			$params['samesite'] = $samesite;
		
		setcookie( $name, $value, $params );
		
	// For PHP 5.6 - 7.2
	}else {
        if($samesite)
            $path = $path . '; samesite=' . $samesite;
        setcookie( $name, $value, $expires, $path, $domain, $secure, $httponly );
    }

}

/*
 * Set Cookies test for cookie test
 * Sets cookies with pararms timestamp && landing_timestamp && pervious_referer
 * Sets test cookie with all other cookies
 */
function apbct_cookie(){
	
	global $apbct;
	
	if($apbct->settings['store_urls__sessions'] || $apbct->settings['set_cookies__sessions'])
		apbct_alt_sessions__remove_old();
	
	if(
		empty($apbct->settings['set_cookies']) || // Do not set cookies if option is disabled (for Varnish cache).
		!empty($apbct->flags__cookies_setuped) || // Cookies already set
		!empty($apbct->headers_sent)              // Headers sent
	)
		return false;
	
	// Prevent headers sent error
	if(headers_sent($file, $line)){
		$apbct->headers_sent = true;
		$apbct->headers_sent__hook  = current_action();
		$apbct->headers_sent__where = $file.':'.$line;
		return false;
	}
	
	
    // Cookie names to validate
	$cookie_test_value = array(
		'cookies_names' => array(),
		'check_value' => $apbct->api_key,
	);
	
	// We need to skip the domain attribute for prevent including the dot to the cookie's domain on the client.
    $domain = null;

// Submit time
	if(empty($_POST['ct_multipage_form'])){ // Do not start/reset page timer if it is multipage form (Gravitiy forms))
		$apbct_timestamp = time();
		$apbct->settings['set_cookies__sessions']
			? apbct_alt_session__save('apbct_timestamp', $apbct_timestamp)
			: \Cleantalk\Antispam\Helper::apbct_cookie__set('apbct_timestamp', $apbct_timestamp,  0, '/', $domain, false, true, 'Lax' );
		$cookie_test_value['cookies_names'][] = 'apbct_timestamp';
		$cookie_test_value['check_value'] .= $apbct_timestamp;
	}

// Pervious referer
	if(apbct_get_server_variable( 'HTTP_REFERER' )){
		$apbct->settings['set_cookies__sessions']
			? apbct_alt_session__save('apbct_prev_referer', apbct_get_server_variable( 'HTTP_REFERER' ))
			: \Cleantalk\Antispam\Helper::apbct_cookie__set('apbct_prev_referer', apbct_get_server_variable( 'HTTP_REFERER' ), 0, '/', $domain, false, true, 'Lax' );
		$cookie_test_value['cookies_names'][] = 'apbct_prev_referer';
		$cookie_test_value['check_value'] .= apbct_get_server_variable( 'HTTP_REFERER' );
	}
	
// Landing time
	$site_landing_timestamp = $apbct->settings['set_cookies__sessions']
		? apbct_alt_session__get('apbct_site_landing_ts')
		: filter_input(INPUT_COOKIE, 'apbct_site_landing_ts');
	if(!$site_landing_timestamp){
		$site_landing_timestamp = time();
		$apbct->settings['set_cookies__sessions']
			? apbct_alt_session__save('apbct_site_landing_ts', $site_landing_timestamp)
			: \Cleantalk\Antispam\Helper::apbct_cookie__set('apbct_site_landing_ts', $site_landing_timestamp, 0, '/', $domain, false, true, 'Lax' );
	}
	$cookie_test_value['cookies_names'][] = 'apbct_site_landing_ts';
	$cookie_test_value['check_value'] .= $site_landing_timestamp;
	
// Page hits	
	// Get
	$page_hits = $apbct->settings['set_cookies__sessions']
		? apbct_alt_session__get('apbct_page_hits')
		: filter_input(INPUT_COOKIE, 'apbct_page_hits');
	// Set / Increase
	$page_hits = intval($page_hits) ? $page_hits + 1 : 1;
	
	$apbct->settings['set_cookies__sessions']
		? apbct_alt_session__save('apbct_page_hits', $page_hits)
		: \Cleantalk\Antispam\Helper::apbct_cookie__set('apbct_page_hits', $page_hits, 0, '/', $domain, false, true, 'Lax' );
	
	$cookie_test_value['cookies_names'][] = 'apbct_page_hits';
	$cookie_test_value['check_value'] .= $page_hits;
	
	// Cookies test
	$cookie_test_value['check_value'] = md5($cookie_test_value['check_value']);
	if(!$apbct->settings['set_cookies__sessions'])
        \Cleantalk\Antispam\Helper::apbct_cookie__set('apbct_cookies_test', urlencode(json_encode($cookie_test_value)), 0, '/', $domain, false, true, 'Lax' );
	
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
	
	if($apbct->settings['set_cookies__sessions'])
		return 1;
	
	if(isset($_COOKIE['apbct_cookies_test'])){
		
		$cookie_test = json_decode(urldecode($_COOKIE['apbct_cookies_test']),true);
		
		if(!is_array($cookie_test))
			return 0;
		
		$check_srting = $apbct->api_key;
		foreach($cookie_test['cookies_names'] as $cookie_name){
			$check_srting .= isset($_COOKIE[$cookie_name]) ? $_COOKIE[$cookie_name] : '';
		} unset($cookie_name);
		
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
	global $apbct;
	$apbct_timestamp = $apbct->settings['set_cookies__sessions']
		? apbct_alt_session__get('apbct_timestamp')
		: filter_input(INPUT_COOKIE, 'apbct_timestamp');
	return apbct_cookies_test() == 1 ? time() - (int)$apbct_timestamp : null;
}

/*
 * Inner function - Account status check
 * Scheduled in 1800 seconds for default!
 */
function ct_account_status_check($api_key = null, $process_errors = true){
	
	global $apbct;
	
	$api_key = $api_key ? $api_key : $apbct->api_key;
	$result = CleantalkAPI::method__notice_paid_till(
		$api_key,
		preg_replace('/http[s]?:\/\//', '', get_option('siteurl'), 1),
		! is_main_site() && $apbct->white_label ? 'anti-spam-hosting' : 'antispam'
	);
	
	if(empty($result['error']) || !empty($result['valid'])){
		
		// Notices
		$apbct->data['notice_show']        = isset($result['show_notice'])             ? (int)$result['show_notice']             : 0;
		$apbct->data['notice_renew']       = isset($result['renew'])                   ? (int)$result['renew']                   : 0;
		$apbct->data['notice_trial']       = isset($result['trial'])                   ? (int)$result['trial']                   : 0;
		$apbct->data['notice_review']      = isset($result['show_review'])             ? (int)$result['show_review']             : 0;
		$apbct->data['notice_auto_update'] = isset($result['show_auto_update_notice']) ? (int)$result['show_auto_update_notice'] : 0;
		
		// Other
		$apbct->data['service_id']         = isset($result['service_id'])                         ? (int)$result['service_id']         : 0;
		$apbct->data['valid']              = isset($result['valid'])                              ? (int)$result['valid']              : 0;
		$apbct->data['moderate']           = isset($result['moderate'])                           ? (int)$result['moderate']           : 0;
		$apbct->data['ip_license']         = isset($result['ip_license'])                         ? (int)$result['ip_license']         : 0;
		$apbct->data['moderate_ip']        = isset($result['moderate_ip'], $result['ip_license']) ? (int)$result['moderate_ip']        : 0;
		$apbct->data['spam_count']         = isset($result['spam_count'])                         ? (int)$result['spam_count']         : 0;
		$apbct->data['auto_update']        = isset($result['auto_update_app'])                    ? (int)$result['auto_update_app']    : 0;
		$apbct->data['user_token']         = isset($result['user_token'])                         ? (string)$result['user_token']      : '';
		$apbct->data['license_trial']      = isset($result['license_trial'])                      ? (int)$result['license_trial']      : 0;
		$apbct->data['account_name_ob']    = isset($result['account_name_ob'])                    ? (string)$result['account_name_ob'] : '';
		
		CleantalkCron::updateTask('check_account_status', 'ct_account_status_check',  86400);
		
		$apbct->error_delete('account_check', 'save');
		
		$apbct->saveData();
		
	}elseif($process_errors){
		$apbct->error_add('account_check', $result);
	}
	
	if(!empty($result['valid'])){
		$apbct->data['key_is_ok'] = true;
		$result = true;
	}else{
		$apbct->data['key_is_ok'] = false;
		$result = false;
	}
	
	return $result;
}

function ct_mail_send_connection_report() {
	
	global $apbct;
	
    if (($apbct->settings['send_connection_reports'] == 1 && $apbct->connection_reports['negative'] > 0) || !empty($_GET['ct_send_connection_report']))
    {
		$to  = "welcome@cleantalk.org" ; 
		$subject = "Connection report for " . apbct_get_server_variable( 'HTTP_HOST' );
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
				    <td><b>Server IP</b></td>
				  </tr>
				  ';
		foreach ($apbct->connection_reports['negative_report'] as $key => $report)
		{
			$message.= '<tr>'
				. '<td>'.($key+1).'.</td>'
				. '<td>'.$report['date'].'</td>'
				. '<td>'.$report['page_url'].'</td>'
				. '<td>'.$report['lib_report'].'</td>'
				. '<td>'.$report['work_url'].'</td>'
			. '</tr>';
		}
		$message.='</table></body></html>';
		
		$headers  = 'Content-type: text/html; charset=windows-1251 \r\n'; 
		$headers .= 'From: '.get_option('admin_email');
		mail($to, $subject, $message, $headers);
    }
 
	$apbct->data['connection_reports'] = $apbct->def_data['connection_reports'];
	$apbct->data['connection_reports']['since'] = date('d M');
	$apbct->saveData();
}

//* Write $message to the plugin's debug option
function apbct_log($message = 'empty', $func = null, $params = array())
{
	global $apbct;

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

function apbct_sfw__delete_tables( $blog_id, $drop ) {
	
	global $wpdb;
	
    $initial_blog  = get_current_blog_id();
	
	switch_to_blog($blog_id);
	$wpdb->query('DROP TABLE IF EXISTS `'. $wpdb->prefix.'cleantalk_sfw`;');       // Deleting SFW data
	$wpdb->query('DROP TABLE IF EXISTS `'. $wpdb->prefix.'cleantalk_sfw_logs`;');  // Deleting SFW logs
	
	switch_to_blog($initial_blog);
}

/**
 * Is enable for user group
 *
 * @param WP_User $user
 *
 * @return boolean
 */
function apbct_is_user_enable($user = null) {
	
	global $current_user;
	
	$user = !empty($user) ? $user : $current_user;
	
	return apbct_is_user_role_in(array('administrator', 'editor', 'author'), $user)
		? false
		: true;
}

/**
 * Checks if the current user has role
 *  
 * @param array $roles array of strings
 * @param int|string|WP_User|mixed $user User ID to check|user_login|WP_User
 *
 * @return boolean Does the user has this role|roles
 */
function apbct_is_user_role_in( $roles, $user = false ){
	
	if( is_numeric($user) && function_exists('get_userdata'))        $user = get_userdata( $user );
	if( is_string($user)  && function_exists('get_user_by'))         $user = get_user_by('login', $user );
	if( ! $user           && function_exists('wp_get_current_user')) $user = wp_get_current_user();
	if( ! $user )                                                                 $user = apbct_wp_get_current_user();
	
	if( empty($user->ID) )
		return false;
	
	foreach( (array) $roles as $role ){
		if( isset($user->caps[ strtolower($role) ]) || in_array(strtolower($role), $user->roles) )
			return true;
	}
	
	return false;
}

/**
 * Update and rotate statistics with requests exection time
 *
 * @param $exec_time
 */
function apbct_statistics__rotate($exec_time){
	
	global $apbct;
	
	// Delete old stats
	if(min(array_keys($apbct->stats['requests'])) < time() - (86400 * 7))
		unset($apbct->stats['requests'][min(array_keys($apbct->stats['requests']))]);

	// Create new if newest older than 1 day
	if(empty($apbct->stats['requests']) ||  max(array_keys($apbct->stats['requests'])) < time() - (86400 * 1))
		$apbct->stats['requests'][time()] = array('amount' => 0, 'average_time' => 0);
	
	// Update all existing stats
	foreach($apbct->stats['requests'] as &$weak_stat){
		$weak_stat['average_time'] = ($weak_stat['average_time'] * $weak_stat['amount'] + $exec_time) / ++$weak_stat['amount'];
	}
	
	$apbct->save('stats');
}

/**
 * Runs update actions for new version.
 *
 * @global CleantalkState $apbct
 */
function apbct_update_actions(){
	
	global $apbct;
	
	// Update logic	
	if($apbct->plugin_version != APBCT_VERSION){
		
		// Main blog
		if(is_main_site()){
			
			require_once(CLEANTALK_PLUGIN_DIR.'inc/cleantalk-updater.php');
			
			$result = apbct_run_update_actions($apbct->plugin_version, APBCT_VERSION);
			
			//If update is successfull
			if($result === true)
				apbct_update__set_version__from_plugin('from_plugin');
			
			ct_send_feedback('0:' . APBCT_AGENT ); // Send feedback to let cloud know about updated version.
			
		// Side blogs
		}else{
			apbct_update__set_version__from_plugin('from_plugin');
		}
	}
	
}

/**
 * Set version of plugin in database
 *
 * @param string          $ver
 *
 * @return bool
 * @global CleantalkState $apbct
 *
 */
function apbct_update__set_version__from_plugin($ver){
	global $apbct;
	switch (true){
		case $ver === 'from_plugin':
			$apbct->data['plugin_version'] = APBCT_VERSION;
			break;
		case preg_match('/^\d+\.\d+(\.\d+)?(-[a-zA-Z0-9-_]+)?$/', $ver) === 1;
			$apbct->data['plugin_version'] = $ver;
			break;
		default:
			return false;
			break;
	}
	$apbct->saveData();
	return true;
}
