<?php

class CleantalkHelper
{
	const URL = 'https://api.cleantalk.org';
	
	public static $cdn_pool = array(
		'cloud_flare' => array(
			'ipv4' => array(
				'103.21.244.0/22',
				'103.22.200.0/22',
				'103.31.4.0/22',
				'104.16.0.0/12',
				'108.162.192.0/18',
				'131.0.72.0/22',
				'141.101.64.0/18',
				'162.158.0.0/15',
				'172.64.0.0/13',
				'173.245.48.0/20',
				'185.93.231.18/20', // User fix
				'185.220.101.46/20', // User fix
				'188.114.96.0/20',
				'190.93.240.0/20',
				'197.234.240.0/22',
				'198.41.128.0/17',
			),
			'ipv6' => array(
				'2400:cb00::/32',
				'2405:8100::/32',
				'2405:b500::/32',
				'2606:4700::/32',
				'2803:f800::/32',
				'2c0f:f248::/32',
				'2a06:98c0::/29',
			),
		),
	);
	
	public static $private_networks = array(
		'10.0.0.0/8',
		'100.64.0.0/10',
		'172.16.0.0/12',
		'192.168.0.0/16',
		'127.0.0.1/32',
	);
	
	/*
	*	Getting arrays of IP (REMOTE_ADDR, X-Forwarded-For, X-Real-Ip, Cf_Connecting_Ip)
	*	reutrns array('remote_addr' => 'val', ['x_forwarded_for' => 'val', ['x_real_ip' => 'val', ['cloud_flare' => 'val']]])
	*/
	static public function ip_get($ips_input = array('real', 'remote_addr', 'x_forwarded_for', 'x_real_ip', 'cloud_flare'), $v4_only = true)
	{
		$ips = array();
		foreach($ips_input as $ip_type){
			$ips[$ip_type] = '';
		} unset($ip_type);
				
		$headers = apache_request_headers();
		
		// REMOTE_ADDR
		if(isset($ips['remote_addr'])){
			$ips['remote_addr'] = $_SERVER['REMOTE_ADDR'];
		}
		
		// X-Forwarded-For
		if(isset($ips['x_forwarded_for'])){
			if(isset($headers['X-Forwarded-For'])){
				$tmp = explode(",", trim($headers['X-Forwarded-For']));
				$ips['x_forwarded_for']= trim($tmp[0]);
			}
		}
		
		// X-Real-Ip
		if(isset($ips['x_real_ip'])){
			if(isset($headers['X-Real-Ip'])){
				$tmp = explode(",", trim($headers['X-Real-Ip']));
				$ips['x_real_ip']= trim($tmp[0]);
			}
		}
		
		// Cloud Flare
		if(isset($ips['cloud_flare'])){
			if(isset($headers['Cf-Connecting-Ip'])){
				if(self::ip_mask_match($ips['remote_addr'], self::$cdn_pool['cloud_flare']['ipv4'])){
					$ips['cloud_flare'] = $headers['Cf-Connecting-Ip'];
				}
			}
		}
		
		// Getting real IP from REMOTE_ADDR or Cf_Connecting_Ip if set or from (X-Forwarded-For, X-Real-Ip) if REMOTE_ADDR is local.
		if(isset($ips['real'])){
			
			$ips['real'] = $_SERVER['REMOTE_ADDR'];
			
			// Cloud Flare
			if(isset($headers['Cf-Connecting-Ip'])){
				if(self::ip_mask_match($ips['real'], self::$cdn_pool['cloud_flare']['ipv4'])){
					$ips['real'] = $headers['Cf-Connecting-Ip'];
				}
			// Incapsula proxy
			}elseif(isset($headers['Incap-Client-Ip'])){
				$ips['real'] = $headers['Incap-Client-Ip'];
			// Private networks. Looking for X-Forwarded-For and X-Real-Ip
			}elseif(self::ip_mask_match($ips['real'], self::$private_networks)){
				if(isset($headers['X-Forwarded-For'])){
					$tmp = explode(",", trim($headers['X-Forwarded-For']));
					$ips['real']= trim($tmp[0]);
				}elseif(isset($headers['X-Real-Ip'])){
					$tmp = explode(",", trim($headers['X-Real-Ip']));
					$ips['real']= trim($tmp[0]);
				}
			}
		}
		
		// Validating IPs
		$result = array();
		foreach($ips as $key => $ip){
			if($v4_only){
				if(self::ip_validate($ip) == 'v4')
					$result[$key] = $ip;
			}else{
				if(self::ip_validate($ip))
					$result[$key] = $ip;
			}
		}
		
		$result = array_unique($result);
		
		return count($ips_input) > 1 
			? $result 
			: (reset($result) !== false
				? reset($result)
				: null);
	}
		
