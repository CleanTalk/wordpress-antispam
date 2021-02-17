<?php

use Cleantalk\Antispam\Cleantalk;
use Cleantalk\Antispam\CleantalkRequest;
use Cleantalk\Antispam\CleantalkResponse;
use Cleantalk\Variables\Cookie;

function apbct_array( $array ){
	return new \Cleantalk\Common\Arr( $array );
}

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

// Trial notice show time in minutes
$trial_notice_showtime = 10;

// Renew notice show time in minutes
$renew_notice_showtime = 10;

// COOKIE label for WP Landing Page proccessing result
$ct_wplp_result_label = 'ct_wplp_result';

// Flag indicates active JetPack comments 
$ct_jp_comments = false;

// WP admin email notice interval in seconds
$ct_admin_notoice_period = 21600;

// Sevice negative comment to visitor.
// It uses for BuddyPress registrations to avoid double checks
$ct_negative_comment = null;

// Set globals to NULL to avoid massive DB requests. Globals will be set when needed only and by accessors only.
$ct_server = NULL;
$admin_email = NULL;

/**
 * Public action 'plugins_loaded' - Loads locale, see http://codex.wordpress.org/Function_Reference/load_plugin_textdomain
 */
function apbct_plugin_loaded() {
	$dir=plugin_basename( dirname( __FILE__ ) ) . '/../i18n';
    $loaded=load_plugin_textdomain('cleantalk-spam-protect', false, $dir);
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

	global $apbct, $cleantalk_executed;
	
	/* Exclusions */
	if( $cleantalk_executed ){
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return array( 'ct_result' => new CleantalkResponse() );
    }
	
    // URL, IP, Role exclusions
    if( apbct_exclusions_check() ){
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return array( 'ct_result' => new CleantalkResponse() );
    }
    
    // Reversed url exclusions. Pass everything except one.
    if( apbct_exclusions_check__url__reversed() ){
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return array( 'ct_result' => new CleantalkResponse() );
    }
    
    // Fields exclusions
    if( ! empty( $params['message'] ) && is_array( $params['message'] ) ){
        $params['message'] = apbct_array( $params['message'] )
            ->get_keys( $apbct->settings['exclusions__fields'], $apbct->settings['exclusions__fields__use_regexp'] )
            ->delete();
    }
    /* End of Exclusions */
    
	$cleantalk_executed = true;
    
    /* Request ID rotation */
	$tmp = array();    
    if ($apbct->plugin_request_ids && !empty($apbct->plugin_request_ids)) {
		$plugin_request_id__lifetime = 2;
	    foreach( $apbct->plugin_request_ids as $request_id => $request_time ){
	    	if( time() - $request_time < $plugin_request_id__lifetime )
	    		$tmp[ $request_id ] = $request_time;
	    }
    }
	$apbct->plugin_request_ids = $tmp;
	$apbct->save('plugin_request_ids');
	
    // Skip duplicate requests
    if( key_exists( $apbct->plugin_request_id, $apbct->plugin_request_ids ) &&
        current_filter() !== 'woocommerce_registration_errors' && // Prevent skip checking woocommerce registration during checkout
        current_filter() !== 'um_submit_form_register' )          // Prevent skip checking UltimateMember register
    {
	    do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
	    return array( 'ct_result' => new CleantalkResponse() );
    }
    
    $apbct->plugin_request_ids = array_merge($apbct->plugin_request_ids, array($apbct->plugin_request_id => time() ) );
	$apbct->save('plugin_request_ids');
    /* End of Request ID rotation */
	
	
	$sender_info = !empty($params['sender_info'])
		? \Cleantalk\ApbctWP\Helper::array_merge__save_numeric_keys__recursive(apbct_get_sender_info(), (array)$params['sender_info'])
		: apbct_get_sender_info();
	
	$default_params = array(
		
		// IPs
		'sender_ip'       => defined('CT_TEST_IP')
            ? CT_TEST_IP
            : \Cleantalk\ApbctWP\Helper::ip__get('remote_addr', false),
		'x_forwarded_for' => \Cleantalk\ApbctWP\Helper::ip__get('x_forwarded_for', false),
		'x_real_ip'       => \Cleantalk\ApbctWP\Helper::ip__get('x_real_ip', false),
		
		// Misc
		'auth_key'        => $apbct->api_key,
		'js_on'           => apbct_js_test('ct_checkjs', $_COOKIE) ? 1 : apbct_js_test('ct_checkjs', $_POST),
		
		'agent'           => APBCT_AGENT,
		'sender_info'     => $sender_info,
		'submit_time'     => apbct_get_submit_time(),
	);
	
	// Send $_SERVER if couldn't find IP
	if(empty($default_params['sender_ip']))
		$default_params['sender_info']['server_info'] = $_SERVER;
	
	$ct_request = new CleantalkRequest(
		\Cleantalk\ApbctWP\Helper::array_merge__save_numeric_keys__recursive($default_params, $params)
	);
	
	$ct = new Cleantalk();

	$ct->use_bultin_api = $apbct->settings['use_buitin_http_api'] ? true : false;
	$ct->ssl_on         = $apbct->settings['ssl_on'];
	$ct->ssl_path       = APBCT_CASERT_PATH;
	
	// Options store url without shceme because of DB error with ''://'
	$config = ct_get_server();
	$ct->server_url     = APBCT_MODERATE_URL;
	$ct->work_url       = preg_match('/http:\/\/.+/', $config['ct_work_url']) ? $config['ct_work_url'] : null;
	$ct->server_ttl     = $config['ct_server_ttl'];
	$ct->server_changed = $config['ct_server_changed'];

	$start = microtime(true);
	$ct_result = $reg_flag
		? @$ct->isAllowUser($ct_request)
		: @$ct->isAllowMessage($ct_request);
	$exec_time = microtime(true) - $start;

	// Statistics
	// Average request time
	apbct_statistics__rotate($exec_time);
	// Last request
	$apbct->stats['last_request']['time'] = time();
	$apbct->stats['last_request']['server'] = $ct->work_url;
	$apbct->save('stats');

	// Connection reports
	if ($ct_result->errno === 0 && empty($ct_result->errstr))
        $apbct->data['connection_reports']['success']++;
    else
    {
        $apbct->data['connection_reports']['negative']++;
        $apbct->data['connection_reports']['negative_report'][] = array(
			'date' => date("Y-m-d H:i:s"),
			'page_url' => apbct_get_server_variable( 'REQUEST_URI' ),
			'lib_report' => $ct_result->errstr,
			'work_url' => $ct->work_url,
		);

		if(count($apbct->data['connection_reports']['negative_report']) > 20)
			$apbct->data['connection_reports']['negative_report'] = array_slice($apbct->data['connection_reports']['negative_report'], -20, 20);

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

    //Strip tags from comment
	$ct_result->comment = strip_tags($ct_result->comment, '<p><a><br>');

	// Set cookies if it's not.
	if(empty($apbct->flags__cookies_setuped))
		apbct_cookie();

    return array('ct' => $ct, 'ct_result' => $ct_result);
	
}

function apbct_exclusions_check($func = null){
	
	global $apbct;

	// Common exclusions
	if(
		apbct_exclusions_check__ip() ||
		apbct_exclusions_check__url() ||
		apbct_is_user_role_in( $apbct->settings['exclusions__roles'] )
	)
		return true;
	
	// Personal exclusions
	switch ($func){
		case 'ct_contact_form_validate_postdata':
			if(
				(defined( 'DOING_AJAX' ) && DOING_AJAX) ||
				apbct_array( $_POST )->get_keys( 'members_search_submit' )->result()
			)
				return true;
			break;
		case 'ct_contact_form_validate':
			if(
				apbct_array( $_POST )->get_keys( 'members_search_submit' )->result()
			)
				return true;
			break;
		default:
			return false;
			break;
	}
	
	return false;
}

/**
 * Check if the reversed exclusions is set and doesn't match.
 *
 * @return bool
 */
function apbct_exclusions_check__url__reversed(){
	return defined( 'APBCT_URL_EXCLUSIONS__REVERSED' ) &&
           ! \Cleantalk\Variables\Server::has_string( 'URI', APBCT_URL_EXCLUSIONS__REVERSED );
}

/**
 * Checks if reuqest URI is in exclusion list
 *
 * @return bool
 */
function apbct_exclusions_check__url() {
	
	global $apbct;
	
	if ( ! empty( $apbct->settings['exclusions__urls'] ) ) {

	    if( strpos( $apbct->settings['exclusions__urls'], "\r\n" ) !== false ) {
            $exclusions = explode( "\r\n", $apbct->settings['exclusions__urls'] );
        } elseif( strpos( $apbct->settings['exclusions__urls'], "\n" ) !== false ) {
            $exclusions = explode( "\n", $apbct->settings['exclusions__urls'] );
        } else {
            $exclusions = explode( ',', $apbct->settings['exclusions__urls'] );
        }

		// Fix for AJAX forms
		$haystack = apbct_get_server_variable( 'REQUEST_URI' ) == '/wp-admin/admin-ajax.php' && ! apbct_get_server_variable( 'HTTP_REFERER' )
			? apbct_get_server_variable( 'HTTP_REFERER' )
			: \Cleantalk\Variables\Server::get('HTTP_HOST') . apbct_get_server_variable( 'REQUEST_URI' );

		foreach ( $exclusions as $exclusion ) {
			if (
				($apbct->settings['exclusions__urls__use_regexp'] && preg_match( '@' . $exclusion . '@', $haystack ) === 1) ||
				stripos( $haystack, $exclusion ) !== false
			){
				return true;
			}
		}
		return false;
	}
}
/**
 * @deprecated 5.128 Using IP white-lists instead
 * @deprecated since 18.09.2019
 * Checks if sender_ip is in exclusion list
 *
 * @return bool
 */
function apbct_exclusions_check__ip(){
	
	global $cleantalk_ip_exclusions;
	
	if( apbct_get_server_variable( 'REMOTE_ADDR' ) ){
		
		if( \Cleantalk\ApbctWP\Helper::ip__is_cleantalks( apbct_get_server_variable( 'REMOTE_ADDR' ) ) ){
			return true;
		}
		
		if( ! empty( $cleantalk_ip_exclusions ) && is_array( $cleantalk_ip_exclusions ) ){
			foreach ( $cleantalk_ip_exclusions as $exclusion ){
				if( stripos( apbct_get_server_variable( 'REMOTE_ADDR' ), $exclusion ) !== false ){
					return true;
				}
			}
		}
	}
	
	return false;
}

/**
 * Inner function - Default data array for senders 
 * @return array 
 */
function apbct_get_sender_info() {
	
	global $apbct;
	
	// Validate cookie from the backend
	$cookie_is_ok = apbct_cookies_test();
    
	$referer_previous = $apbct->settings['set_cookies__sessions']
			? apbct_alt_session__get('apbct_prev_referer')
			: filter_input(INPUT_COOKIE, 'apbct_prev_referer');
	
	$site_landing_ts = $apbct->settings['set_cookies__sessions']
			? apbct_alt_session__get('apbct_site_landing_ts')
			: filter_input(INPUT_COOKIE, 'apbct_site_landing_ts');
	
	$page_hits = $apbct->settings['set_cookies__sessions']
			? apbct_alt_session__get('apbct_page_hits')
			: filter_input(INPUT_COOKIE, 'apbct_page_hits');
		
	if (count($_POST) > 0) {
		foreach ($_POST as $k => $v) {
			if (preg_match("/^(ct_check|checkjs).+/", $k)) {
        		$checkjs_data_post = $v; 
			}
		}
	}
	
	// AMP check
	$amp_detected = apbct_get_server_variable( 'HTTP_REFERER' )
		? strpos(apbct_get_server_variable( 'HTTP_REFERER' ), '/amp/') !== false || strpos(apbct_get_server_variable( 'HTTP_REFERER' ), '?amp=1') !== false || strpos(apbct_get_server_variable( 'HTTP_REFERER' ), '&amp=1') !== false
			? 1
			: 0
		: null;
	
	$site_referer = $apbct->settings['store_urls__sessions']
			? apbct_alt_session__get('apbct_site_referer')
			: filter_input(INPUT_COOKIE, 'apbct_site_referer');
	
	$urls = $apbct->settings['store_urls__sessions']
			? (array)apbct_alt_session__get('apbct_urls')
			: (array)json_decode(filter_input(INPUT_COOKIE, 'apbct_urls'), true);

	// Visible fields processing
    $visible_fields = apbct_visibile_fields__process( Cookie::get('apbct_visible_fields') );

	return array(
		'plugin_request_id'      => $apbct->plugin_request_id,
 		'wpms'                   => is_multisite() ? 'yes' : 'no',
		'remote_addr'            => \Cleantalk\ApbctWP\Helper::ip__get('remote_addr', false),
        'REFFERRER'              => apbct_get_server_variable( 'HTTP_REFERER' ),
        'USER_AGENT'             => apbct_get_server_variable( 'HTTP_USER_AGENT' ),
		'page_url'               => apbct_get_server_variable( 'SERVER_NAME' ) . apbct_get_server_variable( 'REQUEST_URI' ),
        'cms_lang'               => substr(get_locale(), 0, 2),
        'ct_options'             => json_encode($apbct->settings),
        'fields_number'          => sizeof($_POST),
        'direct_post'            => $cookie_is_ok === null && apbct_is_post() ? 1 : 0,
		// Raw data to validated JavaScript test in the cloud                                                                                                          
        'checkjs_data_cookies'   => !empty($_COOKIE['ct_checkjs'])                                 ? $_COOKIE['ct_checkjs']                                            : null, 
        'checkjs_data_post'      => !empty($checkjs_data_post)                                     ? $checkjs_data_post                                                : null, 
		// PHP cookies                                                                                                                                                 
        'cookies_enabled'        => $cookie_is_ok,                                                                                                                     
		'REFFERRER_PREVIOUS'     => !empty($referer_previous) && $cookie_is_ok                     ? $referer_previous                                                 : null,
		'site_landing_ts'        => !empty($site_landing_ts) && $cookie_is_ok                      ? $site_landing_ts                                                  : null,
		'page_hits'              => !empty($page_hits)                                             ? $page_hits                                                        : null,
		// JS cookies                                                                                                                                                  
        'js_info'                => !empty($_COOKIE['ct_user_info'])                               ? json_decode(stripslashes($_COOKIE['ct_user_info']), true)         : null,
		'mouse_cursor_positions' => !empty($_COOKIE['ct_pointer_data'])                            ? json_decode(stripslashes($_COOKIE['ct_pointer_data']), true)      : null,
		'js_timezone'            => !empty($_COOKIE['ct_timezone'])                                ? $_COOKIE['ct_timezone']                                           : null,
		'key_press_timestamp'    => !empty($_COOKIE['ct_fkp_timestamp'])                           ? $_COOKIE['ct_fkp_timestamp']                                      : null,
		'page_set_timestamp'     => !empty($_COOKIE['ct_ps_timestamp'])                            ? $_COOKIE['ct_ps_timestamp']                                       : null,
		'form_visible_inputs'    => !empty($visible_fields['visible_fields_count'])                ? $visible_fields['visible_fields_count']                           : null,
		'apbct_visible_fields'   => !empty($visible_fields['visible_fields'])                      ? $visible_fields['visible_fields']                                 : null,
		// Misc
		'site_referer'           => !empty($site_referer)                                          ? $site_referer                                                     : null,
		'source_url'             => !empty($urls)                                                  ? json_encode($urls)                                                : null,
		// Debug stuff
		'amp_detected'           => $amp_detected,
		'hook'                   => current_filter()                    ? current_filter()            : 'no_hook',
		'headers_sent'           => !empty($apbct->headers_sent)        ? $apbct->headers_sent        : false,
		'headers_sent__hook'     => !empty($apbct->headers_sent__hook)  ? $apbct->headers_sent__hook  : 'no_hook',
		'headers_sent__where'    => !empty($apbct->headers_sent__where) ? $apbct->headers_sent__where : false,
		'request_type'           => apbct_get_server_variable('REQUEST_METHOD') ? apbct_get_server_variable('REQUEST_METHOD') : 'UNKNOWN',
	);
}

/**
 * Process visible fields for specific form to match the fields from request
 * 
 * @param string $visible_fields JSON string
 * 
 * @return array
 */
function apbct_visibile_fields__process( $visible_fields ) {

    $fields_collection = json_decode( $visible_fields, true );

    if( ! empty( $fields_collection ) ) {
        foreach ($fields_collection as $current_fields) {
            if( isset( $current_fields['visible_fields'] ) && isset( $current_fields['visible_fields_count'] ) ) {

                $fields = explode( ' ', $current_fields['visible_fields'] );

                // This fields belong this request
                // @ToDo we have to implement a logic to find form fields (fields names, fields count) in serialized/nested/encoded items. not only $_POST.
                if( count( array_intersect( array_keys($_POST), $fields ) ) > 0 ) {
                    // WP Forms visible fields formatting
                    if(strpos($visible_fields, 'wpforms') !== false){
                        $visible_fields = preg_replace(
                            array('/\[/', '/\]/'),
                            '',
                            str_replace(
                                '][',
                                '_',
                                str_replace(
                                    'wpforms[fields]',
                                    '',
                                    $visible_fields
                                )
                            )
                        );
                    }

                    return $current_fields;

                }
            }
        }
    }
	
	return array();
}

/*
 * Outputs JS key for AJAX-use only. Stops script.
 */
function apbct_js_keys__get__ajax( $direct_call = false ){
	
	die(json_encode(array(
		'js_key' => ct_get_checkjs_value()
	)));

}

/**
 * Get ct_get_checkjs_value
 *
 * @param bool $random_key
 *
 * @return int|string|null
 */
function ct_get_checkjs_value(){
	
    global $apbct;
    
    // Use static JS keys
	if($apbct->settings['use_static_js_key'] == 1){
		
		$key = hash('sha256', $apbct->api_key.ct_get_admin_email().$apbct->salt);
		
	// Auto detecting. Detected.
	}elseif(
		$apbct->settings['use_static_js_key'] == - 1 &&
		  ( apbct_is_cache_plugins_exists() ||
		    ( apbct_is_post() && $apbct->data['cache_detected'] == 1 )
		  )
	){
	    $key = hash('sha256', $apbct->api_key.ct_get_admin_email().$apbct->salt);
	    if( apbct_is_cache_plugins_exists() )
		    $apbct->data['cache_detected'] = 1;
	
    // Using dynamic JS keys
    }else{
		
        $keys = $apbct->data['js_keys'];
        $keys_checksum = md5(json_encode($keys));
        
        $key = null;
        $latest_key_time = 0;
        
        foreach ($keys as $k => $t) {

            // Removing key if it's to old
            if (time() - $t > $apbct->data['js_keys_store_days'] * 86400 * 7) {
                unset($keys[$k]);
                continue;
            }

            if ($t > $latest_key_time) {
                $latest_key_time = $t;
                $key = $k;
            }
        }
        
        // Set new key if the latest key is too old
        if (time() - $latest_key_time > $apbct->data['js_key_lifetime']) {
            $key = rand();
            $keys[$key] = time();
        }
        
        // Save keys if they were changed
        if (md5(json_encode($keys)) != $keys_checksum) {
            $apbct->data['js_keys'] = $keys;
            // $apbct->saveData();
        }
		
		$apbct->data['cache_detected'] = 0;
    }

	$apbct->saveData();
	
    return $key; 
}

function apbct_is_cache_plugins_exists(){
	return
		defined('WP_ROCKET_VERSION') ||                           // WPRocket
		defined('LSCWP_DIR') ||                                   // LiteSpeed Cache
		defined('WPFC_WP_CONTENT_BASENAME') ||                    // WP Fastest Cache
		defined('W3TC') ||                                        // W3 Total Cache
		defined('WPO_VERSION') ||                                 // WP-Optimize – Clean, Compress, Cache
		defined('AUTOPTIMIZE_PLUGIN_VERSION') ||                  // Autoptimize
		defined('WPCACHEHOME') ||                                 // WP Super Cache
		defined('WPHB_VERSION') ||                                // Hummingbird – Speed up, Cache, Optimize Your CSS and JS
		defined('CE_FILE') ||                                     // Cache Enabler – WordPress Cache
		class_exists('\RedisObjectCache') ||                   // Redis Object Cache
		defined('SiteGround_Optimizer\VERSION') ||                // SG Optimizer
		class_exists('\WP_Rest_Cache_Plugin\Includes\Plugin'); // WP REST Cache
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
	global $apbct;
	
    $ct_feedback = $hash . ':' . $allow . ';';
    if( ! $apbct->data['feedback_request'] )
		$apbct->data['feedback_request'] = $ct_feedback;
    else
		$apbct->data['feedback_request'] .= $ct_feedback;
	
	$apbct->saveData();
}

/**
 * Inner function - Sends the results of moderation
 * Scheduled in 3600 seconds!
 * @param string $feedback_request
 * @return bool
 */
function ct_send_feedback($feedback_request = null) {
	
	global $apbct;
	
    if (empty($feedback_request) && isset($apbct->data['feedback_request']) && preg_match("/^[a-z0-9\;\:]+$/", $apbct->data['feedback_request'])){
		$feedback_request = $apbct->data['feedback_request'];
		$apbct->data['feedback_request'] = '';
		$apbct->saveData();
    }
	
    if ($feedback_request !== null) {
				
        $ct_request = new CleantalkRequest(array(
			// General
			'auth_key' => $apbct->api_key,
			// Additional
			'feedback' => $feedback_request,
		));
		
        $ct = new Cleantalk();
		
		// Server URL handling
		$config = ct_get_server();
		$ct->server_url     = APBCT_MODERATE_URL;
		$ct->work_url       = preg_match('/http:\/\/.+/', $config['ct_work_url']) ? $config['ct_work_url'] : null;
		$ct->server_ttl     = $config['ct_server_ttl'];
		$ct->server_changed = $config['ct_server_changed'];
				
        $ct->sendFeedback($ct_request);
		
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
	
    global $apbct;
    
    if ($apbct->settings['remove_old_spam'] == 1) {
        $last_comments = get_comments(array('status' => 'spam', 'number' => 1000, 'order' => 'ASC'));
        foreach ($last_comments as $c) {
        	$comment_date_gmt = strtotime($c->comment_date_gmt);
        	if ($comment_date_gmt && is_numeric($comment_date_gmt)) {
	            if (time() - $comment_date_gmt > 86400 * $apbct->data['spam_store_days']) {
	                // Force deletion old spam comments
	                wp_delete_comment($c->comment_ID, true);
	            }         		
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
		'referer-page',
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
		'signature',
		// Ultimate Form Builder
		'form_data_%d_name',
	);
	
	// Reset $message if we have a sign-up data
    $skip_message_post = array(
        'edd_action', // Easy Digital Downloads
    );
	
   	if( apbct_array( array( $_POST, $_GET ) )->get_keys( $skip_params )->result() )
        $contact = false;
	
	if(count($arr)){
		
		foreach($arr as $key => $value){
						
			if(gettype($value) == 'string'){
				
				$tmp = strpos($value, '\\') !== false ? stripslashes($value) : $value;
				$decoded_json_value = json_decode($tmp, true);
				
				// Decoding JSON
				if($decoded_json_value !== null){
					$value = $decoded_json_value;
					
				// Ajax Contact Forms. Get data from such strings:
					// acfw30_name %% Blocked~acfw30_email %% s@cleantalk.org
					// acfw30_textarea %% msg
				}elseif(preg_match('/^\S+\s%%\s\S+.+$/', $value)){
					$value = explode('~', $value);
					foreach ($value as &$val){
						$tmp = explode(' %% ', $val);
						$val = array($tmp[0] => $tmp[1]);
					}
				}
			}
			
			if(!is_array($value) && !is_object($value)){
				
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

                $value_for_email = trim( strip_shortcodes( $value ) );    // Removes shortcodes to do better spam filtration on server side.
				
				// Email
				if ( ! $email && preg_match( "/^\S+@\S+\.\S+$/", $value_for_email ) ) {
					$email = $value_for_email;

                // Removes whitespaces
                $value = urldecode( trim( strip_shortcodes( $value ) ) ); // Fully cleaned message
					
				// Names
				}elseif (preg_match("/name/i", $key)){
					
					preg_match("/((name.?)?(your|first|for)(.?name)?)/", $key, $match_forename);
					preg_match("/((name.?)?(last|family|second|sur)(.?name)?)/", $key, $match_surname);
					preg_match("/(name.?)?(nick|user)(.?name)?/", $key, $match_nickname);
					
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
				
			}elseif(!is_object($value)){
				
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

/**
 * Checks if given string is valid regular expression
 *
 * @param string $regexp
 *
 * @return bool
 */
function apbct_is_regexp($regexp){
	return @preg_match('/' . $regexp . '/', null) !== false;
}

function cleantalk_debug($key,$value)
{
	if(isset($_COOKIE) && isset($_COOKIE['cleantalk_debug']))
	{
		@header($key.": ".$value);
	}
}

/**
* Function changes CleanTalk result object if an error occurred.
* @return object
*/ 
function ct_change_plugin_resonse($ct_result = null, $checkjs = null) {
	
	global $apbct;
	
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
                $apbct->plugin_name
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

/**
* Does ey has correct symbols? Checks against regexp ^[a-z\d]{3,15}$
* @param api_key
* @return bool
*/
function apbct_api_key__is_correct($api_key = null)
{
	global $apbct;
	$api_key = $api_key !== null ? $api_key : $apbct->api_key;
    return $api_key && preg_match('/^[a-z\d]{3,15}$/', $api_key) ? true : false;
}

function apbct_add_async_attribute($tag, $handle, $src) {
	
	global $apbct;
	
    if(
    	$handle === 'ct_public' ||
	    $handle === 'ct_public_gdpr' ||
	    $handle === 'ct_debug_js' ||
	    $handle === 'ct_public_admin_js' ||
	    $handle === 'ct_internal' ||
	    $handle === 'ct_external' ||
	    $handle === 'ct_nocache'
	){
    	if( $apbct->settings['async_js'] )
	        $tag = str_replace( ' src', ' async="async" src', $tag );
	    
	    if( class_exists('Cookiebot_WP') )
		    $tag = str_replace( ' src', ' data-cookieconsent="ignore" src', $tag );
    }
    
    return $tag;
}