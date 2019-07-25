<?php

class CleantalkAPI extends CleantalkAPI_base
{	
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
		global $apbct;
		
		// Possibility to switch API url
		$url = defined('CLEANTALK_API_URL') ? CLEANTALK_API_URL : $url;
		
		// Adding agent version to data
		$data['agent'] = defined('CLEANTALK_AGENT') ? CLEANTALK_AGENT : self::AGENT;
		
		if($apbct->settings['use_buitin_http_api']){
			
			$args = array(
				'body' => $data,
				'timeout' => $timeout,
				'user-agent' => CLEANTALK_AGENT.' '.get_bloginfo( 'url' ),
			);
			
			$result = wp_remote_post($url, $args);
			
			if( is_wp_error( $result ) ) {
				$errors = $result->get_error_message();
				$result = false;
			}else{
				 $result = wp_remote_retrieve_body($result);
			}
					
		// Call CURL version if disabled
		}else
			$result = parent::send_request($data, $url, $timeout, $ssl);
				
		if(empty($result) || !empty($errors))
			return array('error' => true, 'error_string' => $errors);
		else
			return $result;
	}
}