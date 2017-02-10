<?php

$ct_plugin_name = 'Spam Protection by CleanTalk';
$ct_checkjs_frm = 'ct_checkjs_frm';
$ct_checkjs_register_form = 'ct_checkjs_register_form';
$ct_session_request_id_label = 'request_id';
$ct_session_register_ok_label = 'register_ok';

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

// Timeout before new check for trial notice in hours
$trial_notice_check_timeout = 1;

// Timeout before new check account notice in hours
$account_notice_check_timeout = 24;

// Timeout before new check account notice in hours
$renew_notice_check_timeout = 0.5;

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

// Form time load label  
$ct_formtime_label = 'ct_formtime'; 

// Post without page load
$ct_direct_post = 0;

// WP admin email notice interval in seconds
$ct_admin_notoice_period = 10800;

// Sevice negative comment to visitor.
// It uses for BuddyPress registrations to avoid double checks
$ct_negative_comment = null;

// Flag to show apikey automatic getting error
$show_ct_notice_autokey = false;

// Apikey automatic getting label  
$ct_notice_autokey_label = 'ct_autokey'; 

// Apikey automatic getting error text
$ct_notice_autokey_value = '';

$ct_feedback_requests_pool = array();

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
function ct_plugin_loaded() {
	$dir=plugin_basename( dirname( __FILE__ ) ) . '/../i18n';
    $loaded=load_plugin_textdomain('cleantalk', false, $dir);
}

/**
 * Session init
 * @return null;
 */
function ct_init_session() {

    $session_id = session_id(); 
    if(empty($session_id) && !headers_sent()) {
        $result = @session_start();
        if(!$result){
            session_regenerate_id(true); // replace the Session ID, bug report https://bugs.php.net/bug.php?id=68063
            @session_start(); 
        }    
    }

    return null;
}

/**
 * Inner function - Common part of request sending
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
function ct_base_call($params = array()) {
    global $wpdb, $ct_agent_version, $ct_formtime_label, $ct_options, $ct_data;

    $ct_options=ct_get_options();
	$ct_data=ct_get_data();
	
    require_once('cleantalk.class.php');
        
    $submit_time = submit_time_test();

    $sender_info = get_sender_info();
    if (array_key_exists('sender_info', $params)) {
	    $sender_info = array_merge($sender_info, (array) $params['sender_info']);
    }
		
	$sender_info['page_hits'] = (isset($_SESSION['ct_page_hits']) ? $_SESSION['ct_page_hits'] : 0);
		
    $sender_info = json_encode($sender_info);
    if ($sender_info === false)
        $sender_info = '';
	
    $config = ct_get_server();

    $ct = new Cleantalk();
    $ct->work_url = $config['ct_work_url'];
    $ct->server_url = $ct_options['server'];
    
    $ct->server_ttl = $config['ct_server_ttl'];
    $ct->server_changed = $config['ct_server_changed'];
    $ct->ssl_on = $ct_options['ssl_on'];

    $ct_request = new CleantalkRequest();

    $ct_request->auth_key = $ct_options['apikey'];
    $ct_request->message = $params['message'];
    $ct_request->example = $params['example'];
    $ct_request->sender_email = $params['sender_email'];
    $ct_request->sender_nickname = $params['sender_nickname'];
    $ct_request->sender_ip = $ct->ct_session_ip($_SERVER['REMOTE_ADDR']);
    $ct_request->agent = $ct_agent_version;
    $ct_request->sender_info = $sender_info;
    $ct_request->js_on = $params['checkjs'];
    $ct_request->submit_time = $submit_time;
    $ct_request->post_info = $params['post_info'];
    if(isset($ct_data['last_error_no']))
    {
    	$ct_request->last_error_no=$ct_data['last_error_no'];
    	$ct_request->last_error_time=$ct_data['last_error_time'];
    	$ct_request->last_error_text=$ct_data['last_error_text'];
    }    
    

    $ct_result = @$ct->isAllowMessage($ct_request);
    if ($ct->server_change) {
        update_option(
                'cleantalk_server', array(
                'ct_work_url' => $ct->work_url,
                'ct_server_ttl' => $ct->server_ttl,
                'ct_server_changed' => time()
                )
        );
    }
    
    $ct_result = ct_change_plugin_resonse($ct_result, $params['checkjs']);
     
    // Restart submit form counter for failed requests
    if ($ct_result->allow == 0) {
        ct_init_session();

        $_SESSION[$ct_formtime_label] = time();
       	ct_add_event('no');
    }
    else
    {
       	ct_add_event('yes');
    }
    return array('ct' => $ct, 'ct_result' => $ct_result);
}

/**
 * Validate form submit time 
 *
 */
