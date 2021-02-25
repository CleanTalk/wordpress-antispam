<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\ApbctWP\API;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\Common\Schema;
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
		
		$this->real_ip = isset($ips['real']) ? $ips['real'] : null;
		
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
        $status = 0;
		
		// Skip by cookie
		foreach( $this->ip_array as $current_ip ){

			if( substr( Cookie::get( 'ct_sfw_pass_key' ), 0, 32 ) == md5( $current_ip . $this->api_key ) ){

                if( Cookie::get( 'ct_sfw_passed' ) ){

                    if( ! headers_sent() ){
                        \Cleantalk\Common\Helper::apbct_cookie__set( 'ct_sfw_passed', '0', time() + 86400 * 3, '/', null, false, true, 'Lax' );
                    } else {
                        $results[] = array( 'ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_SFW__BY_COOKIE', );
                    }

                    // Do logging an one passed request
                    $this->update_log( $current_ip, 'PASS_SFW' );

                    if( $this->sfw_counter ){
                        $this->apbct->data['sfw_counter']['all'] ++;
                        $this->apbct->saveData();
                    }

                }

                if( strlen( Cookie::get( 'ct_sfw_pass_key' ) ) > 32 ) {
                    $status = substr( Cookie::get( 'ct_sfw_pass_key' ), -1 );
                }

                if( $status ) {
                    $results[] = array('ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_SFW__BY_WHITELIST',);
                }
					
				return $results;
			}
		}
		
		// Common check
		foreach($this->ip_array as $origin => $current_ip){
			
			$current_ip_v4 = sprintf("%u", ip2long($current_ip));
			for ( $needles = array(), $m = 6; $m <= 32; $m ++ ) {
				$mask      = str_repeat( '1', $m );
				$mask      = str_pad( $mask, 32, '0' );
				$needles[] = sprintf( "%u", bindec( $mask & base_convert( $current_ip_v4, 10, 2 ) ) );
			}
			$needles = array_unique( $needles );
			
			$db_results = $this->db->fetch_all("SELECT
				network, mask, status
				FROM " . $this->db__table__data . "
				WHERE network IN (". implode( ',', $needles ) .")
				AND	network = " . $current_ip_v4 . " & mask 
				AND " . rand( 1, 100000 ) . "  
				ORDER BY status DESC");
			
			if( ! empty( $db_results ) ){
				
				foreach( $db_results as $db_result ){
					
					if( $db_result['status'] == 1 ) {
                        $results[] = array('ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_SFW__BY_WHITELIST',);
                        break;
                    }
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

		$id   = md5( $ip . $this->module_name );
		$time = time();
		
		$query = "INSERT INTO " . $this->db__table__logs . "
		SET
			id = '$id',
			ip = '$ip',
			status = '$status',
			all_entries = 1,
			blocked_entries = " . ( strpos( $status, 'DENY' ) !== false ? 1 : 0 ) . ",
			entries_timestamp = '" . $time . "',
			ua_name = '" . sanitize_text_field( Server::get('HTTP_USER_AGENT') ) . "'
		ON DUPLICATE KEY
		UPDATE
			status = '$status',
			all_entries = all_entries + 1,
			blocked_entries = blocked_entries" . ( strpos( $status, 'DENY' ) !== false ? ' + 1' : '' ) . ",
			entries_timestamp = '" . intval( $time ) . "',
			ua_name = '" . sanitize_text_field( Server::get('HTTP_USER_AGENT') ) . "'";
		
		$this->db->execute( $query );
	}
	
	public function actions_for_denied( $result ){
		
		if( $this->sfw_counter ){
			$this->apbct->data['sfw_counter']['blocked']++;
			$this->apbct->saveData();
		}
		
	}
	
	public function actions_for_passed( $result ){
		if( $this->set_cookies &&  ! headers_sent() ) {
		    $status = $result['status'] == 'PASS_SFW__BY_WHITELIST' ? '1' : '0';
            $cookie_val = md5( $result['ip'] . $this->api_key ) . $status;
            \Cleantalk\ApbctWP\Helper::apbct_cookie__set( 'ct_sfw_pass_key', $cookie_val, time() + 86400 * 30, '/', null, false );
        }
	}
	
	/**
	 * Shows DIE page.
	 * Stops script executing.
	 *
	 * @param $result
	 */
	public function _die( $result ){
		
		global $apbct, $wpdb;
		
		parent::_die( $result );
		
		// Statistics
		if(!empty($this->blocked_ips)){
			reset($this->blocked_ips);
			$this->apbct->stats['last_sfw_block']['time'] = time();
			$this->apbct->stats['last_sfw_block']['ip'] = $result['ip'];
			$this->apbct->save('stats');
		}
		
		// File exists?
		if(file_exists(CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page_sfw.html")){
			
			$sfw_die_page = file_get_contents(CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page_sfw.html");

            $net_count = $wpdb->get_var('SELECT COUNT(*) FROM ' . APBCT_TBL_FIREWALL_DATA );

            $status = $result['status'] == 'PASS_SFW__BY_WHITELIST' ? '1' : '0';
            $cookie_val = md5( $result['ip'] . $this->api_key ) . $status;

			// Translation
			$replaces = array(
				'{SFW_DIE_NOTICE_IP}'              => __('SpamFireWall is activated for your IP ', 'cleantalk-spam-protect'),
				'{SFW_DIE_MAKE_SURE_JS_ENABLED}'   => __( 'To continue working with the web site, please make sure that you have enabled JavaScript.', 'cleantalk-spam-protect' ),
				'{SFW_DIE_CLICK_TO_PASS}'          => __('Please click the link below to pass the protection,', 'cleantalk-spam-protect'),
				'{SFW_DIE_YOU_WILL_BE_REDIRECTED}' => sprintf(__('Or you will be automatically redirected to the requested page after %d seconds.', 'cleantalk-spam-protect'), 3),
				'{CLEANTALK_TITLE}'                => ($this->test ? __('This is the testing page for SpamFireWall', 'cleantalk-spam-protect') : ''),
				'{REMOTE_ADDRESS}'                 => $result['ip'],
				'{SERVICE_ID}'                     => $this->apbct->data['service_id'] . ', ' . $net_count,
				'{HOST}'                           => get_home_url() . ', ' . APBCT_VERSION,
				'{GENERATED}'                      => '<p>The page was generated at&nbsp;' . date( 'D, d M Y H:i:s' ) . "</p>",
				'{REQUEST_URI}'                    => Server::get( 'REQUEST_URI' ),
				
				// Cookie
				'{COOKIE_PREFIX}'      => '',
				'{COOKIE_DOMAIN}'      => $this->cookie_domain,
				'{COOKIE_SFW}'         => $this->test ? $this->test_ip : $cookie_val,
				'{COOKIE_ANTICRAWLER}' => hash( 'sha256', $apbct->api_key . $apbct->data['salt'] ),
				
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
			wp_die("IP BLACKLISTED. Blocked by SFW " . $result['ip'], "Blacklisted", Array('response'=>403));
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
                $value['status'] = $value['status'] === 'DENY_ANTICRAWLER_UA' ? 'BOT_PROTECTION'   : $value['status'];
                $value['status'] = $value['status'] === 'PASS_ANTICRAWLER_UA' ? 'BOT_PROTECTION'   : $value['status'];
				
				$value['status'] = $value['status'] === 'DENY_ANTIFLOOD'      ? 'FLOOD_PROTECTION' : $value['status'];
				$value['status'] = $value['status'] === 'PASS_ANTIFLOOD'      ? 'FLOOD_PROTECTION' : $value['status'];
				
				$value['status'] = $value['status'] === 'PASS_SFW__BY_COOKIE' ? null               : $value['status'];
                $value['status'] = $value['status'] === 'PASS_SFW'            ? null               : $value['status'];
				$value['status'] = $value['status'] === 'DENY_SFW'            ? null               : $value['status'];

                $data[] = array(
					trim( $value['ip'] ),                                      // IP
                    $value['blocked_entries'],                                 // Count showing of block pages
					$value['all_entries'] - $value['blocked_entries'],         // Count passed requests after block pages
					$value['entries_timestamp'],                               // Last timestamp
                    $value['status'],                                          // Status
                    $value['ua_name'],                                         // User-Agent name
                    $value['ua_id'],                                           // User-Agent ID
				);
				
			}
			unset( $key, $value );
			
			//Sending the request
			$result = API::method__sfw_logs( $ct_key, $data );
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

	    global $apbct;

		// Getting remote file name
		if( ! $file_url ){
			
			$result = API::method__get_2s_blacklists_db($ct_key, 'multifiles', '3_0');
			
			sleep(4);
			
			if( empty( $result['error'] ) ){

			    // User-Agents blacklist
                if( ! empty( $result['file_ua_url'] ) && $apbct->settings['sfw__anti_crawler'] ){
                    $ua_bl_res = AntiCrawler::update( trim( $result['file_ua_url'] ) );
                    if( ! empty( $ua_bl_res['error'] ) )
                        $apbct->error_add( 'sfw_update', $ua_bl_res['error'] );
                }

                // Common blacklist
				if( ! empty( $result['file_url'] ) ){
					
					$file_url = trim( $result['file_url'] );
					
					$response_code = Helper::http__request__get_response_code( $file_url );
					
					if( empty( $response_code['error'] ) ){
						
						if( $response_code == 200 || $response_code == 501 ){
							
							$gz_data = Helper::http__request__get_content( $file_url );
							
							if( empty( $gz_data['error'] ) ){
								
								if( Helper::get_mime_type( $gz_data, 'application/x-gzip' ) ){
									
									if( function_exists( 'gzdecode' ) ){
										
										$data = gzdecode( $gz_data );
										
										if( $data !== false ){

                                            $lines = Helper::buffer__parse__csv( $data );

                                            $patterns   = array();
                                            $patterns[] = 'get';

                                            if( ! $immediate ){
                                                $patterns[] = 'async';
                                            }

                                            return Helper::http__request__rc_to_host(
                                                get_option( 'siteurl' ),
                                                array(
                                                    'spbc_remote_call_token'  => md5( $ct_key ),
                                                    'spbc_remote_call_action' => 'sfw_update',
                                                    'plugin_name'             => 'apbct',
                                                    'file_urls'               => str_replace( array( 'http://', 'https://' ), '', $file_url ),
                                                    'url_count'               => count( $lines ),
                                                    'current_url'             => 0,
                                                    // Additional params
                                                    'firewall_updating_id'    => $apbct->fw_stats['firewall_updating_id'],
                                                ),
                                                $patterns
                                            );

										}else
											return array('error' => 'COULD_DECODE_MULTIFILE');
									}else
										return array('error' => 'FUNCTION_GZ_DECODE_DOES_NOT_EXIST');
								}else
									return array('error' => 'WRONG_MULTIFILE_MIME_TYPE');
							}else
								return array('error' => 'COULD_NOT_GET_MULTIFILE: ' . $gz_data['error'] );
						}else
							return array('error' => 'MULTIFILE_BAD_RESPONSE_CODE: '. (int) $response_code );
					}else
						return array('error' => 'MULTIFILE_COULD_NOT_GET_RESPONSE_CODE: '. $response_code['error'] );
				}else
					return array('error' => 'NO_REMOTE_MULTIFILE_FOUND: ' . $result['file_url'] );
			}else
				return $result;
		}else{
			
			$response_code = Helper::http__request( 'https://' . $file_url, array(), 'get_code' );
			
			if( empty( $response_code['error'] ) ){
			
				if( $response_code == 200 || $response_code == 501 ){ // Check if it's there
					
					$gz_data = Helper::http__request__get_content( $file_url );
					
					if( empty( $gz_data['error'] ) ){
						
						if( Helper::get_mime_type( $gz_data, 'application/x-gzip' ) ){
							
							if( function_exists( 'gzdecode' ) ){
								
								$data = gzdecode( $gz_data );
								
								if( $data !== false ){
									
									$lines = Helper::buffer__parse__csv( $data );
									
								}else
									return array('error' => 'COULD_DECODE_FILE');
							}else
								return array('error' => 'FUNCTION_GZ_DECODE_DOES_NOT_EXIST');
						}else
							return array('error' => 'WRONG_FILE_MIME_TYPE');
						
						reset( $lines );
						
						for( $count_result = 0; current($lines) !== false; ) {
							
							$query = "INSERT INTO ".$db__table__data." (network, mask, status) VALUES ";
							
							for( $i = 0, $values = array(); APBCT_WRITE_LIMIT !== $i && current( $lines ) !== false; $i ++, $count_result ++, next( $lines ) ){
								
								$entry = current($lines);
								
								if(empty($entry))
									continue;
								
								if ( APBCT_WRITE_LIMIT !== $i ) {
								
									// Cast result to int
									$ip   = preg_replace('/[^\d]*/', '', $entry[0]);
									$mask = preg_replace('/[^\d]*/', '', $entry[1]);
									$private = isset($entry[2]) ? $entry[2] : 0;
									
								}
								
								$values[] = '('. $ip .','. $mask .','. $private .')';
								
							}
							
							if( ! empty( $values ) ){
								$query = $query . implode( ',', $values ) . ';';
								$db->execute( $query );
							}
							
						}
						
						return $count_result;
						
					}else
						return array('error' => 'COULD_NOT_GET_FILE: ' . $gz_data['error'] );
				}else
					return array('error' => 'FILE_BAD_RESPONSE_CODE: '. (int) $response_code );
			}else
				return array('error' => 'FILE_COULD_NOT_GET_RESPONSE_CODE: '. $response_code['error'] );
		}
	}

    /**
     * Creatin a temporary updating table
     *
     * @param \wpdb $db database handler
     */
    public static function create_temp_tables( $db ){
        global $wpdb, $apbct;
        $sql = 'SHOW TABLES LIKE "%scleantalk_sfw";';
        $sql = sprintf( $sql, $wpdb->prefix ); // Adding current blog prefix
        $result = $wpdb->get_var( $sql );
        if( ! $result ){
            apbct_activation__create_tables( Schema::getSchema('sfw'), $apbct->db_prefix );
        }
        $db->execute( 'CREATE TABLE IF NOT EXISTS `' . APBCT_TBL_FIREWALL_DATA . '_temp` LIKE `' . APBCT_TBL_FIREWALL_DATA . '`;' );
        $db->execute( 'TRUNCATE TABLE `' . APBCT_TBL_FIREWALL_DATA . '_temp`;' );
    }

    /**
     * Removing a temporary updating table
     *
     * @param \wpdb $db database handler
     */
    public static function delete_main_data_tables( $db ){
        $db->execute( 'DROP TABLE `'. APBCT_TBL_FIREWALL_DATA .'`;' );
    }

    /**
     * Renamin a temporary updating table into production table name
     *
     * @param \wpdb $db database handler
     */
    public static function rename_data_tables( $db ){
        $db->execute( 'ALTER TABLE `'. APBCT_TBL_FIREWALL_DATA .'_temp` RENAME `'. APBCT_TBL_FIREWALL_DATA .'`;' );
    }

}