	/*
	 * Check if the IP belong to mask. Recursivly if array given
	 * @param ip string  
	 * @param cird mixed (string|array of strings)
	*/
	static public function ip_mask_match($ip, $cidr){
		if(is_array($cidr)){
			foreach($cidr as $curr_mask){
				if(self::ip_mask_match($ip, $curr_mask)){
					return true;
				}
			} unset($curr_mask);
			return false;
		}
		$exploded = explode ('/', $cidr);
		$net = $exploded[0];
		$mask = 4294967295 << (32 - $exploded[1]);
		return (ip2long($ip) & $mask) == (ip2long($net) & $mask);
	}
	
	/*
	*	Validating IPv4, IPv6
	*	param (string) $ip
	*	returns (string) 'v4' || (string) 'v6' || (bool) false
	*/
	static public function ip_validate($ip)
	{
		if(!$ip)                                                  return false; // NULL || FALSE || '' || so on...
		if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return 'v4';  // IPv4
		if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return 'v6';  // IPv6
		                                                          return false; // Unknown
	}
	
	/*
	* Wrapper for sfw_logs API method
	* 
	* returns mixed STRING || array('error' => true, 'error_string' => STRING)
	*/
	static public function api_method__sfw_logs($api_key, $data, $do_check = true){
		
		$request = array(
			'auth_key' => $api_key,
			'method_name' => 'sfw_logs',
			'data' => json_encode($data),
			'rows' => count($data),
			'timestamp' => time()
		);
		$result = self::api_send_request($request);
		$result = $do_check ? self::api_check_response($result, 'sfw_logs') : $result;
		
		return $result;
	}
	
	/*
	* Wrapper for 2s_blacklists_db API method
	* 
	* returns mixed STRING || array('error' => true, 'error_string' => STRING)
	*/
	static public function api_method__get_2s_blacklists_db($api_key, $do_check = true){
		
		$request = array(
			'agent' => APBCT_AGENT,
			'method_name' => '2s_blacklists_db',
			'auth_key' => $api_key,
		);
		
		$result = self::api_send_request($request);
		$result = $do_check ? self::api_check_response($result, '2s_blacklists_db') : $result;
		
		return $result;
	}
	
	/**
	 * Function gets access key automatically
	 *
	 * @param string website admin email
	 * @param string website host
	 * @param string website platform
	 * @return type
	 */
	static public function api_method__get_api_key($email, $host, $platform, $timezone = null, $language = null, $ip = null, $do_check = true)
	{		
		$request = array(
			'method_name'          => 'get_api_key',
			'product_name'         => 'antispam',
			'agent'                => APBCT_AGENT,
			'email'                => $email,
			'website'              => $host,
			'platform'             => $platform,
			'timezone'             => $timezone,
			'http_accept_language' => !empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : null,
			'user_ip'              => $ip ? $ip : self::ip_get(array('real'), false),
		);
		
		$result = self::api_send_request($request);
		// $result = $do_check ? self::api_check_response($result, 'get_api_key') : $result;
		
		return $result;
	}
	
	/**
	 * Function gets information about renew notice
	 *
	 * @param string api_key
	 * @return type
	 */
	static public function api_method__notice_validate_key($api_key, $path_to_cms, $do_check = true)
	{
		$request = array(
			'agent' => APBCT_AGENT,
			'method_name' => 'notice_validate_key',
			'auth_key' => $api_key,
			'path_to_cms' => $path_to_cms	
		);
		
		$result = self::api_send_request($request);
		$result = $do_check ? self::api_check_response($result, 'notice_validate_key') : $result;
		
		return $result;
	}
	
	/**
	 * Function gets information about renew notice
	 *
	 * @param string api_key
	 * @return type
	 */
	static public function api_method__notice_paid_till($api_key, $do_check = true)
	{
		$request = array(
			'agent' => APBCT_AGENT,
			'method_name' => 'notice_paid_till',
			'auth_key' => $api_key
		);
		
		$result = self::api_send_request($request);
		$result = $do_check ? self::api_check_response($result, 'notice_paid_till') : $result;
		
		return $result;
	}

