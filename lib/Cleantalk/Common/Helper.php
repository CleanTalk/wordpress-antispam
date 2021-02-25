<?php

namespace Cleantalk\Common;

use Cleantalk\Variables\Server;

/**
 * CleanTalk Helper class.
 * Compatible with any CMS.
 *
 * @package       PHP Antispam by CleanTalk
 * @subpackage    Helper
 * @Version       3.5
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see           https://github.com/CleanTalk/php-antispam
 */
class Helper
{
	/**
	 * Default user agent for HTTP requests
	 */
	const AGENT = 'Cleantalk-Helper/3.4';
	
	/**
	 * @var array Set of private networks IPv4 and IPv6
	 */
	public static $private_networks = array(
		'v4' => array(
			'10.0.0.0/8',
			'100.64.0.0/10',
			'172.16.0.0/12',
			'192.168.0.0/16',
			'127.0.0.1/32',
		),
		'v6' => array(
			'0:0:0:0:0:0:0:1/128', // localhost
			'0:0:0:0:0:0:a:1/128', // ::ffff:127.0.0.1
		),
	);
	
	/**
	 * @var array Set of CleanTalk servers
	 */
	public static $cleantalks_servers = array(
		// MODERATE
		'moderate1.cleantalk.org' => '162.243.144.175',
		'moderate2.cleantalk.org' => '159.203.121.181',
		'moderate3.cleantalk.org' => '88.198.153.60',
		'moderate4.cleantalk.org' => '159.69.51.30',
		'moderate5.cleantalk.org' => '95.216.200.119',
		'moderate6.cleantalk.org' => '138.68.234.8',
		// APIX
		'apix1.cleantalk.org' => '35.158.52.161',
		'apix2.cleantalk.org' => '18.206.49.217',
		'apix3.cleantalk.org' => '3.18.23.246',
		'apix4.cleantalk.org' => '44.227.90.42',
		'apix5.cleantalk.org' => '15.188.198.212',
        'apix6.cleantalk.org' => '54.219.94.72',
		//ns
		'netserv2.cleantalk.org' => '178.63.60.214',
		'netserv3.cleantalk.org' => '188.40.14.173',
	);

