<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\Common\Helper as Helper;
use Cleantalk\Variables\Cookie;
use Cleantalk\Variables\Get;
use Cleantalk\Variables\Server;

class SFW extends \Cleantalk\Common\Firewall\FirewallModule {
	
	/**
	 * @var bool
	 */
	private $test;
	
	// Additional params
	private $sfw_counter = false;
	private $api_key = false;
	private $apbct = array();
	private $set_cookies = false;
	private $cookie_domain = false;
	
	public $module_name = 'SFW';
	
	private $real_ip;
	private $debug;
	private $debug_data = '';
	
	/**
	 * FireWall_module constructor.
	 * Use this method to prepare any data for the module working.
	 *
	 * @param string $log_table
	 * @param string $data_table
	 * @param $params
	 */
	public function __construct( $log_table, $data_table, $params = array() ){
		
		$this->db__table__data = $data_table ?: null;
		$this->db__table__logs = $log_table ?: null;
		
		foreach( $params as $param_name => $param ){
			$this->$param_name = isset( $this->$param_name ) ? $param : false;
		}
		
		$this->debug = (bool) Get::get( 'debug' );
		
	}
	
	/**
	 * @param $ips
	 */
	public function ip__append_additional( &$ips ){
		
		$this->real_ip = $ips['real'];
		
		if( Get::get( 'sfw_test_ip' ) ){
			if( Helper::ip__validate( Get::get( 'sfw_test_ip' ) ) !== false ){
				$ips['sfw_test'] = Get::get( 'sfw_test_ip' );
				$this->test_ip   = Get::get( 'sfw_test_ip' );
				$this->test      = true;
			}
		}
		
		
	}
	