	/**
	 * Function gets spam report
	 *
	 * @param string website host
	 * @param integer report days
	 * @return type
	 */
	static public function api_method__get_antispam_report($host, $period = 1)
	{
		$request=Array(
			'agent' => APBCT_AGENT,
			'method_name' => 'get_antispam_report',
			'hostname' => $host,
			'period' => $period
		);
		
		$result = self::api_send_request($request);
		// $result = $do_check ? self::api_check_response($result, 'get_antispam_report') : $result;
		
		return $result;
	}
	
	/**
	 * Function gets spam statistics
	 *
	 * @param string website host
	 * @param integer report days
	 * @return type
	 */
	static public function api_method__get_antispam_report_breif($api_key, $do_check = true)
	{
		
		$request = array(
			'agent' => APBCT_AGENT,
			'method_name' => 'get_antispam_report_breif',
			'auth_key' => $api_key,
		);
		
		$result = self::api_send_request($request);
		$result = $do_check ? self::api_check_response($result, 'get_antispam_report_breif') : $result;
		
		$tmp = array();
		for( $i = 0; $i < 7; $i++ )
			$tmp[ date( 'Y-m-d', time() - 86400 * 7 + 86400 * $i ) ] = 0;
		
		$result['spam_stat']    = array_merge( $tmp, isset($result['spam_stat']) ? $result['spam_stat'] : array() );
		$result['top5_spam_ip'] = isset($result['top5_spam_ip']) ? $result['top5_spam_ip'] : array();
		
		return $result;		
	}
	
	/**
	 * Function gets spam report
	 *
	 * @param string website host
	 * @param integer report days
	 * @return type
	 */
	static public function api_method__spam_check_cms($api_key, $data, $date = null, $do_check = true)
	{
		$request=Array(
			'agent' => APBCT_AGENT,
			'method_name' => 'spam_check_cms',
			'auth_key' => $api_key,
			'data' => is_array($data) ? implode(',',$data) : $data,
		);
		
		if($date) $request['date'] = $date;
		
		$result = self::api_send_request($request);
		$result = $do_check ? self::api_check_response($result, 'spam_check_cms') : $result;
		
		return $result;
	}
	
	/**
	 * Function sends raw request to API server
	 *
	 * @param string url of API server
	 * @param array data to send
	 * @param boolean is data have to be JSON encoded or not
	 * @param integer connect timeout
	 * @return type
	 */
	static public function api_send_request($data, $url = self::URL, $isJSON = false, $timeout=3, $ssl = false)
	{	
		
		$result = null;
		$curl_error = false;
		
		$original_data = $data;
		
		if(!$isJSON){
			$data = http_build_query($data);
			$data = str_replace("&amp;", "&", $data);
		}else{
			$data = json_encode($data);
		}
		
		if (function_exists('curl_init') && function_exists('json_decode')){
		
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
			
			if ($ssl === true) {
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
				curl_setopt($ch, CURLOPT_CAINFO, APBCT_CASERT_PATH);
            }else{
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			}
			
			$result = curl_exec($ch);
			
			if($result === false){
				if($ssl === false){
					return self::api_send_request($original_data, $url, $isJSON, $timeout, true);
				}
				$curl_error = curl_error($ch);
			}
			
			curl_close($ch);
			
		}else{
			$curl_error = 'CURL_NOT_INSTALLED';
		}
		
		if($curl_error){
			
			$opts = array(
				'http'=>array(
					'method'  => "POST",
					'timeout' => $timeout,
					'content' => $data,
				)
			);
			$context = stream_context_create($opts);
			$result = @file_get_contents($url, 0, $context);
		}
		
		if(!$result && $curl_error)
			return json_encode(array('error' => true, 'error_string' => $curl_error));
		
		return $result;
	}
	
