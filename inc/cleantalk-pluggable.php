<?php

/**
 * Getting current user by cookie
 *
 * @return WP_User|null
 */
function apbct_wp_get_current_user(){
	
	global $apbct, $current_user;
	
	$user = null;
	
	if(!(defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)){
		
		if(!empty($apbct->user)){
			$user_id = is_object($current_user) && isset($current_user->ID) && !($current_user instanceof WP_User)
				? $current_user->ID
				: null;
		}else{
			$user_id = empty($user_id) && defined('LOGGED_IN_COOKIE') && !empty($_COOKIE[LOGGED_IN_COOKIE])
				? apbct_wp_validate_auth_cookie($_COOKIE[LOGGED_IN_COOKIE], 'logged_in')
				: null;
		}
		
		if($user_id){
			$user = new WP_User($user_id);
		}
		
	}
	
	return $user ? $user : $current_user;
}

function apbct_wp_set_current_user($user = null){
	
	global $apbct;
	
	if( $user instanceof WP_User ){
		$apbct->user = $user;
		return true;
	}
	
	return false;
}

/**
 * Validates authentication cookie.
 *
 * The checks include making sure that the authentication cookie is set and
 * pulling in the contents (if $cookie is not used).
 *
 * Makes sure the cookie is not expired. Verifies the hash in cookie is what is
 * should be and compares the two.
 *
 * @param string $cookie Optional. If used, will validate contents instead of cookie's
 * @param string $scheme Optional. The cookie scheme to use: auth, secure_auth, or logged_in
 *
 * @return false|int False if invalid cookie, User ID if valid.
 * @global int   $login_grace_period
 *
 */
function apbct_wp_validate_auth_cookie( $cookie = '', $scheme = '' ) {
	
	$cookie_elements = apbct_wp_parse_auth_cookie($cookie, $scheme);
	
	$scheme = $cookie_elements['scheme'];
	$username = $cookie_elements['username'];
	$hmac = $cookie_elements['hmac'];
	$token = $cookie_elements['token'];
	$expiration = $cookie_elements['expiration'];
	
	// Allow a grace period for POST and Ajax requests
	$expired = apbct_is_ajax() || 'POST' == filter_input(INPUT_SERVER, 'REQUEST_METHOD')
		? $expiration + HOUR_IN_SECONDS
		: $cookie_elements['expiration'];
	
	// Quick check to see if an honest cookie has expired
	if($expired >= time()){
		$user = apbct_wp_get_user_by('login', $username);
		if($user){
			$pass_frag = substr($user->user_pass, 8, 4);
			$key = apbct_wp_hash($username . '|' . $pass_frag . '|' . $expiration . '|' . $token, $scheme);
			// If ext/hash is not present, compat.php's hash_hmac() does not support sha256.
			$algo = function_exists('hash') ? 'sha256' : 'sha1';
			$hash = hash_hmac($algo, $username . '|' . $expiration . '|' . $token, $key);
			if(hash_equals($hash, $hmac)){
				$sessions = get_user_meta($user->ID, 'session_tokens', true);
				$sessions = current($sessions);
				if(is_array($sessions)){
					if(is_int($sessions['expiration']) && $sessions['expiration'] > time()){
						return $user->ID;
					}else
						return false;
				}else
					return false;
			}else
				return false;
		}else
			return false;
	}else
		return false;
}

/**
 * Gets user by filed
 *
 * @param $field
 * @param $value
 *
 * @return bool|WP_User
 */
function apbct_wp_get_user_by($field, $value){
	
	$userdata = WP_User::get_data_by($field, $value);
	
	if(!$userdata)
		return false;
	
	$user = new WP_User;
	$user->init($userdata);
	
	return $user;
}

/**
 * Get hash of given string.
 *
 * @param string $data   Plain text to hash
 * @param string $scheme Authentication scheme (auth, secure_auth, logged_in, nonce)
 * @return string Hash of $data
 */
function apbct_wp_hash( $data, $scheme = 'auth' ) {
	
	$values = array(
		'key'  => '',
		'salt' => '',
	);
	
	foreach(array('key', 'salt') as $type){
		$const = strtoupper( "{$scheme}_{$type}");
		if ( defined($const) && constant($const)){
			$values[$type] = constant($const);
		}elseif(!$values[$type]){
			$values[$type] = get_site_option( "{$scheme}_{$type}");
			if (!$values[$type]){
				$values[$type] = '';
			}
		}
	}
	
	$salt = $values['key'] . $values['salt'];
	
	return hash_hmac('md5', $data, $salt);
}

/**
 * Parse a cookie into its components
 *
 * @param string $cookie
 * @param string $scheme Optional. The cookie scheme to use: auth, secure_auth, or logged_in
 *
 * @return array|false Authentication cookie components
 *
 */
function apbct_wp_parse_auth_cookie($cookie = '', $scheme = '')
{
	$cookie_elements = explode('|', $cookie);
	if(count($cookie_elements) !== 4){
		return false;
	}
	
	list($username, $expiration, $token, $hmac) = $cookie_elements;
	
	return compact('username', 'expiration', 'token', 'hmac', 'scheme');
}

/**
 * Checks if the plugin is active
 *
 * @param string $plugin relative path from plugin folder like cleantalk-spam-protect/cleantalk.php
 *
 * @return bool
 */
function apbct_is_plugin_active( $plugin ) {
	return in_array( $plugin, (array) get_option( 'active_plugins', array() ) ) || apbct_is_plugin_active_for_network( $plugin );
}

/**
 * Checks if the plugin is active for network
 *
 * @param string $plugin relative path from plugin folder like cleantalk-spam-protect/cleantalk.php
 *
 * @return bool
 */
function apbct_is_plugin_active_for_network( $plugin ){
	
	if ( ! APBCT_WPMS )
		return false;
	
	$plugins = get_site_option( 'active_sitewide_plugins' );
	
	return isset( $plugins[ $plugin ] )
		? true
		: false;
}

/**
 * Checks if the request is AJAX
 *
 * @return boolean
 */
function apbct_is_ajax() {
	
	return
		(defined( 'DOING_AJAX' ) && DOING_AJAX) || // by standart WP functions
		(filter_input(INPUT_SERVER, 'HTTP_X_REQUESTED_WITH') && strtolower(filter_input(INPUT_SERVER, 'HTTP_X_REQUESTED_WITH')) == 'xmlhttprequest') || // by Request type
		!empty($_POST['quform_ajax']) || // special. QForms
		!empty($_POST['iphorm_ajax']); // special. IPHorm
	
}

/**
 * Checks if the user is logged in
 *
 * @return bool
 */
function apbct_is_user_logged_in(){
	$siteurl = get_site_option( 'siteurl' );
	$cookiehash = $siteurl ? md5( $siteurl ) : '';
	return count($_COOKIE) && isset($_COOKIE['wordpress_logged_in_'.$cookiehash]);
}