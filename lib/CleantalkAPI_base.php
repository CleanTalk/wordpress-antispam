<?php

class CleantalkAPI_base
{
	const URL = 'https://api.cleantalk.org';
	const AGENT = 'apbct-api-2.0';
	
	/**
	 * Wrapper for 2s_blacklists_db API method
	 * 
	 * @param type $api_key
	 * @param type $out Data output type (JSON or file URL)
	 * @param type $do_check
	 * @returns mixed STRING || array('error' => true, 'error_string' => STRING)
	 */
	static public function method__get_2s_blacklists_db($api_key, $out = null, $do_check = true){
		
		$request = array(
			'method_name' => '2s_blacklists_db',
			'auth_key' => $api_key,
			'out' => $out,
		);
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, '2s_blacklists_db') : $result;
		
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
	static public function method__get_api_key($email, $website, $platform, $timezone = null, $language = null, $user_ip = null, $wpms = false, $white_label = 0, $hoster_api_key = '', $do_check = true)
	{
		$request = array(
			'method_name'          => 'get_api_key',
			'product_name'         => 'antispam',
			'email'                => $email,
			'website'              => $website,
			'platform'             => $platform,
			'timezone'             => $timezone,
			'http_accept_language' => $language,
			'user_ip'              => $user_ip,
			'wpms_setup'           => $wpms,
			'hoster_whitelabel'    => $white_label,
			'hoster_api_key'       => $hoster_api_key,
		);
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'get_api_key') : $result;
		
