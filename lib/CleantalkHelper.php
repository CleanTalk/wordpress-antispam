<?php

/**
 * CleanTalk Cleantalk Antispam Helper class.
 * Compatible only with Wordpress.
 * 
 * @depends Cleantalk\Antispam\Helper
 * 
 * @package Antispam Plugin by CleanTalk
 * @subpackage Helper
 * @Version 1.0
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see https://github.com/CleanTalk/wordpress-antispam
 */

class CleantalkHelper extends Cleantalk\Antispam\Helper
{
	/**
	 * Function sends raw http request
	 *
	 * May use 4 presets(combining possible):
	 * get_code - getting only HTTP response code
	 * async    - async requests
	 * get      - GET-request
	 * ssl      - use SSL
	 *
	 * @param string       $url     URL
	 * @param array        $data    POST|GET indexed array with data to send
	 * @param string|array $presets String or Array with presets: get_code, async, get, ssl, dont_split_to_array
	 * @param array        $opts    Optional option for CURL connection
	 *
	 * @return array|bool (array || array('error' => true))
	 */
	static public function http__request($url, $data = array(), $presets = null, $opts = array())
	{
		// Set APBCT User-Agent and passing data to parent method
		$opts = self::array_merge__save_numeric_keys(
			array(
				CURLOPT_USERAGENT => 'APBCT-wordpress/' . (defined('APBCT_VERSION') ? APBCT_VERSION : 'unknown') . '; ' . get_bloginfo('url'),
			),
			$opts
		);
		
		return parent::http__request($url, $data, $presets, $opts);
	}
}