    /**
     * Getting arrays of IP (REMOTE_ADDR, X-Forwarded-For, X-Real-Ip, Cf_Connecting_Ip)
     *
     * @param string $ip_type_to_get Type of IP you want to receive
     * @param bool   $v4_only
     *
     * @return string|null
     */
    public static function ip__get( $ip_type_to_get = 'real', $v4_only = true, $headers = array() )
    {
        $out = null;

        switch( $ip_type_to_get ){

            // Cloud Flare
            case 'cloud_flare':
                $headers = $headers ?: self::http__get_headers();
                if( isset( $headers['Cf-Connecting-Ip'], $headers['Cf-Ipcountry'], $headers['Cf-Ray'] ) ){
                    $tmp = strpos( $headers['Cf-Connecting-Ip'], ',' ) !== false
                        ? explode( ',', $headers['Cf-Connecting-Ip'] )
                        : (array) $headers['Cf-Connecting-Ip'];
                    $ip_version = self::ip__validate( trim( $tmp[0] ) );
                    if( $ip_version ){
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ip__v6_normalize( trim( $tmp[0] ) ) : trim( $tmp[0] );
                    }
                }
                break;

            // GTranslate
            case 'gtranslate':
                $headers = $headers ?: self::http__get_headers();
                if( isset( $headers['X-Gt-Clientip'], $headers['X-Gt-Viewer-Ip'] ) ){
                    $ip_version = self::ip__validate( $headers['X-Gt-Viewer-Ip'] );
                    if( $ip_version ){
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ip__v6_normalize( $headers['X-Gt-Viewer-Ip'] ) : $headers['X-Gt-Viewer-Ip'];
                    }
                }
                break;

            // ezoic
            case 'ezoic':
                $headers = $headers ?: self::http__get_headers();
                if( isset( $headers['X-Middleton'], $headers['X-Middleton-Ip'] ) ){
                    $ip_version = self::ip__validate( $headers['X-Middleton-Ip'] );
                    if( $ip_version ){
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ip__v6_normalize( $headers['X-Middleton-Ip'] ) : $headers['X-Middleton-Ip'];
                    }
                }
                break;

            // Sucury
            case 'sucury':
                $headers = $headers ?: self::http__get_headers();
                if( isset( $headers['X-Sucuri-Clientip'] ) ){
                    $ip_version = self::ip__validate( $headers['X-Sucuri-Clientip'] );
                    if( $ip_version ){
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ip__v6_normalize( $headers['X-Sucuri-Clientip'] ) : $headers['X-Sucuri-Clientip'];
                    }
                }
                break;

            // X-Forwarded-By
            case 'x_forwarded_by':
                $headers = $headers ?: self::http__get_headers();
                if( isset( $headers['X-Forwarded-By'], $headers['X-Client-Ip'] ) ){
                    $ip_version = self::ip__validate( $headers['X-Client-Ip'] );
                    if( $ip_version ){
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ip__v6_normalize( $headers['X-Client-Ip'] ) : $headers['X-Client-Ip'];
                    }
                }
                break;

            // Stackpath
            case 'stackpath':
                $headers = $headers ?: self::http__get_headers();
                if( isset( $headers['X-Sp-Edge-Host'], $headers['X-Sp-Forwarded-Ip'] ) ){
                    $ip_version = self::ip__validate( $headers['X-Sp-Forwarded-Ip'] );
                    if( $ip_version ){
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ip__v6_normalize( $headers['X-Sp-Forwarded-Ip'] ) : $headers['X-Sp-Forwarded-Ip'];
                    }
                }
                break;

            // Ico-X-Forwarded-For
            case 'ico_x_forwarded_for':
                $headers = $headers ?: self::http__get_headers();
                if( isset( $headers['Ico-X-Forwarded-For'], $headers['X-Forwarded-Host'] ) ){
                    $ip_version = self::ip__validate( $headers['Ico-X-Forwarded-For'] );
                    if( $ip_version ){
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ip__v6_normalize( $headers['Ico-X-Forwarded-For'] ) : $headers['Ico-X-Forwarded-For'];
                    }
                }
                break;

            // OVH
            case 'ovh':
                $headers = $headers ?: self::http__get_headers();
                if( isset( $headers['X-Cdn-Any-Ip'], $headers['Remote-Ip'] ) ){
                    $ip_version = self::ip__validate( $headers['Remote-Ip'] );
                    if( $ip_version ){
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ip__v6_normalize( $headers['Remote-Ip'] ) : $headers['Remote-Ip'];
                    }
                }
                break;

            // Incapsula proxy
            case 'incapsula':
                $headers = $headers ?: self::http__get_headers();
                if( isset( $headers['Incap-Client-Ip'], $headers['X-Forwarded-For'] ) ){
                    $ip_version = self::ip__validate( $headers['Incap-Client-Ip'] );
                    if( $ip_version ){
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ip__v6_normalize( $headers['Incap-Client-Ip'] ) : $headers['Incap-Client-Ip'];
                    }
                }
                break;

            // Remote addr
            case 'remote_addr':
                $ip_version = self::ip__validate( Server::get( 'REMOTE_ADDR' ) );
                if( $ip_version ){
                    $out = $ip_version === 'v6' && ! $v4_only ? self::ip__v6_normalize( Server::get( 'REMOTE_ADDR' ) ) : Server::get( 'REMOTE_ADDR' );
                }
                break;

            // X-Forwarded-For
            case 'x_forwarded_for':
                $headers = $headers ?: self::http__get_headers();
                if( isset( $headers['X-Forwarded-For'] ) ){
                    $tmp     = explode( ',', trim( $headers['X-Forwarded-For'] ) );
                    $tmp     = trim( $tmp[0] );
                    $ip_version = self::ip__validate( $tmp );
                    if( $ip_version ){
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ip__v6_normalize( $tmp ) : $tmp;
                    }
                }
                break;

            // X-Real-Ip
            case 'x_real_ip':
                $headers = $headers ?: self::http__get_headers();
                if(isset($headers['X-Real-Ip'])){
                    $tmp = explode(",", trim($headers['X-Real-Ip']));
                    $tmp = trim($tmp[0]);
                    $ip_version = self::ip__validate($tmp);
                    if($ip_version){
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ip__v6_normalize($tmp) : $tmp;
                    }
                }
                break;

            // Real
            // Getting real IP from REMOTE_ADDR or Cf_Connecting_Ip if set or from (X-Forwarded-For, X-Real-Ip) if REMOTE_ADDR is local.
            case 'real':

                // Detect IP type
                $out = self::ip__get( 'cloud_flare', $v4_only, $headers );
                $out = $out ?: self::ip__get( 'sucury', $v4_only, $headers );
                $out = $out ?: self::ip__get( 'gtranslate', $v4_only, $headers );
                $out = $out ?: self::ip__get( 'ezoic', $v4_only, $headers );
                $out = $out ?: self::ip__get( 'stackpath', $v4_only, $headers );
                $out = $out ?: self::ip__get( 'x_forwarded_by', $v4_only, $headers );
                $out = $out ?: self::ip__get( 'ico_x_forwarded_for', $v4_only, $headers );
                $out = $out ?: self::ip__get( 'ovh', $v4_only, $headers );
                $out = $out ?: self::ip__get( 'incapsula', $v4_only, $headers );

                $ip_version = self::ip__validate( $out );

                // Is private network
                if(
                    ! $out ||
                    ($out &&
                    (
                        self::ip__is_private_network( $out, $ip_version ) ||
                        self::ip__mask_match(
                            $out,
                            Server::get( 'SERVER_ADDR' ) . '/24',
                            $ip_version
                        )
                    ))
                ){
                    //@todo Remove local IP from x-forwarded-for and x-real-ip
                    $out = $out ?: self::ip__get( 'x_forwarded_for', $v4_only, $headers );
                    $out = $out ?: self::ip__get( 'x_real_ip', $v4_only, $headers );
                }

                $out = $out ?: self::ip__get( 'remote_addr', $v4_only, $headers );

                break;

            default:
                $out = self::ip__get( 'real', $v4_only, $headers );
        }

        // Final validating IP
        $ip_version = self::ip__validate( $out );

        if( ! $ip_version ){
            return null;

        }elseif( $ip_version === 'v6' && $v4_only ){
            return null;

        }else{
            return $out;
        }
    }
	
