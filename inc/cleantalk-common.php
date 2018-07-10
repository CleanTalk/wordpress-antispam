<?php

$ct_plugin_name = 'Antispam by CleanTalk';
$ct_checkjs_frm = 'ct_checkjs_frm';
$ct_checkjs_register_form = 'ct_checkjs_register_form';

$apbct_cookie_request_id_label  = 'request_id';
$apbct_cookie_register_ok_label = 'register_ok';

$ct_checkjs_cf7 = 'ct_checkjs_cf7';
$ct_cf7_comment = '';

$ct_checkjs_jpcf = 'ct_checkjs_jpcf';
$ct_jpcf_patched = false; 
$ct_jpcf_fields = array('name', 'email');

// Comment already proccessed
$ct_comment_done = false;

// Comment already proccessed
$ct_signup_done = false;

//Contains registration error
$ct_registration_error_comment = false;

// Default value for JS test
$ct_checkjs_def = 0;

// COOKIE label to store request id for last approved  
$ct_approved_request_id_label = 'ct_approved_request_id';

// Last request id approved for publication 
$ct_approved_request_id = null;

// COOKIE label for trial notice flag
$ct_notice_trial_label = 'ct_notice_trial';

// Flag to show trial notice
$show_ct_notice_trial = false;

// COOKIE label for renew notice flag
$ct_notice_renew_label = 'ct_notice_renew';

// Flag to show renew notice
$show_ct_notice_renew = false;

// COOKIE label for online notice flag
$ct_notice_online_label = 'ct_notice_online';

// Flag to show online notice - 'Y' or 'N'
$show_ct_notice_online = '';

// Trial notice show time in minutes
$trial_notice_showtime = 10;

// Renew notice show time in minutes
$renew_notice_showtime = 10;

// COOKIE label for WP Landing Page proccessing result
$ct_wplp_result_label = 'ct_wplp_result';

// Flag indicates active JetPack comments 
$ct_jp_comments = false;

// S2member PayPal post data label
$ct_post_data_label = 's2member_pro_paypal_registration'; 

// S2member Auth.Net post data label
$ct_post_data_authnet_label = 's2member_pro_authnet_registration'; 

// WP admin email notice interval in seconds
$ct_admin_notoice_period = 21600;

// Sevice negative comment to visitor.
// It uses for BuddyPress registrations to avoid double checks
$ct_negative_comment = null;

// Flag to show apikey automatic getting error
$show_ct_notice_autokey = false;

// Apikey automatic getting label  
$ct_notice_autokey_label = 'ct_autokey'; 

// Apikey automatic getting error text
$ct_notice_autokey_value = '';

// Set globals to NULL to avoid massive DB requests. Globals will be set when needed only and by accessors only.
$ct_options = NULL;
$ct_data = NULL;
$ct_server = NULL;
$admin_email = NULL;

// Timer in PHP sessions state.
$ct_page_timer_setuped = false;

/**
 * Public action 'plugins_loaded' - Loads locale, see http://codex.wordpress.org/Function_Reference/load_plugin_textdomain
 */
function apbct_plugin_loaded() {
	$dir=plugin_basename( dirname( __FILE__ ) ) . '/../i18n';
    $loaded=load_plugin_textdomain('cleantalk', false, $dir);
}

/**
 * Inner function - Request's wrapper for anything
 * @param array Array of parameters:
 *  'message' - string
 *  'example' - string
 *  'checkjs' - int
 *  'sender_email' - string
 *  'sender_nickname' - string
 *  'sender_info' - array
 *  'post_info' - string
 * @return array array('ct'=> Cleantalk, 'ct_result' => CleantalkResponse)
 */