function submit_time_test() {
    global $ct_formtime_label;
    
    ct_init_session();

    $submit_time = null;
    if (isset($_SESSION[$ct_formtime_label])) {
        $submit_time = time() - (int) $_SESSION[$ct_formtime_label];
    }

    return $submit_time;
}

/**
 * Inner function - Default data array for senders 
 * @return array 
 */
function get_sender_info() {
    global $ct_direct_post, $ct_options, $ct_data, $wp_rewrite, $ct_formtime_label;
    
    $ct_options = ct_get_options();
    $ct_data = ct_get_data();

    $php_session = session_id() != '' ? 1 : 0;
    
    // Raw data to validated JavaScript test in the cloud
    $checkjs_data_cookies = null; 
    if (isset($_COOKIE['ct_checkjs'])) {
        $checkjs_data_cookies = $_COOKIE['ct_checkjs'];
    }
    
	
	$checkjs_data_post = null;
	if (count($_POST) > 0) {
		foreach ($_POST as $k => $v) {
			if (preg_match("/^ct_check.+/", $k)) {
        		$checkjs_data_post = $v; 
			}
		}
	}
	
	$options2server=$ct_options;
	$js_info='';
	if(isset($_COOKIE['ct_user_info']) && function_exists('mb_convert_encoding'))
    {
    	$js_info=stripslashes(rawurldecode($_COOKIE['ct_user_info']));
    	$js_info=mb_convert_encoding($js_info, "UTF-8", "Windows-1252");
    }
    

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        ct_init_session();
        if (isset($_SESSION) && is_array($_SESSION) && !array_key_exists($ct_formtime_label, $_SESSION) && session_id() != '') {
            $ct_direct_post = 1;
        }
    }
    
	return $sender_info = array(
	'page_url' => htmlspecialchars(@$_SERVER['SERVER_NAME'].@$_SERVER['REQUEST_URI']),
        'cms_lang' => substr(get_locale(), 0, 2),
        'REFFERRER' => htmlspecialchars(@$_SERVER['HTTP_REFERER']),
        'USER_AGENT' => htmlspecialchars(@$_SERVER['HTTP_USER_AGENT']),
        'php_session' => $php_session, 
        'cookies_enabled' => ct_cookies_test(true), 
        'direct_post' => $ct_direct_post,
        'checkjs_data_post' => $checkjs_data_post, 
        'checkjs_data_cookies' => $checkjs_data_cookies, 
        'ct_options' => json_encode($options2server),
        'fields_number' => sizeof($_POST),
        'js_info' => $js_info,
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
	if(!$force && isset($ct_server) && isset($ct_server['ct_work_url']) && !empty($ct_server['ct_work_url']))
	{
		return $ct_server;
	}
	else
	{
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
		//Data processing
        'protect_logged_in' => '-1', // Do anit-spam tests to for logged in users.
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
        'collect_details' => 0 // Collect details about browser of the visitor. 
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
        'next_account_status_check' => 0, // Time label when the plugin should check account status 
        'user_token' => '', // User token 
        'js_keys' => array(), // Keys to do JavaScript antispam test 
        'js_keys_store_days' => 14, // JavaScript keys store days - 8 days now
        'js_key_lifetime' => 86400, // JavaScript key life time in seconds - 1 day now
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
function ct_feedback($hash, $message = null, $allow) {
    global $ct_options, $ct_data;
    
    $ct_options = ct_get_options();
    $ct_data = ct_get_data();

    require_once('cleantalk.class.php');

    $config = ct_get_server();

    $ct = new Cleantalk();
    $ct->work_url = $config['ct_work_url'];
    $ct->server_url = $ct_options['server'];
    $ct->server_ttl = $config['ct_server_ttl'];
    $ct->server_changed = $config['ct_server_changed'];

    if (empty($hash)) {
	    $hash = $ct->getCleantalkCommentHash($message);
    }
    
    $resultMessage = null;
    if ($message !== null) {
        $resultMessage = $ct->delCleantalkComment($message);
    }
    
    ct_init_session();

    $ct_feedback = $hash . ':' . $allow . ';';
    if (empty($_SESSION['feedback_request'])) {
	$_SESSION['feedback_request'] = $ct_feedback; 
    } else {
	$_SESSION['feedback_request'] .= $ct_feedback; 
    }

    return $resultMessage;
}

/**
 * Inner function - Sends the results of moderation
 * @param string $feedback_request
 * @return bool
 */
function ct_send_feedback($feedback_request = null) {
    global $ct_options, $ct_data, $ct_feedback_requests_pool;
    
    $ct_options = ct_get_options();
    $ct_data = ct_get_data();
    
    ct_init_session();

    if (empty($feedback_request) && isset($_SESSION['feedback_request']) && preg_match("/^[a-z0-9\;\:]+$/", $_SESSION['feedback_request'])) {
	$feedback_request = $_SESSION['feedback_request'];
	unset($_SESSION['feedback_request']);
    }

    if ($feedback_request !== null) {
        if (in_array($feedback_request, $ct_feedback_requests_pool)) { // The request already sent.
            return false;
        } else {
            $ct_feedback_requests_pool[] = $feedback_request;
        }

        require_once('cleantalk.class.php');
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
 * On the scheduled action hook, run the function.
 */
function ct_do_this_hourly() {
    global $ct_options, $ct_data;
    
    $ct_options = ct_get_options();
    $ct_data = ct_get_data();
    // do something every hour

    if (!isset($ct_options))
	$ct_options = ct_get_options();

    if (!isset($ct_data))
	$ct_data = ct_get_data();

    delete_spam_comments();
    ct_send_feedback();
}

/**
 * Delete old spam comments 
 * @return null 
 */
function delete_spam_comments() {
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
function ct_get_fields_any($arr, $message=array(), $email=null, $nickname=null, $subject=null, $contact=true, $prev_name='') {
	$skip_params = array( //Skip request if fields exists
	    'ipn_track_id', // PayPal IPN #
	    'txn_type', // PayPal transaction type
	    'payment_status', // PayPal payment status
	    'ccbill_ipn', //CCBill IPN 
		'ct_checkjs' //skip ct_checkjs field
    );
    $obfuscate_params = array( //Fields to replace with ****
        'password',
        'pass',
        'pwd',
		'pswd'
    );
	$skip_fields_params = array( //Array for known service fields
	//Common
		'ct_checkjs',
	//Custom Contact Forms
		'form_id',
		'form_nonce',
		'ccf_form',
		'form_page',
		'form_nonce',
	//Qu Forms
		'iphorm_uid',
		'form_url',
		'post_id',
		'iphorm_ajax',
		'iphorm_id',
	//Fast SecureContact Froms
		'fs_postonce_1',
		'fscf_submitted',
		'mailto_id',
		'si_contact_action'
	);
	
   	foreach($skip_params as $value){
   		if(@array_key_exists($value,$_GET)||@array_key_exists($value,$_POST))
   			$contact = false;
   	} unset($value);
		
	if(count($arr)){
		foreach($arr as $key => $value){
			if(!is_array($value) && !is_object($value) && @get_class($value)!='WP_User'){
				
				// Skip empty or work fields execept 0 feild
				if($value==='' || in_array($key, $skip_fields_params, true)){
					continue;
				}
				
				foreach($obfuscate_params as $needle){
					if (strpos($key, $needle) !== false){
						$value = ct_obfuscate_param($value);
						$message[$key] = $value;
						continue;
					}
				}unset($needle);
				
				// Removes shortcodes to do better spam filtration on server side.
				$value = strip_shortcodes($value);
				
				if (in_array($key, $skip_params, true) && $key!=0 && $key!='' || preg_match("/^ct_checkjs/", $key)){
					$contact = false;
				}

				if (!$email && @preg_match("/^\S+@\S+\.\S+$/", $value)){
					$email = $value;
				}elseif ($nickname === null && ct_get_data_from_submit($key, 'name')){
					$nickname .= " ".$value;
				}elseif ($subject === null && ct_get_data_from_submit($key, 'subject')){
					$subject = $value;
				}else{
					$message[$prev_name.$key] = $value;					
				}
				
			}else if(!is_object($value)&&@get_class($value)!='WP_User'){
				
				$prev_name_original = $prev_name;
				$prev_name = ($prev_name === '' ? $key.'_' : $prev_name.$key.'_');
				
				$temp = ct_get_fields_any($value, $message, $email, $nickname, $subject, $contact, $prev_name);
				
				$prev_name = $prev_name_original;
				
				$email = ($temp['email'] ? $temp['email'] : null);
				$nickname = ($temp['nickname'] ? $temp['nickname'] : null);
				$subject = ($temp['subject'] ? $temp['subject'] : null);
				if($contact===true)
					$contact = ($temp['contact']===false ? false : true);
				$message = $temp['message'];
			}
		} unset($key, $value);
	}
	
	// Deleting repeats values
	$message = array_unique($message);
	
	// Reset $message if we have a sign-up data
    $skip_message_post = array(
        'edd_action', // Easy Digital Downloads
    );
    foreach ($skip_message_post as $v) {
        if (isset($_POST[$v])) {
            $message = null;
            break;
        }
    } unset($v);

    $return_param = array(
		'email' => $email,
		'nickname' => $nickname,
		'subject' => $subject,
		'contact' => $contact,
		'message' => $message
	);	
	return $return_param;
}

/**
* Masks a value with asterisks (*)
* @return string
*/
function ct_obfuscate_param ($value = null) {
    if ($value && (!is_object($value) || !is_array($value))) {
        $length = strlen($value);
        $value = str_repeat('*', $length);
    }

    return $value;
}
/* //OLD ct_get_fields_any_postdata
function ct_get_fields_any_postdata(&$message,$arr)
{
	$skip_params = array(
	    'ipn_track_id', // PayPal IPN #
	    'txn_type', // PayPal transaction type
	    'payment_status', // PayPal payment status
    );
	foreach($arr as $key=>$value)
	{
		if(!is_array($value))
		{
			if (in_array($key, $skip_params) || preg_match("/^ct_checkjs/", $key)) {
                //$contact = false;
            }
            else
	        {
	        	$message.="$value\n";
	        }
		}
		else
		{
			@ct_get_fields_any_postdata($message, $value);
		}
	}
}
//*/
//New ct_get_fields_any_postdata
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
	
	if((isset($cleantalk_url_exclusions) && is_array($cleantalk_url_exclusions) && sizeof($cleantalk_url_exclusions)>0) || ($exclusions !== NULL && is_array($exclusions) && sizeof($exclusions)>0)){
		foreach($cleantalk_url_exclusions as $key => $value){
			if(stripos($_SERVER['REQUEST_URI'], $value) !== false){
				return true;
			}
		}
	}
	
	return false;
}

function ct_filter_array(&$array)
{
	global $cleantalk_key_exclusions;
	if(isset($cleantalk_key_exclusions) && sizeof($cleantalk_key_exclusions)>0)
	{
		foreach($array as $key=>$value)
		{
			if(!is_array($value))
			{
				if(in_array($key,$cleantalk_key_exclusions))
				{
					unset($array[$key]);
				}
			}
			else
			{
				$array[$key]=ct_filter_array($value);
			}
		}
		return $array;
	}
	else
	{
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