	/**
	 * Checks if the IP is in private range
	 *
	 * @param string $ip
	 * @param string $ip_type
	 *
	 * @return bool
	 */
	static function ip__is_private_network($ip, $ip_type = 'v4')
	{
		return self::ip__mask_match($ip, self::$private_networks[$ip_type], $ip_type);
	}
	
	/**
	 * Check if the IP belong to mask.  Recursive.
	 * Octet by octet for IPv4
	 * Hextet by hextet for IPv6
	 *
	 * @param string $ip
	 * @param string $cidr       work to compare with
	 * @param string $ip_type    IPv6 or IPv4
	 * @param int    $xtet_count Recursive counter. Determs current part of address to check.
	 *
	 * @return bool
	 */
	static public function ip__mask_match($ip, $cidr, $ip_type = 'v4', $xtet_count = 0)
	{

		if(is_array($cidr)){
			foreach($cidr as $curr_mask){
				if(self::ip__mask_match($ip, $curr_mask, $ip_type)){
					return true;
				}
			}
			unset($curr_mask);
			return false;
		}

        if( ! self::ip__validate( $ip ) || ! self::cidr__validate( $cidr ) ){
            return false;
        }
		
		$xtet_base = ($ip_type == 'v4') ? 8 : 16;
		
		// Calculate mask
		$exploded = explode('/', $cidr);
		$net_ip = $exploded[0];
		$mask = $exploded[1];
		
		// Exit condition
		$xtet_end = ceil($mask / $xtet_base);
		if($xtet_count == $xtet_end)
			return true;
		
		// Lenght of bits for comparsion
		$mask = $mask - $xtet_base * $xtet_count >= $xtet_base ? $xtet_base : $mask - $xtet_base * $xtet_count;
		
		// Explode by octets/hextets from IP and Net
		$net_ip_xtets = explode($ip_type == 'v4' ? '.' : ':', $net_ip);
		$ip_xtets = explode($ip_type == 'v4' ? '.' : ':', $ip);
		
		// Standartizing. Getting current octets/hextets. Adding leading zeros.
		$net_xtet = str_pad(decbin($ip_type == 'v4' ? $net_ip_xtets[$xtet_count] : @hexdec($net_ip_xtets[$xtet_count])), $xtet_base, 0, STR_PAD_LEFT);
		$ip_xtet = str_pad(decbin($ip_type == 'v4' ? $ip_xtets[$xtet_count] : @hexdec($ip_xtets[$xtet_count])), $xtet_base, 0, STR_PAD_LEFT);
		
		// Comparing bit by bit
		for($i = 0, $result = true; $mask != 0; $mask--, $i++){
			if($ip_xtet[$i] != $net_xtet[$i]){
				$result = false;
				break;
			}
		}
		
		// Recursing. Moving to next octet/hextet.
		if($result)
			$result = self::ip__mask_match($ip, $cidr, $ip_type, $xtet_count + 1);
		
		return $result;
		
	}
	