function apbct_base_call($params = array(), $reg_flag = false){
	
	global $ct_options, $ct_data;
	
	$ct_options = ct_get_options();
	$ct_data    = ct_get_data();
	
    $sender_info = !empty($params['sender_info'])
		? array_merge(apbct_get_sender_info(), (array) $params['sender_info'])
		: apbct_get_sender_info();
	
    $config = ct_get_server();
	
	$ct_request = new CleantalkRequest();
	
	// IPs
	$ct_request->sender_ip       = defined('CT_TEST_IP') ? CT_TEST_IP : (isset($params['sender_ip']) ? $params['sender_ip'] : CleantalkHelper::ip_get(array('real'), false));
	$ct_request->x_forwarded_for = CleantalkHelper::ip_get(array('x_forwarded_for'), false);
	$ct_request->x_real_ip       = CleantalkHelper::ip_get(array('x_real_ip'), false);
	
	// Misc
	$ct_request->auth_key        = $ct_options['apikey'];
	$ct_request->message         = !empty($params['message'])         ? serialize(ct_filter_array($params['message']))   : null;
	$ct_request->example         = !empty($params['example'])         ? $params['example']                               : null;
	$ct_request->sender_email    = !empty($params['sender_email'])    ? $params['sender_email']                          : null;
	$ct_request->sender_nickname = !empty($params['sender_nickname']) ? $params['sender_nickname']                       : null;
	$ct_request->post_info       =  isset($params['post_info'])       ? json_encode($params['post_info'])                : null;
	$ct_request->js_on           =  isset($params['checkjs'])         ? $params['checkjs']                               : apbct_js_test('ct_checkjs', $_COOKIE, true);
	$ct_request->agent           = APBCT_AGENT;
	$ct_request->sender_info     = json_encode($sender_info);
	$ct_request->submit_time     = apbct_get_submit_time();
	
	$ct = new Cleantalk();

	$ct->ssl_on         = $ct_options['ssl_on'];
	$ct->ssl_path       = APBCT_CASERT_PATH;
	$ct->server_url     = $ct_options['server'];
	$ct->server_ttl     = $config['ct_server_ttl'];
	$ct->work_url       = $config['ct_work_url'];
	$ct->server_changed = $config['ct_server_changed'];
	
	if($reg_flag){
		$ct_result = @$ct->isAllowUser($ct_request);
	}else{
		$ct_result = @$ct->isAllowMessage($ct_request);		
	}
	
	if ($ct_result->errno === 0 && empty($ct_result->errstr))
        $ct_data['connection_reports']['success']++;
    else
    {
        $ct_data['connection_reports']['negative']++;
        $ct_data['connection_reports']['negative_report'][] = array('date'=>date("Y-m-d H:i:s"),'page_url'=>$_SERVER['REQUEST_URI'],'lib_report'=>$ct_result->errstr);
		
		if(count($ct_data['connection_reports']['negative_report']) > 20)
			$ct_data['connection_reports']['negative_report'] = array_slice($ct_data['connection_reports']['negative_report'], -20, 20);
		
    }
    if ($ct->server_change) {
		update_option(
			'cleantalk_server', 
			array(
				'ct_work_url'       => $ct->work_url,
				'ct_server_ttl'     => $ct->server_ttl,
				'ct_server_changed' => time(),
			)
		);
    }
    
    $ct_result = ct_change_plugin_resonse($ct_result, $ct_request->js_on);
	
	// Restart submit form counter for failed requests
    if ($ct_result->allow == 0){
		apbct_cookie(); // Setting page timer and cookies
       	ct_add_event('no');
    }else{
       	ct_add_event('yes');
    }
	
    return array('ct' => $ct, 'ct_result' => $ct_result);
	
}

/**
 * Inner function - Default data array for senders 
 * @return array 
 */