		return $result;
	}
	
	/**
	 * Function gets spam report
	 *
	 * @param string website host
	 * @param integer report days
	 * @return type
	 */
	static public function method__get_antispam_report($host, $period = 1)
	{
		$request=Array(
			'method_name' => 'get_antispam_report',
			'hostname' => $host,
			'period' => $period
		);
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'get_antispam_report') : $result;
		
		return $result;
	}
	
	/**
	 * Function gets spam statistics
	 *
	 * @param string website host
	 * @param integer report days
	 * @return type
	 */
	static public function method__get_antispam_report_breif($api_key, $do_check = true)
	{
		$request = array(
			'method_name' => 'get_antispam_report_breif',
			'auth_key' => $api_key,
		);
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'get_antispam_report_breif') : $result;
		
		return $result;		
	}
	
	/**
	 * Function gets information about renew notice
	 *
	 * @param string api_key
	 * @return type
	 */
	static public function method__notice_validate_key($api_key, $path_to_cms, $do_check = true)
	{
		$request = array(
			'method_name' => 'notice_validate_key',
			'auth_key' => $api_key,
			'path_to_cms' => $path_to_cms	
		);
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'notice_validate_key') : $result;
		
		return $result;
	}
	
	/**
	 * Function gets information about renew notice
	 *
	 * @param string api_key
	 * @return type
	 */
	static public function method__notice_paid_till($api_key, $do_check = true)
	{
		$request = array(
			'method_name' => 'notice_paid_till',
			'auth_key' => $api_key
		);
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'notice_paid_till') : $result;
		
		return $result;
	}
	
	static public function method__ip_info($data, $do_check = true)
	{
		$request = array(
			'method_name' => 'ip_info',
			'data' => $data
		);
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'ip_info') : $result;
		return $result;
	}
	
	/**
	 * Function gets spam report
	 *
	 * @param string website host
	 * @param integer report days
	 * @return type
	 */
	static public function method__spam_check_cms($api_key, $data, $date = null, $do_check = true)
	{
		$request=Array(
			'method_name' => 'spam_check_cms',
			'auth_key' => $api_key,
			'data' => is_array($data) ? implode(',',$data) : $data,
		);
		
		if($date) $request['date'] = $date;
		
		$result = self::send_request($request, self::URL, 10);
		$result = $do_check ? self::check_response($result, 'spam_check_cms') : $result;
		
		return $result;
	}
	
	/**
	 * Function gets spam report
	 *
	 * @param string website host
	 * @param integer report days
	 * @return type
	 */
	static public function method__spam_check($api_key, $data, $date = null, $do_check = true)
	{
		$request=Array(
			'method_name' => 'spam_check',
			'auth_key' => $api_key,
			'data' => is_array($data) ? implode(',',$data) : $data,
		);
		
		if($date) $request['date'] = $date;
		
		$result = self::send_request($request, self::URL, 10);
		$result = $do_check ? self::check_response($result, 'spam_check') : $result;
		
		return $result;
	}
	
	/**
	* Wrapper for sfw_logs API method
	* @param integer connect timeout
	* @return type
	* returns mixed STRING || array('error' => true, 'error_string' => STRING)
	*/
	static public function method__sfw_logs($api_key, $data, $do_check = true){
		
		$request = array(
			'auth_key' => $api_key,
			'method_name' => 'sfw_logs',
			'data' => json_encode($data),
			'rows' => count($data),
			'timestamp' => time()
		);
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'sfw_logs') : $result;
		
		return $result;
	}
		
	static public function method__security_logs($api_key, $data, $do_check = true)
	{
		$request = array(
			'auth_key' => $api_key,
			'method_name' => 'security_logs',
			'timestamp' => current_time('timestamp'),
			'data' => json_encode($data),
			'rows' => count($data),
		);
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'security_logs') : $result;
		
		return $result;
	}
	
	static public function method__security_logs__sendFWData($api_key, $data, $do_check = true){
		
		$request = array(
			'auth_key' => $api_key,
			'method_name' => 'security_logs',
			'timestamp' => current_time('timestamp'),
			'data_fw' => json_encode($data),
			'rows_fw' => count($data),
		);
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'security_logs') : $result;
		
		return $result;
	}
	
	static public function method__security_logs__feedback($api_key, $do_check = true)
	{
		$request = array(
			'auth_key' => $api_key,
			'method_name' => 'security_logs',
			'data' => '0',
		);
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'security_logs') : $result;
		
		return $result;
	}
	
	static public function method__security_firewall_data($api_key, $do_check = true){
				
		$request = array(
			'auth_key' => $api_key,
			'method_name' => 'security_firewall_data',
		);
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'security_firewall_data') : $result;
		
		return $result;
	}
	
	static public function method__security_firewall_data_file($api_key, $do_check = true){
				
		$request = array(
			'auth_key' => $api_key,
			'method_name' => 'security_firewall_data_file',
		);
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'security_firewall_data_file') : $result;
		
		return $result;
	}
	
	static public function method__security_linksscan_logs($api_key, $scan_time, $scan_result, $links_total, $links_list, $do_check = true)
	{	
		$request = array(
			'auth_key' => $api_key,
			'method_name' => 'security_linksscan_logs',
			'started' => $scan_time,
			'result' => $scan_result,
			'total_links_found' => $links_total,
			'links_list' => $links_list,
		);
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'security_linksscan_logs') : $result;
		
		return $result;
	}
	
	static public function method__security_mscan_logs($api_key, $service_id, $scan_time, $scan_result, $scanned_total, $modified, $unknown, $do_check = true)
	{
		$request = array(
			'method_name'        => 'security_mscan_logs',
			'auth_key'           => $api_key,
			'service_id'         => $service_id,
			'started'            => $scan_time,
			'result'             => $scan_result,
			'total_core_files'   => $scanned_total,
		);
		
		if(!empty($modified)){
			$request['failed_files']      = json_encode($modified);
			$request['failed_files_rows'] = count($modified);
		}
		if(!empty($unknown)){
			$request['unknown_files']      = json_encode($unknown);
			$request['unknown_files_rows'] = count($unknown);
		}
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'security_mscan_logs') : $result;
		
		return $result;
	}
	
	static public function method__security_mscan_files($api_key, $file_path, $file, $file_md5, $weak_spots, $do_check = true)
	{
		$request = array(
			'method_name' => 'security_mscan_files',
			'auth_key' => $api_key,
			'path_to_sfile' => $file_path,
			'attached_sfile' => $file,
			'md5sum_sfile' => $file_md5,
			'dangerous_code' => $weak_spots,
		);
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'security_mscan_files') : $result;
		
		return $result;
	}
	
	/**
	 * Function gets spam domains report
	 *
	 * @param string api key
	 * @param integer report days
	 * @return type
	 */
	static public function method__backlinks_check_cms($api_key, $data, $date = null, $do_check = true)
	{
		$request = array(
			'method_name' => 'backlinks_check_cms',
			'auth_key'    => $api_key,
			'data'        => is_array($data) ? implode(',',$data) : $data,
		);
		
		if($date) $request['date'] = $date;
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'backlinks_check_cms') : $result;
		
		return $result;
	}
	
	/**
	 * Function gets spam domains report
	 *
	 * @param string api_key
	 * @param array logs
	 * @param bool do_check
	 * @return type
	 */
	static public function method__security_backend_logs($api_key, $logs, $do_check = true)
	{
		$request = array(
			'method_name' => 'security_backend_logs',
			'auth_key'    => $api_key,
			'logs'        => json_encode($logs),
			'total_logs'  => count($logs),
		);
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'security_backend_logs') : $result;
		
		return $result;
	}
	
	/**
	 * Sends data about auto repairs
	 * 
	 * @param type $api_key
	 * @param type $repair_result
	 * @param type $repair_comment
	 * @param type $repaired_processed_files
	 * @param type $repaired_total_files_proccessed
	 * @param type $backup_id
	 * @param type $do_check
	 * @return type
	 */
	static public function method__security_mscan_repairs($api_key, $repair_result, $repair_comment, $repaired_processed_files, $repaired_total_files_proccessed, $backup_id, $do_check = true)
	{
		$request = array(
			'method_name'                   => 'security_mscan_repairs',
			'auth_key'                      => $api_key,
			'repair_result'                 => $repair_result,
			'repair_comment'                => $repair_comment,
			'repair_proccessed_files'       => json_encode($repaired_processed_files),
			'repair_total_files_proccessed' => $repaired_total_files_proccessed,
			'backup_id'                     => $backup_id
		);
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'security_mscan_repairs') : $result;
		
		return $result;
	}
	
	/**
	 * Force server to update checksums for specific plugin\theme
	 * 
	 * @param type $api_key
	 * @param type $plugins_and_themes_to_refresh
	 * @param type $do_check
	 * @return type
	 */
	static public function method__request_checksums($api_key, $plugins_and_themes_to_refresh, $do_check = true)
	{
		$request = array(
			'method_name' => 'request_checksums',
			'auth_key'    => $api_key,
			'data'        => $plugins_and_themes_to_refresh
		);
		
		$result = self::send_request($request);
		$result = $do_check ? self::check_response($result, 'request_checksums') : $result;
				
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
	static public function send_request($data, $url = self::URL, $timeout = 5, $ssl = false)
	{
		// Possibility to switch API url
		$url = defined('CLEANTALK_API_URL') ? CLEANTALK_API_URL : $url;
		
		// Adding agent version to data
		$data['agent'] = defined('CLEANTALK_AGENT') ? CLEANTALK_AGENT : self::AGENT;
				
		// Make URL string
		$data_string = http_build_query($data);
		$data_string = str_replace("&amp;", "&", $data_string);
		
		// For debug purposes
		if(defined('CLEANTALK_DEBUG') && CLEANTALK_DEBUG){
			global $apbct_debug;
			$apbct_debug['sent_data'] = $data;
			$apbct_debug['request_string'] = $data_string;
		}
		
		if (function_exists('curl_init')){
		
			$ch = curl_init();

			// Set diff options
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));

			// Switch on/off SSL
			if ($ssl === true) {
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
				curl_setopt($ch, CURLOPT_CAINFO, APBCT_CASERT_PATH);
			}else{
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			}

			// Make a request
			$result = curl_exec($ch);
			$errors = curl_error($ch);
			curl_close($ch);
			
			// Retry with SSL enabled if failed
			if($result === false)
				if($ssl === false)
					return self::send_request($data, $url, $timeout, true);
			
		}else
			$errors = 'CURL_NOT_INSTALLED';
		
		// Trying to use file_get_contents() to make a API call
		if(!empty($errors)){
			if(ini_get('allow_url_fopen')){
				$opts = array(
					'http'=>array(
						'method'  => "POST",
						'timeout' => $timeout,
						'content' => $data_string,
					)
				);
				$context = stream_context_create($opts);
				$result = @file_get_contents($url, 0, $context);
				if($result === false)
					$errors .= '_FAILED_TO_USE_FILE_GET_CONTENTS';
			}else
				$errors .= '_AND_ALLOW_URL_FOPEN_IS_DISABLED';
		}
		
		if(empty($result) || !empty($errors))
			return array('error' => true, 'error_string' => $errors);
		else
			return $result;
	}
	
	/**
	 * Function checks server response
	 *
	 * @param string result
	 * @param string request_method
	 * @return mixed (array || array('error' => true))
	 */
	static public function check_response($result, $method_name = null)
	{
		// Errors handling
		// Bad connection
		if(is_array($result) && isset($result['error'])){
			return array(
				'error' => true,
				'error_string' => 'CONNECTION_ERROR: ' . (isset($result['error_string']) ? ' '.$result['error_string'] : ''),
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
		
		// Server errors
		if($result && (isset($result['error_no']) || isset($result['error_message']))){
			return array(
				'error' => true,
				'error_string' => "SERVER_ERROR NO: {$result['error_no']} MSG: {$result['error_message']}",
				'error_no' => $result['error_no'],
				'error_message' => $result['error_message']
			);
		}
		
		$out = array();
		// Pathces for different methods			
		switch ($method_name) {
			
		// notice_validate_key
			case 'notice_validate_key':
				$out = isset($result['valid']) ? $result : 'NO_VALID_VALUE';
				break;
		
		// get_antispam_report_breif
			case 'get_antispam_report_breif':
				for($tmp = array(), $i = 0; $i < 7; $i++){
					$tmp[date('Y-m-d', time() - 86400 * 7 + 86400 * $i)] = 0;
				}
				$out['spam_stat']    = (array) array_merge( $tmp, isset($out['spam_stat']) ? $out['spam_stat'] : array() );
				$out['top5_spam_ip'] = isset($out['top5_spam_ip']) ? $out['top5_spam_ip'] : array();
				break;
			
			default:
				$out = isset($result['data']) && is_array($result['data'])
					? $result['data']
					: array('error' => true, 'error_string' => 'NO_DATA');
				break;
		}
		
		return $out;
		
	}
}