	/**
	 * Converts long mask like 4294967295 to number like 32
	 *
	 * @param int $long_mask
	 *
	 * @return int
	 */
	static function ip__mask__long_to_number($long_mask)
	{
		$num_mask = strpos((string)decbin($long_mask), '0');
		return $num_mask === false ? 32 : $num_mask;
	}
	
	/**
	 * Validating IPv4, IPv6
	 *
	 * @param string $ip
	 *
	 * @return string|bool
	 */
	static public function ip__validate($ip)
	{
		if(!$ip) return false; // NULL || FALSE || '' || so on...
		if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $ip != '0.0.0.0') return 'v4';  // IPv4
		if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && self::ip__v6_reduce($ip) != '0::0') return 'v6';  // IPv6
		return false; // Unknown
	}

    /**
     * Validate CIDR
     *
     * @param string $cidr expects string like 1.1.1.1/32
     *
     * @return bool
     */
    public static function cidr__validate( $cidr ){
        $cidr = explode( '/', $cidr );
        return isset( $cidr[0], $cidr[1] ) && self::ip__validate( $cidr[0] ) && preg_match( '@\d{1,2}@', $cidr[1] );
    }
	
	/**
	 * Expand IPv6
	 *
	 * @param string $ip
	 *
	 * @return string IPv6
	 */
	static public function ip__v6_normalize($ip)
	{
		$ip = trim($ip);
		// Searching for ::ffff:xx.xx.xx.xx patterns and turn it to IPv6
		if(preg_match('/^::ffff:([0-9]{1,3}\.?){4}$/', $ip)){
			$ip = dechex(sprintf("%u", ip2long(substr($ip, 7))));
			$ip = '0:0:0:0:0:0:' . (strlen($ip) > 4 ? substr('abcde', 0, -4) : '0') . ':' . substr($ip, -4, 4);
			// Normalizing hextets number
		}elseif(strpos($ip, '::') !== false){
			$ip = str_replace('::', str_repeat(':0', 8 - substr_count($ip, ':')) . ':', $ip);
			$ip = strpos($ip, ':') === 0 ? '0' . $ip : $ip;
			$ip = strpos(strrev($ip), ':') === 0 ? $ip . '0' : $ip;
		}
		// Simplifyng hextets
		if(preg_match('/:0(?=[a-z0-9]+)/', $ip)){
			$ip = preg_replace('/:0(?=[a-z0-9]+)/', ':', strtolower($ip));
			$ip = self::ip__v6_normalize($ip);
		}
		return $ip;
	}
	
	/**
	 * Reduce IPv6
	 *
	 * @param string $ip
	 *
	 * @return string IPv6
	 */
	static public function ip__v6_reduce($ip)
	{
		if(strpos($ip, ':') !== false){
			$ip = preg_replace('/:0{1,4}/', ':', $ip);
			$ip = preg_replace('/:{2,}/', '::', $ip);
			$ip = strpos($ip, '0') === 0 ? substr($ip, 1) : $ip;
		}
		return $ip;
	}
	
	/**
	 * Get URL form IP. Check if it's belong to cleantalk.
	 *
	 * @param string $ip
	 *
	 * @return false|int|string
	 */
	static public function ip__is_cleantalks($ip)
	{
		if(self::ip__validate($ip)){
			$url = array_search($ip, self::$cleantalks_servers);
			return $url
				? true
				: false;
		}else
			return false;
	}
	
	/**
	 * Get URL form IP. Check if it's belong to cleantalk.
	 *
	 * @param $ip
	 *
	 * @return false|int|string
	 */
	static public function ip__resolve__cleantalks($ip)
	{
		if(self::ip__validate($ip)){
			$url = array_search($ip, self::$cleantalks_servers);
			return $url
				? $url
				: self::ip__resolve($ip);
		}else
			return $ip;
	}
	
	/**
	 * Get URL form IP
	 *
	 * @param $ip
	 *
	 * @return string
	 */
	static public function ip__resolve($ip)
	{
		if(self::ip__validate($ip)){
			$url = gethostbyaddr($ip);
			if($url)
				return $url;
		}
		return $ip;
	}
	
	/**
	 * Resolve DNS to IP
	 *
	 * @param      $host
	 * @param bool $out
	 *
	 * @return bool
	 */
	static public function dns__resolve($host, $out = false)
	{
		
		// Get DNS records about URL
		if(function_exists('dns_get_record')){
			$records = dns_get_record($host, DNS_A);
			if($records !== false){
				$out = $records[0]['ip'];
			}
		}
		
		// Another try if first failed
		if(!$out && function_exists('gethostbynamel')){
			$records = gethostbynamel($host);
			if($records !== false){
				$out = $records[0];
			}
		}
		
		return $out;
		
	}
	
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
		if(function_exists('curl_init')){
			
			$ch = curl_init();
			
			if(!empty($data)){
				// If $data scalar converting it to array
				$data = is_string($data) || is_int($data) ? array($data => 1) : $data;
				// Build query
				$opts[CURLOPT_POSTFIELDS] = $data;
			}
			
			// Merging OBLIGATORY options with GIVEN options
			$opts = self::array_merge__save_numeric_keys(
				array(
					CURLOPT_URL => $url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_CONNECTTIMEOUT_MS => 6000,
					CURLOPT_FORBID_REUSE => true,
					CURLOPT_USERAGENT => self::AGENT . '; ' . ( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'UNKNOWN_HOST' ),
					CURLOPT_POST => true,
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_SSL_VERIFYHOST => 0,
					CURLOPT_HTTPHEADER => array('Expect:'), // Fix for large data and old servers http://php.net/manual/ru/function.curl-setopt.php#82418
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_MAXREDIRS => 5,
				),
				$opts
			);
			
			// Use presets
			$presets = is_array($presets) ? $presets : explode(' ', $presets);
			foreach($presets as $preset){
				
				switch($preset){
					
					// Do not follow redirects
					case 'dont_follow_redirects':
						$opts[CURLOPT_FOLLOWLOCATION] = false;
						$opts[CURLOPT_MAXREDIRS] = 0;
						break;
					
					// Get headers only
					case 'get_code':
						$opts[CURLOPT_HEADER] = true;
						$opts[CURLOPT_NOBODY] = true;
						break;
					
					// Make a request, don't wait for an answer
					case 'async':
						$opts[CURLOPT_CONNECTTIMEOUT_MS] = 1000;
						$opts[CURLOPT_TIMEOUT_MS] = 1000;
						break;
					
					case 'get':
						$opts[CURLOPT_URL] .= $data ? '?' . str_replace("&amp;", "&", http_build_query($data)) : '';
						$opts[CURLOPT_CUSTOMREQUEST] = 'GET';
						$opts[CURLOPT_POST] = false;
						$opts[CURLOPT_POSTFIELDS] = null;
						break;
					
					case 'ssl':
						$opts[CURLOPT_SSL_VERIFYPEER] = true;
						$opts[CURLOPT_SSL_VERIFYHOST] = 2;
						if(defined('CLEANTALK_CASERT_PATH') && CLEANTALK_CASERT_PATH)
							$opts[CURLOPT_CAINFO] = CLEANTALK_CASERT_PATH;
						break;
					
					default:
						
						break;
				}
				
			}
			unset($preset);
			
			curl_setopt_array($ch, $opts);
			$result = curl_exec($ch);
			
			// RETURN if async request
			if(in_array('async', $presets))
				return true;
			
			if($result){
				
				if(strpos($result, PHP_EOL) !== false && !in_array('dont_split_to_array', $presets))
					$result = explode(PHP_EOL, $result);
				
				// Get code crossPHP method
				if(in_array('get_code', $presets)){
					$curl_info = curl_getinfo($ch);
					$result = $curl_info['http_code'];
				}
				curl_close($ch);
				$out = $result;
			}else
				$out = array('error' => curl_error($ch));
		}else
			$out = array('error' => 'CURL_NOT_INSTALLED');
		
		/**
		 * Getting HTTP-response code without cURL
		 */
		if($presets && ($presets == 'get_code' || (is_array($presets) && in_array('get_code', $presets)))
			&& isset($out['error']) && $out['error'] == 'CURL_NOT_INSTALLED'
		){
			$headers = get_headers($url);
			$out = (int)preg_replace('/.*(\d{3}).*/', '$1', $headers[0]);
		}
		
		return $out;
	}
	
	/**
	 * Merging arrays without reseting numeric keys
	 *
	 * @param array $arr1 One-dimentional array
	 * @param array $arr2 One-dimentional array
	 *
	 * @return array Merged array
	 */
	public static function array_merge__save_numeric_keys($arr1, $arr2)
	{
		foreach($arr2 as $key => $val){
			$arr1[$key] = $val;
		}
		return $arr1;
	}
	
	/**
	 * Merging arrays without reseting numeric keys recursive
	 *
	 * @param array $arr1 One-dimentional array
	 * @param array $arr2 One-dimentional array
	 *
	 * @return array Merged array
	 */
	public static function array_merge__save_numeric_keys__recursive($arr1, $arr2)
	{
		foreach($arr2 as $key => $val){
			
			// Array | array => array
			if(isset($arr1[$key]) && is_array($arr1[$key]) && is_array($val)){
				$arr1[$key] = self::array_merge__save_numeric_keys__recursive($arr1[$key], $val);
				
			// Scalar | array => array
			}elseif(isset($arr1[$key]) && !is_array($arr1[$key]) && is_array($val)){
				$tmp = $arr1[$key] =
				$arr1[$key] = $val;
				$arr1[$key][] = $tmp;
				
			// array  | scalar => array
			}elseif(isset($arr1[$key]) && is_array($arr1[$key]) && !is_array($val)){
				$arr1[$key][] = $val;
				
			// scalar | scalar => scalar
			}else{
				$arr1[$key] = $val;
			}
		}
		return $arr1;
	}
	
	/**
	 * Function removing non UTF8 characters from array|string|object
	 *
	 * @param array|object|string $data
	 *
	 * @return array|object|string
	 */
	public static function removeNonUTF8($data)
	{
		// Array || object
		if(is_array($data) || is_object($data)){
			foreach($data as $key => &$val){
				$val = self::removeNonUTF8($val);
			}
			unset($key, $val);
			
			//String
		}else{
			if(!preg_match('//u', $data))
				$data = 'Nulled. Not UTF8 encoded or malformed.';
		}
		return $data;
	}
	
	/**
	 * Function convert anything to UTF8 and removes non UTF8 characters
	 *
	 * @param array|object|string $obj
	 * @param string              $data_codepage
	 *
	 * @return mixed(array|object|string)
	 */
	public static function toUTF8($obj, $data_codepage = null)
	{
		// Array || object
		if(is_array($obj) || is_object($obj)){
			foreach($obj as $key => &$val){
				$val = self::toUTF8($val, $data_codepage);
			}
			unset($key, $val);
			
			//String
		}else{
			if(!preg_match('//u', $obj) && function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding')){
				$encoding = mb_detect_encoding($obj);
				$encoding = $encoding ? $encoding : $data_codepage;
				if($encoding)
					$obj = mb_convert_encoding($obj, 'UTF-8', $encoding);
			}
		}
		return $obj;
	}
	
	/**
	 * Function convert from UTF8
	 *
	 * @param array|object|string $obj
	 * @param string              $data_codepage
	 *
	 * @return mixed (array|object|string)
	 */
	public static function fromUTF8($obj, $data_codepage = null)
	{
		// Array || object
		if(is_array($obj) || is_object($obj)){
			foreach($obj as $key => &$val){
				$val = self::fromUTF8($val, $data_codepage);
			}
			unset($key, $val);
			
			//String
		}else{
			if(preg_match('u', $obj) && function_exists('mb_convert_encoding') && $data_codepage !== null)
				$obj = mb_convert_encoding($obj, $data_codepage, 'UTF-8');
		}
		return $obj;
	}
	
	/**
	 * Checks if the string is JSON type
	 *
	 * @param string
	 *
	 * @return bool
	 */
	static public function is_json($string)
	{
		return is_string($string) && is_array(json_decode($string, true)) ? true : false;
	}

    /**
     * Universal method to adding cookies
     *
     * @param $name
     * @param string $value
     * @param int $expires
     * @param string $path
     * @param null $domain
     * @param bool $secure
     * @param bool $httponly
     * @param string $samesite
     *
     * @return void
     */
    public static function apbct_cookie__set ($name, $value = '', $expires = 0, $path = '', $domain = null, $secure = false, $httponly = false, $samesite = 'Lax' ) {

        // For PHP 7.3+ and above
        if( version_compare( phpversion(), '7.3.0', '>=' ) ){

            $params = array(
                'expires'  => $expires,
                'path'     => $path,
                'domain'   => $domain,
                'secure'   => $secure,
                'httponly' => $httponly,
            );

            if($samesite)
                $params['samesite'] = $samesite;

            setcookie( $name, $value, $params );

            // For PHP 5.6 - 7.2
        }else {
            setcookie( $name, $value, $expires, $path, $domain, $secure, $httponly );
        }

    }
	
	public static function time__get_interval_start( $interval = 300 ){
		return time() - ( ( time() - strtotime( date( 'd F Y' ) ) ) % $interval );
	}
	
	/**
	 * Get mime type from file or data
	 *
	 * @param string $data Path to file or data
	 * @param string $type Default mime type. Returns if we failed to detect type
	 *
	 * @return string
	 */
	static function get_mime_type( $data, $type = '' )
	{
        $data = str_replace( chr(0), '', $data ); // Clean input of null bytes
		if( ! empty( $data ) && @file_exists( $data )){
			$type = mime_content_type( $data );
		}elseif( function_exists('finfo_open' ) ){
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$type = finfo_buffer($finfo, $data);
			finfo_close($finfo);
		}
		return $type;
	}
	
	static function buffer__trim_and_clear_from_empty_lines( $buffer ){
		$buffer = (array) $buffer;
		foreach( $buffer as $indx => &$line ){
			$line = trim( $line );
			if($line === '')
				unset( $buffer[$indx] );
		}
		return $buffer;
	}
	
	static function buffer__parse__csv( $buffer ){
		$buffer = explode( "\n", $buffer );
		$buffer = self::buffer__trim_and_clear_from_empty_lines( $buffer );
		foreach($buffer as &$line){
			$line = str_getcsv($line, ',', '\'');
		}
		return $buffer;
	}
	
	/**
	 * Pops line from buffer without formatting
	 *
	 * @param $csv
	 *
	 * @return false|string
	 */
	static public function buffer__csv__pop_line( &$csv ){
		$pos  = strpos( $csv, "\n" );
		$line = substr( $csv, 0, $pos );
		$csv  = substr_replace( $csv, '', 0, $pos + 1 );
		return $line;
	}
	
	/**
	 * Pops line from the csv buffer and fromat it by map to array
	 *
	 * @param $csv
	 * @param array $map
	 *
	 * @return array|false
	 */
	static public function buffer__csv__get_map( &$csv ){
		$line = static::buffer__csv__pop_line( $csv );
		return explode( ',', $line );
	}
	
	/**
	 * Pops line from the csv buffer and fromat it by map to array
	 *
	 * @param $csv
	 * @param array $map
	 *
	 * @return array|false
	 */
	static public function buffer__csv__pop_line_to_array( &$csv, $map = array() ){
		$line = trim( static::buffer__csv__pop_line( $csv ) );
		$line = strpos( $line, '\'' ) === 0
			? str_getcsv($line, ',', '\'')
			: explode( ',', $line );
		if( $map )
			$line = array_combine( $map, $line );
		return $line;
	}

    /**
     * Escapes MySQL params
     *
     * @param string|int $param
     * @param string     $quotes
     *
     * @return int|string
     */
    public static function db__prepare_param($param, $quotes = '\'')
    {
        if(is_array($param)){
            foreach($param as &$par){
                $par = self::db__prepare_param($par);
            }
        }
        switch(true){
            case is_numeric($param):
                $param = intval($param);
                break;
            case is_string($param) && strtolower($param) == 'null':
                $param = 'NULL';
                break;
            case is_string($param):
                global $wpdb;
                $param = $quotes . $wpdb->_real_escape($param) . $quotes;
                break;
        }
        return $param;
    }

    /**
     * Gets every HTTP_ headers from $_SERVER
     *
     * If Apache web server is missing then making
     * Patch for apache_request_headers()
     *
     * returns array
     */
    public static function http__get_headers(){

        $headers = array();
        foreach($_SERVER as $key => $val){
            if( 0 === stripos( $key, 'http_' ) ){
                $server_key = preg_replace('/^http_/i', '', $key);
                $key_parts = explode('_', $server_key);
                if(count($key_parts) > 0 and strlen($server_key) > 2){
                    foreach($key_parts as $part_index => $part){
                        $key_parts[$part_index] = function_exists('mb_strtolower') ? mb_strtolower($part) : strtolower($part);
                        $key_parts[$part_index][0] = strtoupper($key_parts[$part_index][0]);
                    }
                    $server_key = implode('-', $key_parts);
                }
                $headers[$server_key] = $val;
            }
        }
        return $headers;
    }
}