	/**
	 * Use this method to execute main logic of the module.
	 *
	 * @return array  Array of the check results
	 */
	public function check(){
		
		$results = array();
		
		// Skip by cookie
		foreach( $this->ip_array as $current_ip ){
			
			if( Cookie::get( 'ct_sfw_pass_key' ) == md5( $current_ip . $this->api_key ) ){
				
					if( Cookie::get( 'ct_sfw_passed' ) ){
						
						if( ! headers_sent() ){
							\Cleantalk\Common\Helper::apbct_cookie__set( 'ct_sfw_passed', '0', time() + 86400 * 3, '/', null, false, true, 'Lax' );
						}
						
						$results[] = array( 'ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_SFW__BY_COOKIE', );
						
						if( $this->sfw_counter ){
							$this->apbct->data['sfw_counter']['all'] ++;
							$this->apbct->saveData();
						}
						
					}
					
					return $results;
			}
		}
		
		// Common check
		foreach($this->ip_array as $origin => $current_ip){
			
			$current_ip_v4 = sprintf("%u", ip2long($current_ip));
			for ( $needles = array(), $m = 6; $m <= 32; $m ++ ) {
				$mask      = sprintf( "%u", ip2long( long2ip( - 1 << ( 32 - (int) $m ) ) ) );
				$needles[] = bindec( decbin( $mask ) & decbin( $current_ip_v4 ) );
			}
			$needles = array_unique( $needles );
			
			$db_results = $this->db->fetch_all("SELECT
				network, mask, status
				FROM " . $this->db__table__data . "
				WHERE network IN (". implode( ',', $needles ) .")
				AND	network = " . $current_ip_v4 . " & mask");
			
			if( ! empty( $db_results ) ){
				
				foreach( $db_results as $db_result ){
					
					if( $db_result['status'] == 1 )
						$results[] = array('ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_SFW__BY_WHITELIST',);
					else
						$results[] = array('ip' => $current_ip, 'is_personal' => false, 'status' => 'DENY_SFW',);
					
				}
				
			}else{
				
				$results[] = array( 'ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_SFW' );
				
			}
		}
		
		return $results;
	}
	
	/**
	 * Add entry to SFW log.
	 * Writes to database.
	 *
	 * @param string $ip
	 * @param $status
	 */
	public function update_log( $ip, $status ) {
		
		if( in_array( $status, array( 'PASS_SFW__BY_WHITELIST', 'PASS_SFW' ) ) ){
			return;
		}

		$id   = md5( $ip );
		$time = time();
		
		$query = "INSERT INTO " . $this->db__table__logs . "
		SET
			id = '$id',
			ip = '$ip',
			status = '$status',
			all_entries = 1,
			blocked_entries = 1,
			entries_timestamp = '" . $time . "'
		ON DUPLICATE KEY
		UPDATE
			status = '$status',
			all_entries = all_entries + 1,
			blocked_entries = blocked_entries" . ( strpos( $status, 'DENY' ) !== false ? ' + 1' : '' ) . ",
			entries_timestamp = '" . intval( $time ) . "'";
		
		$this->db->execute( $query );
	}
	
	public function actions_for_denied( $result ){
		
		if( $this->sfw_counter ){
			$this->apbct->data['sfw_counter']['blocked']++;
			$this->apbct->saveData();
		}
		
	}
	
	public function actions_for_passed( $result ){
		if( $this->set_cookies &&  ! headers_sent() )
			\Cleantalk\ApbctWP\Helper::apbct_cookie__set( 'ct_sfw_pass_key', md5( $result['ip'] . $this->api_key ), time() + 86400 * 30, '/', null, false );
	}
	
	/**
	 * Shows DIE page.
	 * Stops script executing.
	 *
	 * @param $result
	 */
	public function _die( $result ){
		
		parent::_die( $result );
		
		// Statistics
		if(!empty($this->blocked_ips)){
			reset($this->blocked_ips);
			$this->apbct->stats['last_sfw_block']['time'] = time();
			$this->apbct->stats['last_sfw_block']['ip'] = $result['ip'];
			$this->apbct->save('stats');
		}
		
		// File exists?
		if(file_exists(CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page__SFW.html")){
			
			$sfw_die_page = file_get_contents(CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page__SFW.html");
			
			// Translation
			$replaces = array(
				'{SFW_DIE_NOTICE_IP}'              => __('SpamFireWall is activated for your IP ', 'cleantalk-spam-protect'),
				'{SFW_DIE_MAKE_SURE_JS_ENABLED}'   => __( 'To continue working with web site, please make sure that you have enabled JavaScript.', 'cleantalk-spam-protect' ),
				'{SFW_DIE_CLICK_TO_PASS}'          => __('Please click the link below to pass the protection,', 'cleantalk-spam-protect'),
				'{SFW_DIE_YOU_WILL_BE_REDIRECTED}' => sprintf(__('Or you will be automatically redirected to the requested page after %d seconds.', 'cleantalk-spam-protect'), 3),
				'{CLEANTALK_TITLE}'                => ($this->test ? __('This is the testing page for SpamFireWall', 'cleantalk-spam-protect') : ''),
				'{REMOTE_ADDRESS}'                 => $result['ip'],
				'{SERVICE_ID}'                     => $this->apbct->data['service_id'],
				'{HOST}'                           => Server::get( 'HTTP_HOST' ),
				'{GENERATED}'                      => '<p>The page was generated at&nbsp;' . date( 'D, d M Y H:i:s' ) . "</p>",
				'{REQUEST_URI}'                    => Server::get( 'REQUEST_URI' ),
				
				// Cookie
				'{COOKIE_PREFIX}'      => '',
				'{COOKIE_DOMAIN}'      => $this->cookie_domain,
				'{COOKIE_SFW}'         => $this->test ? $this->test_ip : md5( $result['ip'] . $this->api_key ),
				'{COOKIE_ANTICRAWLER}' => md5( $this->api_key . $result['ip'] ),
				
				// Test
				'{TEST_TITLE}'      => '',
				'{REAL_IP__HEADER}' => '',
				'{TEST_IP__HEADER}' => '',
				'{TEST_IP}'         => '',
				'{REAL_IP}'         => '',
			);
			
			// Test
			if($this->test){
				$replaces['{TEST_TITLE}']      = __( 'This is the testing page for SpamFireWall', 'cleantalk-spam-protect' );
				$replaces['{REAL_IP__HEADER}'] = 'Real IP:';
				$replaces['{TEST_IP__HEADER}'] = 'Test IP:';
				$replaces['{TEST_IP}']         = $this->test_ip;
				$replaces['{REAL_IP}']         = $this->real_ip;
			}
			
			// Debug
			if($this->debug){
				$debug = '<h1>Headers</h1>'
				         . var_export(apache_request_headers(), true)
				         . '<h1>REMOTE_ADDR</h1>'
				         . Server::get( 'REMOTE_ADDR' )
				         . '<h1>SERVER_ADDR</h1>'
				         . Server::get( 'REMOTE_ADDR' )
				         . '<h1>IP_ARRAY</h1>'
				         . var_export($this->ip_array, true)
				         . '<h1>ADDITIONAL</h1>'
				         . var_export($this->debug_data, true);
			}
			$replaces['{DEBUG}'] = isset( $debug ) ? $debug : '';
			
			foreach( $replaces as $place_holder => $replace ){
				$sfw_die_page = str_replace( $place_holder, $replace, $sfw_die_page );
			}
			
			wp_die($sfw_die_page, "Blacklisted", Array('response'=>403));
			
		}else{
			wp_die("IP BLACKLISTED", "Blacklisted", Array('response'=>403));
		}
		
	}
	
	/**
	 * Sends and wipe SFW log
	 *
	 * @param $db
	 * @param $log_table
	 * @param string $ct_key API key
	 *
	 * @return array|bool array('error' => STRING)
	 */
	public static function send_log( $db, $log_table, $ct_key ) {
		
		//Getting logs
		$query = "SELECT * FROM " . $log_table . ";";
		$db->fetch_all( $query );
		
		if( count( $db->result ) ){
			
			//Compile logs
			$data = array();
			foreach( $db->result as $key => $value ){
				
				// Converting statuses to API format
				$value['status'] = $value['status'] === 'DENY_ANTICRAWLER'    ? 'BOT_PROTECTION'   : $value['status'];
				$value['status'] = $value['status'] === 'PASS_ANTICRAWLER'    ? 'BOT_PROTECTION'   : $value['status'];
				
				$value['status'] = $value['status'] === 'DENY_ANTIFLOOD'      ? 'FLOOD_PROTECTION' : $value['status'];
				$value['status'] = $value['status'] === 'PASS_ANTIFLOOD'      ? 'FLOOD_PROTECTION' : $value['status'];
				
				$value['status'] = $value['status'] === 'PASS_SFW__BY_COOKIE' ? null               : $value['status'];
				$value['status'] = $value['status'] === 'DENY_SFW'            ? null               : $value['status'];
				
				$row = array(
					trim( $value['ip'] ),
					$value['all_entries'],
					$value['all_entries'] - $value['blocked_entries'],
					$value['entries_timestamp'],
				);
				
				if( $value['status'] )
					$row[] = $value['status'];
				
				$data[] = $row;
				
			}
			unset( $key, $value );
			
			//Sending the request
			$result = \Cleantalk\Common\API::method__sfw_logs( $ct_key, $data );
			//Checking answer and deleting all lines from the table
			if( empty( $result['error'] ) ){
				if( $result['rows'] == count( $data ) ){
					$db->execute( "TRUNCATE TABLE " . $log_table . ";" );
					
					return $result;
				}
				
				return array( 'error' => 'SENT_AND_RECEIVED_LOGS_COUNT_DOESNT_MACH' );
			} else{
				return $result;
			}
			
		} else{
			return $result = array( 'rows' => 0 );
		}
	}
	
	
	/**
	 * Updates SFW local base
	 *
	 * @param $db
	 * @param $db__table__data
	 * @param string $ct_key API key
	 * @param null|string $file_url File URL with SFW data.
	 * @param bool $immediate Requires immmediate update. Without remote call
	 *
	 * @return array|bool array('error' => STRING)
	 */
	public static function update( $db, $db__table__data, $ct_key, $file_url = null, $immediate = false){
		
		// Getting remote file name
		if(!$file_url){
			
			sleep(6);
			
			$result = \Cleantalk\Common\API::method__get_2s_blacklists_db($ct_key, 'multifiles', '2_0');
			
			if(empty($result['error'])){
				
				if( !empty($result['file_url']) ){
					
					if(Helper::http__request($result['file_url'], array(), 'get_code') === 200) {
						
						if(ini_get('allow_url_fopen')) {
							
							$patterns = array();
							$patterns[] = 'get';
							
							if(!$immediate) $patterns[] = 'async';
							
							// Clear SFW table
							$db->execute("TRUNCATE TABLE {$db__table__data};");
							$db->set_query("SELECT COUNT(network) as cnt FROM {$db__table__data};")->fetch(); // Check if it is clear
							if($db->result['cnt'] != 0){
								$db->execute("DELETE FROM {$db__table__data};"); // Truncate table
								$db->set_query("SELECT COUNT(network) as cnt FROM {$db__table__data};")->fetch(); // Check if it is clear
								if($db->result['cnt'] != 0){
									return array('error' => 'COULD_NOT_CLEAR_SFW_TABLE'); // throw an error
								}
							}
							
							$gf = \gzopen($result['file_url'], 'rb');
							
							if ($gf) {
								
								$file_urls = array();
								
								while( ! \gzeof($gf) )
									$file_urls[] = trim( \gzgets($gf, 1024) );
								
								\gzclose($gf);
								
								return Helper::http__request(
									get_option('siteurl'),
									array(
										'spbc_remote_call_token'  => md5($ct_key),
										'spbc_remote_call_action' => 'sfw_update',
										'plugin_name'             => 'apbct',
										'file_urls'               => implode(',', $file_urls),
									),
									$patterns
								);
							}else
								return array('error' => 'COULD_NOT_OPEN_REMOTE_FILE_SFW');
						}else
							return array('error' => 'ERROR_ALLOW_URL_FOPEN_DISABLED');
					}else
						return array('error' => 'NO_FILE_URL_PROVIDED');
				}else
					return array('error' => 'BAD_RESPONSE');
			}else
				return $result;
		}else{
			
			if(Helper::http__request($file_url, array(), 'get_code') === 200){ // Check if it's there
				
				$gf = \gzopen($file_url, 'rb');
				
				if($gf){
					
					if( ! \gzeof($gf) ){
						
						for( $count_result = 0; ! \gzeof($gf); ){
							
							$query = "INSERT INTO ".$db__table__data." VALUES %s";
							
							for($i=0, $values = array(); APBCT_WRITE_LIMIT !== $i && ! \gzeof($gf); $i++, $count_result++){
								
								$entry = trim( \gzgets($gf, 1024) );
								
								if(empty($entry)) continue;
								
								$entry = explode(',', $entry);
								
								// Cast result to int
								$ip   = preg_replace('/[^\d]*/', '', $entry[0]);
								$mask = preg_replace('/[^\d]*/', '', $entry[1]);
								$private = isset($entry[2]) ? $entry[2] : 0;
								
								if(!$ip || !$mask) continue;
								
								$values[] = '('. $ip .','. $mask .','. $private .')';
								
							}
							
							if(!empty($values)){
								$query = sprintf($query, implode(',', $values).';');
								$db->execute($query);
							}
							
						}
						
						\gzclose($gf);
						return $count_result;
						
					}else
						return array('error' => 'ERROR_GZ_EMPTY');
				}else
					return array('error' => 'ERROR_OPEN_GZ_FILE');
			}else
				return array('error' => 'NO_REMOTE_FILE_FOUND');
		}
	}
}