	/**
	 * Function checks server response
	 *
	 * @param string result
	 * @param string request_method
	 * @return mixed (array || array('error' => true))
	 */
	static public function api_check_response($result, $method_name = null)
	{	
		
		// Errors handling
		
		// Bad connection
		if(empty($result)){
			return array(
				'error' => true,
				'error_string' => 'CONNECTION_ERROR'
			);
		}
		
		// JSON decode errors
		$result = json_decode($result, true);
		if(empty($result)){
			return array(
				'error' => true,
				'error_string' => 'JSON_DECODE_ERROR'
			);
		}
		
		// cURL error
		if(!empty($result['error'])){
			return array(
				'error' => true,
				'error_string' => 'CONNECTION_ERROR: ' . $result['error_string'],
			);
		}
		
		// Server errors
		if($result && (isset($result['error_no']) || isset($result['error_message']))){
			return array(
				'error' => true,
				'error_string' => "SERVER_ERROR NO: {$result['error_no']} MSG: {$result['error_message']}",
				'error_no' => $result['error_no'],
				'error_message' => $result['error_message']
			);
		}
		
		// Pathces for different methods
		
		// mehod_name = notice_validate_key
		if($method_name == 'notice_validate_key' && isset($result['valid'])){
			return $result;
		}
		
		// Other methods
		if(isset($result['data']) && is_array($result['data'])){
			return $result['data'];
		}
	}
	
	/**
	 * Prepares an adds an error to the plugin's data
	 *
	 * @param string type
	 * @param mixed array || string
	 * @returns null
	 */
	static public function addError($type, $error, $set_time = true)
	{	
		global $apbct;
		
		$error_string = is_array($error)
			? $error['error_string']
			: $error;
		
		// Exceptions
		if( ($type == 'send_logs'          && $error_string == 'NO_LOGS_TO_SEND') ||
			($type == 'send_firewall_logs' && $error_string == 'NO_LOGS_TO_SEND')
		)
			return;
		
		if($set_time == true)
			$apbct->data['errors'][$type]['error_time']   = current_time('timestamp');
		$apbct->data['errors'][$type]['error_string'] = $error_string;
		$apbct->save('data');
	}
	
	/**
	 * Deletes an error from the plugin's data
	 *
	 * @param mixed (array of strings || string 'elem1 elem2...' || string 'elem') type
	 * @param delay saving
	 * @returns null
	 */
	static public function deleteError($type, $save_flag = false)
	{
		global $apbct;
		
		$before = empty($apbct->data['errors']) ? 0 : count($apbct->data['errors']);
		
		if(is_string($type))
			$type = explode(' ', $type);
		
		foreach($type as $val){
			if(isset($apbct->data['errors'][$val])){
				unset($apbct->data['errors'][$val]);
			}
		}
		
		$after = empty($apbct->data['errors']) ? 0 : count($apbct->data['errors']);
		// Save if flag is set and there are changes
		if($save_flag && $before != $after)
			$apbct->save('data');
	}
	
	/**
	 * Deletes all errors from the plugin's data
	 *
	 * @param delay saving
	 * @returns null
	 */
	static public function deleteAllErrors($save_flag = false)
	{
		global $apbct;
		
		if(isset($apbct->data['errors']))
			unset($apbct->data['errors']);
		
		if($save_flag)
			$apbct->save('data');
	}
	
	//* Write $message to the plugin's debug option
	static public function ct_log($message = 'empty', $func = null, $params = array())
	{
		global $ct_data;
		
		$ct_data = ct_get_data();
		
		$function = $func                         ? $func : '';
		$cron     = in_array('cron', $params)     ? true  : false;
		$data     = in_array('data', $params)     ? true  : false;
		$settings = in_array('settings', $params) ? true  : false;
		
		if(is_array($message) or is_object($message))
			$message = print_r($message, true);
		
		if($message)  $ct_data['debug'][date("H:i:s", microtime(true))."_ACTION_".strval(current_action())."_FUNCTION_".strval($func)]             = $message;
		if($cron)     $ct_data['debug'][date("H:i:s", microtime(true))."_ACTION_".strval(current_action())."_FUNCTION_".strval($func).'_cron']     = get_option('cleantalk_cron');
		if($data)     $ct_data['debug'][date("H:i:s", microtime(true))."_ACTION_".strval(current_action())."_FUNCTION_".strval($func).'_data']     = get_option('cleantalk_data');
		if($settings) $ct_data['debug'][date("H:i:s", microtime(true))."_ACTION_".strval(current_action())."_FUNCTION_".strval($func).'_settings'] = get_option('cleantalk_settings');
		
		update_option('cleantalk_data', $ct_data);
	}

	static public function is_json($string)
	{
		return is_string($string) && is_array(json_decode($string, true)) ? true : false;
	}
}
