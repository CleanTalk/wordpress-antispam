<?php

/**
 * Class CleantalkAPI.
 * Compatible only with Wordpress.
 *
 * @depends       Cleantalk\Antispam\API
 * 
 * @version       1.0
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see           https://github.com/CleanTalk/wordpress-antispam
 */
class CleantalkAPI extends Cleantalk\Antispam\API
{
	/**
	 * Function sends raw request to API server.
	 * May use built in Wordpress HTTP-API
	 *
	 * @param array Data to send
	 * @param string API server URL
	 * @param int $timeout
	 * @param bool Do we need to use SSL
	 *
	 * @return array|bool
	 */
	static public function send_request($data, $url = self::URL, $timeout = 5, $ssl = false, $ssl_path = '')
	{
		global $apbct;
		
		// Possibility to switch API url
		$url = defined('CLEANTALK_API_URL') ? CLEANTALK_API_URL : $url;
		
		// Adding agent version to data
		$data['agent'] = APBCT_AGENT;
		
		if($apbct->settings['use_buitin_http_api']){
			
			$args = array(
				'body' => $data,
				'timeout' => $timeout,
				'user-agent' => APBCT_AGENT.' '.get_bloginfo( 'url' ),
			);
			
			$result = wp_remote_post($url, $args);
			
			if( is_wp_error( $result ) ) {
				$errors = $result->get_error_message();
				$result = false;
			}else{
				 $result = wp_remote_retrieve_body($result);
			}
					
		// Call CURL version if disabled
		}else{
			$ssl_path = $ssl_path
				? $ssl_path
				: (defined('APBCT_CASERT_PATH') ? APBCT_CASERT_PATH : '');
			$result = parent::send_request($data, $url, $timeout, $ssl, $ssl_path);
		}
		
		return empty($result) || !empty($errors)
			? array('error' => true, 'error' => $errors)
			: $result;
	}
}