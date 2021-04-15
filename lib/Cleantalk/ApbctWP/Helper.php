<?php

namespace Cleantalk\ApbctWP;

/**
 * CleanTalk Cleantalk Antispam Helper class.
 * Compatible only with Wordpress.
 * 
 * @depends \Cleantalk\Common\Helper
 * 
 * @package Antispam Plugin by CleanTalk
 * @subpackage Helper
 * @Version 1.0
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see https://github.com/CleanTalk/wordpress-antispam
 */

class Helper extends \Cleantalk\Common\Helper
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
	
	/**
	 * Wrapper for http_request
	 * Requesting HTTP response code for $url
	 *
	 * @param string $url
	 *
	 * @return array|mixed|string
	 */
	static public function http__request__get_response_code( $url ){
		return static::http__request( $url, array(), 'get_code');
	}
	
	/**
	 * Wrapper for http_request
	 * Requesting data via HTTP request with GET method
	 *
	 * @param string $url
	 *
	 * @return array|mixed|string
	 */
	static public function http__request__get_content( $url ){
		return static::http__request( $url, array(), 'get dont_split_to_array');
	}
    
    static public function http__request__rc_to_host( $rc_action, $request_params, $patterns = array() ){
        
        global $apbct;
        
        $request_params__default = array(
            'spbc_remote_call_token'  => md5( $apbct->api_key ),
            'spbc_remote_call_action' => $rc_action,
            'plugin_name'             => 'apbct',
        );
        
        $result__rc_check_website = static::http__request(
            get_option( 'siteurl' ),
            array_merge( $request_params__default, $request_params, array( 'test' => 'test' ) ),
            array( 'get', 'dont_split_to_array' )
        );
        
        // Considering empty response as error
        $result__rc_check_website !== '' ?: $result__rc_check_website = array( 'error' => 'EMPTY_RESPONSE' );
        
        if( empty( $result__rc_check_website['error'] ) ){
            
            if( preg_match( '@^.*?OK$@', $result__rc_check_website) ){
                
                static::http__request(
                    get_option( 'siteurl' ),
                    array_merge( $request_params__default, $request_params ),
                    array_merge( array( 'get', ), $patterns )
                );
                
            }else
                return array(
                    'error' => 'WRONG_SITE_RESPONSE ACTION: ' . $rc_action . ' RESPONSE: ' . htmlspecialchars( substr(
                            ! is_string( $result__rc_check_website )
                                ? print_r( $result__rc_check_website, true )
                                : $result__rc_check_website,
                            0,
                            400
                        ) )
                );
        }else
            return array( 'error' => 'WRONG_SITE_RESPONSE TEST ACTION: ' . $rc_action . ' ERROR: ' . $result__rc_check_website['error'] );
        
        return true;
    }
    
    /**
     * Wrapper for http_request
     * Get data from remote GZ archive with all following checks
     *
     * @param string $url
     *
     * @return array|mixed|string
     */
    public static function http__get_data_from_remote_gz( $url ){
        
        $response_code = static::http__request__get_response_code( $url );
        
        if ( $response_code === 200 ) { // Check if it's there
            
            $data = static::http__request__get_content( $url );
            
            if ( empty( $data['error'] ) ){
                
                if( static::get_mime_type( $data, 'application/x-gzip' ) ){
                    
                    if(function_exists('gzdecode')) {
                        
                        $data = gzdecode( $data );
                        
                        if ( $data !== false ){
                            return $data;
                        }else
                            return array( 'error' => 'Can not unpack datafile');
                        
                    }else
                        return array( 'error' => 'Function gzdecode not exists. Please update your PHP at least to version 5.4 ' . $data['error'] );
                }else
                    return array('error' => 'Wrong file mime type: ' . $url);
            }else
                return array( 'error' => 'Getting datafile ' . $url . '. Error: '. $data['error'] );
        }else
            return array( 'error' => 'Bad HTTP response from file location: ' . $url );
    }
    
    /**
     * Wrapper for http__get_data_from_remote_gz
     * Get data and parse CSV from remote GZ archive with all following checks
     *
     * @param string $url
     *
     * @return array|mixed|string
     */
    public static function http__get_data_from_remote_gz__and_parse_csv( $url ){
    
        $result = static::http__get_data_from_remote_gz( $url );
        return empty( $result['error'] )
            ? static::buffer__parse__csv( $result )
            : $result;
    }
    
}