function apbct_get_sender_info() {
	    
	// Validate cookie from the backend
	$cookie_is_ok = apbct_cookies_test();
    
	if (count($_POST) > 0) {
		foreach ($_POST as $k => $v) {
			if (preg_match("/^ct_check.+/", $k)) {
        		$checkjs_data_post = $v; 
			}
		}
	}
	
	// AMP check
	$amp_detected = isset($_SERVER['HTTP_REFERER'])
		? strpos($_SERVER['HTTP_REFERER'], '/amp/') !== false || strpos($_SERVER['HTTP_REFERER'], '?amp=1') !== false || strpos($_SERVER['HTTP_REFERER'], '&amp=1') !== false
			? 1
			: 0
		: null;
	
	return array(
		'remote_addr'            => CleantalkHelper::ip_get(array('remote_addr'), false),
        'REFFERRER'              => isset($_SERVER['HTTP_REFERER'])                                ? htmlspecialchars($_SERVER['HTTP_REFERER'])                        : null,
        'USER_AGENT'             => isset($_SERVER['HTTP_USER_AGENT'])                             ? htmlspecialchars($_SERVER['HTTP_USER_AGENT'])                     : null,
		'page_url'               => isset($_SERVER['SERVER_NAME'], $_SERVER['REQUEST_URI'])        ? htmlspecialchars($_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']) : null,
        'cms_lang'               => substr(get_locale(), 0, 2),
        'ct_options'             => json_encode(ct_get_options()),
        'fields_number'          => sizeof($_POST),
        'direct_post'            => $cookie_is_ok === null && $_SERVER['REQUEST_METHOD'] == 'POST' ? 1                                                                 : 0,
		// Raw data to validated JavaScript test in the cloud                                                                                                          
        'checkjs_data_cookies'   => !empty($_COOKIE['ct_checkjs'])                                 ? $_COOKIE['ct_checkjs']                                            : null, 
        'checkjs_data_post'      => !empty($checkjs_data_post)                                     ? $checkjs_data_post                                                : null, 
		// PHP cookies                                                                                                                                                 
        'cookies_enabled'        => $cookie_is_ok,                                                                                                                     
		'REFFERRER_PREVIOUS'     => !empty($_COOKIE['apbct_prev_referer'])    && $cookie_is_ok     ? $_COOKIE['apbct_prev_referer']                                    : null,
		'site_landing_ts'        => !empty($_COOKIE['apbct_site_landing_ts']) && $cookie_is_ok     ? $_COOKIE['apbct_site_landing_ts']                                 : null,
		'page_hits'              => !empty($_COOKIE['apbct_page_hits'])                            ? $_COOKIE['apbct_page_hits']                                       : null,
		// JS cookies                                                                                                                                                  
        'js_info'                => !empty($_COOKIE['ct_user_info'])                               ? json_decode(stripslashes($_COOKIE['ct_user_info']), true)         : null,
		'mouse_cursor_positions' => !empty($_COOKIE['ct_pointer_data'])                            ? json_decode(stripslashes($_COOKIE['ct_pointer_data']), true)      : null,
		'js_timezone'            => !empty($_COOKIE['ct_timezone'])                                ? $_COOKIE['ct_timezone']                                           : null,
		'key_press_timestamp'    => !empty($_COOKIE['ct_fkp_timestamp'])                           ? $_COOKIE['ct_fkp_timestamp']                                      : null,
		'page_set_timestamp'     => !empty($_COOKIE['ct_ps_timestamp'])                            ? $_COOKIE['ct_ps_timestamp']                                       : null,
		'form_visible_inputs'    => !empty($_COOKIE['apbct_visible_fields_count'])                 ? $_COOKIE['apbct_visible_fields_count']                            : null,
		'apbct_visible_fields'   => !empty($_COOKIE['apbct_visible_fields'])                       ? $_COOKIE['apbct_visible_fields']                                  : null,
		// Debug stuff
		'amp_detected'           => $amp_detected,
	);
}

/**
 * Cookies test for sender 
 * @return null|0|1;
 */
function ct_cookies_test ($test = false) {
    $ct_options = ct_get_options();
    
    $cookie_label = 'ct_cookies_test';
    $secret_hash = ct_get_checkjs_value();

    $result = null;
    if (isset($_COOKIE[$cookie_label])) {
        if ($_COOKIE[$cookie_label] == $secret_hash) {
            $result = 1;
        } else {
            $result = 0;
        }
    } else {
        //
        // Do not generate if admin turned off the cookies.
        //
        if (isset($ct_options['set_cookies']) && $ct_options['set_cookies'] == 1) {
            @setcookie($cookie_label, $secret_hash, 0, '/');
        }

        if ($test) {
            $result = 0;
        }
    }

    return $result;
}

/**
 * Get ct_get_checkjs_value 
 * @return string
 */
function ct_get_checkjs_value($random_key = false) {
    global $ct_options, $ct_data;
    $ct_options = ct_get_options();
    $ct_data = ct_get_data();

    if ($random_key) {
        $keys = $ct_data['js_keys'];
        $keys_checksum = md5(json_encode($keys));
        
        $key = null;
        $latest_key_time = 0;
        foreach ($keys as $k => $t) {

            // Removing key if it's to old
            if (time() - $t > $ct_data['js_keys_store_days'] * 86400) {
                unset($keys[$k]);
                continue;
            }

            if ($t > $latest_key_time) {
                $latest_key_time = $t;
                $key = $k;
            }
        }
        
        // Get new key if the latest key is too old
        if (time() - $latest_key_time > $ct_data['js_key_lifetime']) {
            $key = rand();
            $keys[$key] = time();
        }
        
        if (md5(json_encode($keys)) != $keys_checksum) {
            $ct_data['js_keys'] = $keys;
            update_option('cleantalk_data', $ct_data);
        }
    } else {
        $key = md5($ct_options['apikey'] . '+' . ct_get_admin_email());
    }

    return $key; 
}

/**
 * Inner function - Current site admin e-mail
 * @return 	string Admin e-mail
 */
function ct_get_admin_email() {
	global $admin_email;
	if(!isset($admin_email))
	{
	    $admin_email = get_option('admin_email');
	}
	return $admin_email;
}

/**
 * Inner function - Current Cleantalk working server info
 * @return 	mixed[] Array of server data
 */
function ct_get_server($force=false) {
	global $ct_server;
	if(!$force && isset($ct_server) && isset($ct_server['ct_work_url']) && !empty($ct_server['ct_work_url'])){
		
		return $ct_server;
		
	}else{
		
	    $ct_server = get_option('cleantalk_server');
	    if (!is_array($ct_server)){
	        $ct_server = array(
				'ct_work_url' => NULL,
				'ct_server_ttl' => NULL,
				'ct_server_changed' => NULL
			);
	    }
	    return $ct_server;
	}
}

/**
 * Inner function - Current Cleantalk options
 * @return 	mixed[] Array of options
 */
function ct_get_options($force=false) {
	global $ct_options;
	if(!$force && isset($ct_options) && isset($ct_options['apikey']) && strlen($ct_options['apikey'])>3)
	{
        //
        // Skip query to get options because we already have options. 
        //
		if(defined('CLEANTALK_ACCESS_KEY'))
	    {
	    	$options['apikey']=CLEANTALK_ACCESS_KEY;
	    }
		return $ct_options;
	}
	else
	{
	    $options = get_option('cleantalk_settings');
	    if (!is_array($options)){
	        $options = array();
	    }else{
		if(array_key_exists('apikey', $options))
		    $options['apikey'] = trim($options['apikey']);
	    }
	    if(defined('CLEANTALK_ACCESS_KEY'))
	    {
	    	$options['apikey']=CLEANTALK_ACCESS_KEY;
	    }
        $options = array_merge(ct_def_options(), (array) $options);

        if ($options['apikey'] === 'enter key' || $options['apikey'] === '') {
            if ($options['protect_logged_in'] == -1) {
                $options['protect_logged_in'] = 1;
            }
        }
        
	    return $options; 
	}
}

/**
 * Inner function - Default Cleantalk options
 * @return 	mixed[] Array of default options
 */
function ct_def_options() {
    return array(
		'spam_firewall' => '0',
        'server' => 'http://moderate.cleantalk.org',
        'apikey' => '',
        'autoPubRevelantMess' => '0', 
		//Forms for protection
        'registrations_test' => '1',
        'comments_test' => '1', 
        'contact_forms_test' => '1', 
        'general_contact_forms_test' => '1', // Antispam test for unsupported and untested contact forms 
		'wc_checkout_test' => '0', //WooCommerce checkout default test => OFF
		'check_external' => '0',
        'check_internal' => '0',
		//Comments and messages
		'bp_private_messages' => '1', //buddyPress private messages test => ON
		'check_comments_number' => '1',
        'remove_old_spam' => '0',
		'remove_comments_links' => '0', //Removes links from approved comments
		'show_check_links' => '1', //Shows check link to Cleantalk's DB. And allowing to control comments form public page.
		//Data processing
        'protect_logged_in' => '1', // Do anit-spam tests to for logged in users.
		'use_ajax' => '1',
		'general_postdata_test' => '0', //CAPD
        'set_cookies'=> '1', // Disable cookies generatation to be compatible with Varnish.
        'ssl_on' => '0', // Secure connection to servers 
		//Administrator Panel
        'show_adminbar' => '1', // Show the admin bar.
		'all_time_counter' => '0',
		'daily_counter' => '0',
		//Others
        'spam_store_days' => '15', // Days before delete comments from folder Spam 
        'relevance_test' => 0, // Test comment for relevance 
        'notice_api_errors' => 0, // Send API error notices to WP admin
        'user_token'=>'', //user token for auto login into spam statistics
        'collect_details' => 0, // Collect details about browser of the visitor. 
        'send_connection_reports' => 0, //Send connection reports to Cleantalk servers
		'show_link' => 0,
		'async_js' => 0,
    );
}

/**
 * Inner function - Current Cleantalk data
 * @return 	mixed[] Array of options
 */
function ct_get_data($force=false) {
	global $ct_data;
	if(!$force && isset($ct_data) && isset($ct_data['js_keys']))
	{
		return $ct_data;
	}
	else
	{
	    $data = get_option('cleantalk_data');
	    if (!is_array($data)){
	        $data = array();
	    }
	    return array_merge(ct_def_data(), (array) $data);
	}
}

/**
 * Inner function - Default Cleantalk data
 * @return 	mixed[] Array of default options
 */
function ct_def_data() {
	
    return array(
		'start_version' => APBCT_VERSION,
        'user_token' => '', // User token 
        'js_keys' => array(), // Keys to do JavaScript antispam test 
        'js_keys_store_days' => 14, // JavaScript keys store days - 8 days now
        'js_key_lifetime' => 86400, // JavaScript key life time in seconds - 1 day now
		'ip_license' => 0,
		'service_id' => 0,
		'moderate' => 0,
		'sfw_counter' => array(
			'all' => 0,
			'blocked' => 0
		),
		'array_accepted' => array(),
		'array_blocked' => array(),
		'current_hour' => '',
		'all_time_counter' => array(
			'accepted' => 0,
			'blocked' => 0
		),
		'user_counter' => array(
			'accepted' => 0,
			'blocked' => 0,
			'since' => date('d M')
		),
        'connection_reports' => array(
            'success' => 0,
            'negative' => 0,
            'negative_report' => array(),
            'since' => date('d M')
        )        
    );
}

/**
 * Inner function - Stores ang returns cleantalk hash of current comment
 * @param	string New hash or NULL
 * @return 	string New hash or current hash depending on parameter
 */
function ct_hash($new_hash = '') {
    /**
     * Current hash
     */
    static $hash;

    if (!empty($new_hash)) {
        $hash = $new_hash;
    }
    return $hash;
}

/**
 * Inner function - Write manual moderation results to PHP sessions 
 * @param 	string $hash Cleantalk comment hash
 * @param 	string $message comment_content
 * @param 	int $allow flag good comment (1) or bad (0)
 * @return 	string comment_content w\o cleantalk resume
 */
function ct_feedback($hash, $allow) {
	
    global $ct_data;
    
    $ct_data = ct_get_data();
	
    $ct_feedback = $hash . ':' . $allow . ';';
    if(empty($ct_data['feedback_request']))
		$ct_data['feedback_request'] = $ct_feedback; 
    else
		$ct_data['feedback_request'] .= $ct_feedback; 
	
	update_option('cleantalk_data', $ct_data);
}

/**
 * Inner function - Sends the results of moderation
 * Scheduled in 3600 seconds!
 * @param string $feedback_request
 * @return bool
 */
function ct_send_feedback($feedback_request = null) {
	
    global $ct_options, $ct_data;
    
    $ct_options = ct_get_options();
    $ct_data = ct_get_data();

    if (empty($feedback_request) && isset($ct_data['feedback_request']) && preg_match("/^[a-z0-9\;\:]+$/", $ct_data['feedback_request'])){
		$feedback_request = $ct_data['feedback_request'];
		unset($ct_data['feedback_request']);
		update_option('cleantalk_data', $ct_data);
    }

    if ($feedback_request !== null) {
		
        $config = ct_get_server();

        $ct = new Cleantalk();
        $ct->work_url = $config['ct_work_url'];
        $ct->server_url = $ct_options['server'];
        $ct->server_ttl = $config['ct_server_ttl'];
        $ct->server_changed = $config['ct_server_changed'];

        $ct_request = new CleantalkRequest();
        $ct_request->auth_key = $ct_options['apikey'];
        $ct_request->feedback = $feedback_request;

        $ct->sendFeedback($ct_request);

        if ($ct->server_change) {
            update_option(
                'cleantalk_server', array(
                'ct_work_url' => $ct->work_url,
                'ct_server_ttl' => $ct->server_ttl,
                'ct_server_changed' => time()
                )
            );
        }
        return true;
    }

    return false;
}

/**
 * Delete old spam comments 
 * Scheduled in 3600 seconds!
 * @return null 
 */
function ct_delete_spam_comments() {
    global $pagenow, $ct_options, $ct_data;
    
    $ct_options = ct_get_options();
    $ct_data = ct_get_data();
    
    if ($ct_options['remove_old_spam'] == 1) {
        $last_comments = get_comments(array('status' => 'spam', 'number' => 1000, 'order' => 'ASC'));
        foreach ($last_comments as $c) {
            if (time() - strtotime($c->comment_date_gmt) > 86400 * $ct_options['spam_store_days']) {
                // Force deletion old spam comments
                wp_delete_comment($c->comment_ID, true);
            } 
        }
    }

    return null; 
}

/*
* Get data from an ARRAY recursively
* @return array
*/ 
function ct_get_fields_any($arr, $message=array(), $email = null, $nickname = array('nick' => '', 'first' => '', 'last' => ''), $subject = null, $contact = true, $prev_name = ''){
	
	//Skip request if fields exists
	$skip_params = array(
	    'ipn_track_id', 	// PayPal IPN #
	    'txn_type', 		// PayPal transaction type
	    'payment_status', 	// PayPal payment status
	    'ccbill_ipn', 		// CCBill IPN 
		'ct_checkjs', 		// skip ct_checkjs field
		'api_mode',         // DigiStore-API
		'loadLastCommentId' // Plugin: WP Discuz. ticket_id=5571
    );
	
	// Fields to replace with ****
    $obfuscate_params = array(
        'password',
        'pass',
        'pwd',
		'pswd'
    );
	
	// Skip feilds with these strings and known service fields
	$skip_fields_with_strings = array( 
		// Common
		'ct_checkjs', //Do not send ct_checkjs
		'nonce', //nonce for strings such as 'rsvp_nonce_name'
		'security',
		// 'action',
		'http_referer',
		'timestamp',
		'captcha',
		// Formidable Form
		'form_key',
		'submit_entry',
		// Custom Contact Forms
		'form_id',
		'ccf_form',
		'form_page',
		// Qu Forms
		'iphorm_uid',
		'form_url',
		'post_id',
		'iphorm_ajax',
		'iphorm_id',
		// Fast SecureContact Froms
		'fs_postonce_1',
		'fscf_submitted',
		'mailto_id',
		'si_contact_action',
		// Ninja Forms
		'formData_id',
		'formData_settings',
		'formData_fields_\d+_id',
		'formData_fields_\d+_files.*',		
		// E_signature
		'recipient_signature',
		'output_\d+_\w{0,2}',
		// Contact Form by Web-Settler protection
        '_formId',
        '_returnLink',
		// Social login and more
		'_save',
		'_facebook',
		'_social',
		'user_login-',
		// Contact Form 7
		'_wpcf7',
		'ebd_settings',
		'ebd_downloads_',
		'ecole_origine',
	);
	
	// Reset $message if we have a sign-up data
    $skip_message_post = array(
        'edd_action', // Easy Digital Downloads
    );
	
   	foreach($skip_params as $value){
   		if(@array_key_exists($value,$_GET)||@array_key_exists($value,$_POST))
   			$contact = false;
   	} unset($value);
		
	if(count($arr)){
		foreach($arr as $key => $value){
			
			if(gettype($value)=='string'){
				$decoded_json_value = json_decode($value, true);
				if($decoded_json_value !== null)
					$value = $decoded_json_value;
			}
			
			if(!is_array($value) && !is_object($value) && @get_class($value)!='WP_User'){
				
				if (in_array($key, $skip_params, true) && $key != 0 && $key != '' || preg_match("/^ct_checkjs/", $key))
					$contact = false;
				
				if($value === '')
					continue;
				
				// Skipping fields names with strings from (array)skip_fields_with_strings
				foreach($skip_fields_with_strings as $needle){
					if (preg_match("/".$needle."/", $prev_name.$key) == 1){
						continue(2);
					}
				}unset($needle);
				
				// Obfuscating params
				foreach($obfuscate_params as $needle){
					if (strpos($key, $needle) !== false){
						$value = ct_obfuscate_param($value);
						continue(2);
					}
				}unset($needle);
				
				// Removes shortcodes to do better spam filtration on server side.
				$value = strip_shortcodes($value);

				// Decodes URL-encoded data to string.
				$value = urldecode($value);	

				// Email
				if (!$email && preg_match("/^\S+@\S+\.\S+$/", $value)){
					$email = $value;
					
				// Names
				}elseif (preg_match("/name/i", $key)){
					
					preg_match("/((name.?)?(your|first|for)(.?name)?)$/", $key, $match_forename);
					preg_match("/((name.?)?(last|family|second|sur)(.?name)?)$/", $key, $match_surname);
					preg_match("/^(name.?)?(nick|user)(.?name)?$/", $key, $match_nickname);
					
					if(count($match_forename) > 1)
						$nickname['first'] = $value;
					elseif(count($match_surname) > 1)
						$nickname['last'] = $value;
					elseif(count($match_nickname) > 1)
						$nickname['nick'] = $value;
					else
						$message[$prev_name.$key] = $value;
				
				// Subject
				}elseif ($subject === null && preg_match("/subject/i", $key)){
					$subject = $value;
				
				// Message
				}else{
					$message[$prev_name.$key] = $value;					
				}
				
			}elseif(!is_object($value) && @get_class($value) != 'WP_User'){
				
				$prev_name_original = $prev_name;
				$prev_name = ($prev_name === '' ? $key.'_' : $prev_name.$key.'_');
				
				$temp = ct_get_fields_any($value, $message, $email, $nickname, $subject, $contact, $prev_name);
				
				$message 	= $temp['message'];
				$email 		= ($temp['email'] 		? $temp['email'] : null);
				$nickname 	= ($temp['nickname'] 	? $temp['nickname'] : null);				
				$subject 	= ($temp['subject'] 	? $temp['subject'] : null);
				if($contact === true)
					$contact = ($temp['contact'] === false ? false : true);
				$prev_name 	= $prev_name_original;
			}
		} unset($key, $value);
	}
	
    foreach ($skip_message_post as $v) {
        if (isset($_POST[$v])) {
            $message = null;
            break;
        }
    } unset($v);
	
	//If top iteration, returns compiled name field. Example: "Nickname Firtsname Lastname".
	if($prev_name === ''){
		if(!empty($nickname)){
			$nickname_str = '';
			foreach($nickname as $value){
				$nickname_str .= ($value ? $value." " : "");
			}unset($value);
		}
		$nickname = $nickname_str;
	}
	
    $return_param = array(
		'email' 	=> $email,
		'nickname' 	=> $nickname,
		'subject' 	=> $subject,
		'contact' 	=> $contact,
		'message' 	=> $message
	);	
	return $return_param;
}

/**
* Masks a value with asterisks (*)
* @return string
*/
function ct_obfuscate_param($value = null) {
    if ($value && (!is_object($value) || !is_array($value))) {
        $length = strlen($value);
        $value = str_repeat('*', $length);
    }

    return $value;
}

//New ct_get_fields_any_postdata
function ct_get_fields_any_postdata($arr, $message=array()){
	$skip_params = array(
	    'ipn_track_id', // PayPal IPN #
	    'txn_type', // PayPal transaction type
	    'payment_status', // PayPal payment status
    );
		
	foreach($arr as $key => $value){
		if(!is_array($value)){
			if($value == '')
				continue;
			if (!(in_array($key, $skip_params) || preg_match("/^ct_checkjs/", $key)) && $value!='')
	        	$message[$key] = $value;
		}else{
			$temp = ct_get_fields_any_postdata($value);
			$message = (count($temp) == 0 ? $message : array_merge($message, $temp));
		}
	}
	return $message;
}

/*
* Check if Array has keys with restricted names
*/

$ct_check_post_result=false;

function ct_check_array_keys_loop($key){
	
	global $ct_check_post_result;
	
	$strict = Array('members_search_submit');
	
	for($i=0;$i<sizeof($strict);$i++){
		
		if(stripos($key,$strict[$i])!== false)
			$ct_check_post_result = true;
		
	}
}

function ct_check_array_keys($arr){
	
	global $ct_check_post_result;
	
	if(!is_array($arr))
		return $ct_check_post_result;
	
	foreach($arr as $key=>$value){
		
		if(!is_array($value))
			ct_check_array_keys_loop($key);
		else
			ct_check_array_keys($value);
		
	}
	
	return $ct_check_post_result;
}

function check_url_exclusions($exclusions = NULL){
	
	global $cleantalk_url_exclusions;
	
	if ((isset($cleantalk_url_exclusions) && is_array($cleantalk_url_exclusions) && sizeof($cleantalk_url_exclusions)>0) ||
		($exclusions !== NULL && is_array($exclusions) && sizeof($exclusions)>0)
	){
		foreach($cleantalk_url_exclusions as $key => $value){
			if(stripos($_SERVER['REQUEST_URI'], $value) !== false){
				return true; 
			}
		}
	}
	
	return false;
}

function check_ip_exclusions($exclusions = NULL){
	
	global $cleantalk_ip_exclusions;
	
	if ((isset($cleantalk_ip_exclusions) && is_array($cleantalk_ip_exclusions) && sizeof($cleantalk_ip_exclusions)>0) ||
		($exclusions !== NULL && is_array($exclusions) && sizeof($exclusions)>0)
	){
		foreach($cleantalk_ip_exclusions as $key => $value){
			if(stripos($_SERVER['REMOTE_ADDR'], $value) !== false){
				return true; 
			}
		}
	}
	
	return false;
}

function ct_filter_array(&$array)
{
	global $cleantalk_key_exclusions;
	
	if(isset($cleantalk_key_exclusions) && sizeof($cleantalk_key_exclusions) > 0){
		
		foreach($array as $key => $value){
			
			if(!is_array($value)){
				if(in_array($key,$cleantalk_key_exclusions)){
					unset($array[$key]);
				}
			}else{
				$array[$key] = ct_filter_array($value);
			}
		}
		
		return $array;
		
	}else{
		return $array;
	}
}


function cleantalk_debug($key,$value)
{
	if(isset($_COOKIE) && isset($_COOKIE['cleantalk_debug']))
	{
		@header($key.": ".$value);
	}
}

/**
* Function changes CleanTalk result object if an error occured.
* @return object
*/ 
function ct_change_plugin_resonse($ct_result = null, $checkjs = null) {
    global $ct_plugin_name;

    if (!$ct_result) {
        return $ct_result;
    }
    
    if(@intval($ct_result->errno) != 0)
    {
    	if($checkjs === null || $checkjs != 1)
    	{
    		$ct_result->allow = 0;
    		$ct_result->spam = 1;
    		$ct_result->comment = sprintf('We\'ve got an issue: %s. Forbidden. Please, enable Javascript. %s.',
                $ct_result->comment,
                $ct_plugin_name
            );
    	}
    	else
    	{
    		$ct_result->allow = 1;
    		$ct_result->comment = 'Allow';
    	}
    }

    return $ct_result;
}

?>