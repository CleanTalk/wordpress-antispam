<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\ApbctWP\API;
use Cleantalk\ApbctWP\DB;
use Cleantalk\ApbctWP\Helper;
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
	private $data__set_cookies = false;
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

			if( strpos( Cookie::get( 'ct_sfw_pass_key' ), md5( $current_ip . $this->api_key ) ) === 0 ){

                if( Cookie::get( 'ct_sfw_passed' ) ){

                    if( ! headers_sent() ){
                        \Cleantalk\ApbctWP\Variables\Cookie::set(
                            'ct_sfw_passed',
                            '0',
                            time() + 86400 * 3,
                            '/',
                            null,
                            null,
                            true,
                            'Lax' );
                    } else {
                        $results[] = array(
                            'ip'          => $current_ip,
                            'is_personal' => false,
                            'status'      => 'PASS_SFW__BY_COOKIE'
                        );
                    }

                    // Do logging an one passed request
                    $this->update_log( $current_ip, 'PASS_SFW' );

                    if( $this->sfw_counter ){
                        $this->apbct->data['admin_bar__sfw_counter']['all'] ++;
                        $this->apbct->saveData();
                    }

                }

                if( strlen( Cookie::get( 'ct_sfw_pass_key' ) ) > 32 ) {
                    $status = substr( Cookie::get( 'ct_sfw_pass_key' ), -1 );
                }

                if( $status ) {
                    $results[] = array(
                        'ip'          => $current_ip,
                        'is_personal' => false,
                        'status'      => 'PASS_SFW__BY_WHITELIST'
                    );
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
				network, mask, status, source
				FROM " . $this->db__table__data . "
				WHERE network IN (". implode( ',', $needles ) .")
				AND	network = " . $current_ip_v4 . " & mask 
				AND " . rand( 1, 100000 ) . "  
				ORDER BY status DESC");
            
    
			if( ! empty( $db_results ) ){
				
				foreach( $db_results as $db_result ){
                    
                    $result_entry = array(
                        'ip'          => $current_ip,
                        'network'     => long2ip( $db_result['network'] ) . '/' . Helper::ip__mask__long_to_number( $db_result['mask'] ),
                        'is_personal' => $db_result['source'],
                    );
                    
					if( (int) $db_result['status'] === 1 ) { $result_entry['status'] = 'PASS_SFW__BY_WHITELIST'; break; }
                    if( (int) $db_result['status'] === 0 ) { $result_entry['status'] = 'DENY_SFW'; }
					
				}
				
			}else{
                
                $result_entry = array(
                    'ip'          => $current_ip,
                    'is_personal' => null,
                    'status'      => 'PASS_SFW',
                );
                
			}
		 
			$results[] = $result_entry;
		}
		
		return $results;
	}
    
    /**
     * Add entry to SFW log.
     * Writes to database.
     *
     * @param string $ip
     * @param $status
     * @param string $network
     * @param string $source
     */
	public function update_log( $ip, $status, $network = 'NULL', $source = 'NULL' ) {

		$id   = md5( $ip . $this->module_name );
		$time = time();

		$this->db->prepare( "INSERT INTO " . $this->db__table__logs . "
            SET
                id = '$id',
                ip = '$ip',
                status = '$status',
                all_entries = 1,
                blocked_entries = " . ( strpos( $status, 'DENY' ) !== false ? 1 : 0 ) . ",
                entries_timestamp = '" . $time . "',
                ua_name = %s,
                source = $source,
                network = %s,
                first_url = %s,
                last_url = %s
            ON DUPLICATE KEY
            UPDATE
                status = '$status',
                source = $source,
                all_entries = all_entries + 1,
                blocked_entries = blocked_entries" . ( strpos( $status, 'DENY' ) !== false ? ' + 1' : '' ) . ",
                entries_timestamp = '" . intval( $time ) . "',
                ua_name = %s,
                network = %s,
                last_url = %s",
            array( 
                Server::get('HTTP_USER_AGENT'),
                $network,
                substr( Server::get( 'HTTP_HOST' ) . Server::get( 'REQUEST_URI' ), 0, 100 ),
                substr( Server::get( 'HTTP_HOST' ) . Server::get( 'REQUEST_URI' ), 0, 100 ),
    
                Server::get('HTTP_USER_AGENT') ,
                $network,
                substr( Server::get( 'HTTP_HOST' ) . Server::get( 'REQUEST_URI' ), 0, 100 ),
            )
        );
		$this->db->execute( $this->db->get_query() );
	}
	
	public function actions_for_denied( $result ){
		
		if( $this->sfw_counter ){
			$this->apbct->data['admin_bar__sfw_counter']['blocked']++;
			$this->apbct->saveData();
		}
		
	}
	
	public function actions_for_passed( $result ){
		if( $this->data__set_cookies == 1 && ! headers_sent() ) {
		    $status = $result['status'] == 'PASS_SFW__BY_WHITELIST' ? '1' : '0';
            $cookie_val = md5( $result['ip'] . $this->api_key ) . $status;
            \Cleantalk\ApbctWP\Variables\Cookie::setNativeCookie(
                'ct_sfw_pass_key',
                $cookie_val,
                time() + 86400 * 30,
                '/',
                null,
                null );
        }
	}
	
	/**
	 * Shows DIE page.
	 * Stops script executing.
	 *
	 * @param $result
	 */
	public function _die( $result ){
		
		global $apbct;
		
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

            $net_count = $apbct->stats['sfw']['entries'];

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

            http_response_code(403);
            die($sfw_die_page);

		}else{
            http_response_code(403);
            die("IP BLACKLISTED. Blocked by SFW " . $result['ip']);
		}
		
	}
    
    /**
     * Sends and wipe SFW log
     *
     * @param $db
     * @param $log_table
     * @param string $ct_key API key
     * @param bool $use_delete_command Determs whether use DELETE or TRUNCATE to delete the logs table data
     *
     * @return array|bool array('error' => STRING)
     */
	public static function send_log( $db, $log_table, $ct_key, $use_delete_command ) {
	 
		//Getting logs
		$query = "SELECT * FROM " . $log_table . ";";
		$db->fetch_all( $query );
		
		if( count( $db->result ) ){
			
			//Compile logs
			$data = array();
			foreach( $db->result as $key => &$value ){
				
				// Converting statuses to API format
				$value['status'] = $value['status'] === 'DENY_ANTICRAWLER'    ? 'BOT_PROTECTION'   : $value['status'];
				$value['status'] = $value['status'] === 'PASS_ANTICRAWLER'    ? 'BOT_PROTECTION'   : $value['status'];
                $value['status'] = $value['status'] === 'DENY_ANTICRAWLER_UA' ? 'BOT_PROTECTION'   : $value['status'];
                $value['status'] = $value['status'] === 'PASS_ANTICRAWLER_UA' ? 'BOT_PROTECTION'   : $value['status'];
				
				$value['status'] = $value['status'] === 'DENY_ANTIFLOOD'      ? 'FLOOD_PROTECTION' : $value['status'];
				$value['status'] = $value['status'] === 'PASS_ANTIFLOOD'      ? 'FLOOD_PROTECTION' : $value['status'];
				$value['status'] = $value['status'] === 'DENY_ANTIFLOOD_UA'   ? 'FLOOD_PROTECTION' : $value['status'];
				$value['status'] = $value['status'] === 'PASS_ANTIFLOOD_UA'   ? 'FLOOD_PROTECTION' : $value['status'];
				
				$value['status'] = $value['status'] === 'PASS_SFW__BY_COOKIE' ? 'DB_MATCH'         : $value['status'];
                $value['status'] = $value['status'] === 'PASS_SFW'            ? 'DB_MATCH'         : $value['status'];
				$value['status'] = $value['status'] === 'DENY_SFW'            ? 'DB_MATCH'         : $value['status'];
                
                $value['status'] = $value['source']                           ? 'PERSONAL_LIST_MATCH' : $value['status'];
				
				$additional = array();
				if( $value['network'] ){
                    $additional['nd'] = $value['network'];
                }
                if( $value['first_url'] ){
                    $additional['fu'] = $value['first_url'];
                }
                if( $value['last_url'] ){
                    $additional['lu'] = $value['last_url'];
                }
                $additional = $additional ?: 'EMPTY_ASSOCIATIVE_ARRAY';
				
                $data[] = array(
					trim( $value['ip'] ),                                      // IP
                    $value['blocked_entries'],                                 // Count showing of block pages
					$value['all_entries'] - $value['blocked_entries'],         // Count passed requests after block pages
					$value['entries_timestamp'],                               // Last timestamp
                    $value['status'],                                          // Status
                    $value['ua_name'],                                         // User-Agent name
                    $value['ua_id'],                                           // User-Agent ID
                    $additional                                                // Network, first URL, last URL
				);
				
			}
			unset( $key, $value );
			
			//Sending the request
			$result = API::method__sfw_logs( $ct_key, $data );
			//Checking answer and deleting all lines from the table
			if( empty( $result['error'] ) ){
				if( $result['rows'] == count( $data ) ){
				    
                    $db->execute( "BEGIN;" );
				    if( $use_delete_command ){
                        $db->execute( "DELETE FROM " . $log_table . ";" );
                    }else{
                        $db->execute( "TRUNCATE TABLE " . $log_table . ";" );
                    }
                    $db->execute( "COMMIT;" );
					
					return $result;
				}
				
				return array( 'error' => 'SENT_AND_RECEIVED_LOGS_COUNT_DOESNT_MACH' );
			} else{
				return $result;
			}
			
		} else{
            return array( 'rows' => 0 );
		}
	}

	/**
	 * Gets multifile with data to update Firewall.
	 *
	 * @param string $api_key API key
	 *
	 * @return array
	 */
	public static function direct_update__get_db( $api_key ){

		// Getting remote file name
		$result = API::method__get_2s_blacklists_db( $api_key, null, '3_0' );

		if( empty( $result['error'] ) ){

			return array(
				'blacklist'  => $result['data'],
				'useragents' => $result['data_user_agents'],
			);

		}else
			return $result;
	}

	public static function direct_update( $db, $db__table__data, array $blacklists ){

		for( $count_result = 0; current($blacklists) !== false; ) {

			$query = "INSERT INTO ".$db__table__data." (network, mask, status) VALUES ";

			for( $i = 0, $values = array(); APBCT_WRITE_LIMIT !== $i && current( $blacklists ) !== false; $i ++, $count_result ++, next( $blacklists ) ){

				$entry = current($blacklists);

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
				$query .= implode( ',', $values ) . ';';
				$db->execute( $query );
			}

		}

		return $count_result;

	}
    
    /**
     * Gets multifile with data to update Firewall.
     *
     * @param string $api_key API key
     *
     * @return array
     */
    public static function update__get_multifile( $api_key ){
        
        // Getting remote file name
        $result = API::method__get_2s_blacklists_db( $api_key, 'multifiles', '3_1' );

        if( empty( $result['error'] ) ){
            
            if( ! empty( $result['file_url'] ) ){
                
                $data = Helper::http__get_data_from_remote_gz__and_parse_csv( $result['file_url'] );
                
                if( empty( $data['error'] ) ){
                    
                    return array(
                        'multifile_url' => trim( $result['file_url'] ),
                        'useragent_url' => trim( $result['file_ua_url'] ),
                        'file_urls'     => $data,
                        'file_ck_url'   => trim( $result['file_ck_url'] ),
                    );
                    
                }else
                    return array( 'error' => 'FW. Get multifile. ' . $data['error'] );
            }else
                return array( 'error' => 'FW. Get multifile. BAD_RESPONSE' );
        }else
            return $result;
    }
    
    /**
     * Updates SFW local base
     *
     * @param $db
     * @param $db__table__data
     * @param null|string $file_url File URL with SFW data.
     *
     * @return array|bool array('error' => STRING)
     */
    public static function update__write_to_db( $db, $db__table__data, $file_url = null ){
        
        $data = Helper::http__get_data_from_remote_gz__and_parse_csv( $file_url );
        
        if( empty( $data['errors'] ) ){
            
            for( $count_result = 0; current($data) !== false; ) {
                
                $query = "INSERT INTO ".$db__table__data." (network, mask, status, source) VALUES ";
                
                for( $i = 0, $values = array(); APBCT_WRITE_LIMIT !== $i && current( $data ) !== false; $i ++, $count_result ++, next( $data ) ){
                    
                    $entry = current($data);
                    
                    if(empty($entry))
                        continue;
                    
                    if ( APBCT_WRITE_LIMIT !== $i ) {
                        
                        if( empty( $entry[0] )  || empty ($entry[1] ) ){
                            continue;
                        }
                        
                        // Cast result to int
                        $ip     = preg_replace( '/[^\d]*/', '', $entry[0] );
                        $mask   = preg_replace( '/[^\d]*/', '', $entry[1] );
                        $status = isset( $entry[2] ) ? $entry[2]       : 0;
                        $source = isset( $entry[3] ) ? (int) $entry[3] : 'NULL';

                        $values[] = "($ip, $mask, $status, $source)";
                    }
                    
                }
                
                if( ! empty( $values ) ){
                    $query .= implode( ',', $values ) . ';';
                    $db->execute( $query );
                }
                
            }
            
            return $count_result;
            
        }else
            return $data;
    }
    
	public static function update__write_to_db__exclusions( $db, $db__table__data, $exclusions = array() ) {

		$query = 'INSERT INTO `' . $db__table__data . '` (network, mask, status) VALUES ';
		
		//Exclusion for servers IP (SERVER_ADDR)
		if ( Server::get('HTTP_HOST') ) {

			// Do not add exceptions for local hosts
			if( ! in_array( Server::get_domain(), array( 'lc', 'loc', 'lh' ) ) ){
				$exclusions[] = Helper::dns__resolve( Server::get( 'HTTP_HOST' ) );
				$exclusions[] = '127.0.0.1';
            
            // And delete all 127.0.0.1 entries for local hosts
			}else{
			    global $wpdb;
			    $wpdb->query( 'DELETE FROM ' . $db__table__data . ' WHERE network = ' . ip2long( '127.0.0.1' ) . ';' );
            }
		}

		foreach ( $exclusions as $exclusion ) {
			if ( Helper::ip__validate( $exclusion ) && sprintf( '%u', ip2long( $exclusion ) ) ) {
				$query .= '(' . sprintf( '%u', ip2long( $exclusion ) ) . ', ' . sprintf( '%u', bindec( str_repeat( '1', 32 ) ) ) . ', 1),';
			}
		}

		if( $exclusions ){

			$sql_result = $db->execute( substr( $query, 0, - 1 ) . ';' );

			return $sql_result === false
				? array( 'error' => 'COULD_NOT_WRITE_TO_DB 4: ' . $db->get_last_error() )
				: count( $exclusions );
		}

		return 0;

	}
    
    /**
     * Creating a temporary updating table
     *
     * @param DB $db database handler
     * @param array|string $table_names Array with table names to create
     *
     * @return bool|array
     */
    public static function create_temp_tables( $db, $table_names ){
    
        // Cast it to array for simple input
        $table_names = (array) $table_names;
    
        foreach( $table_names as $table_name ){
    
            $table_name__temp = $table_name . '_temp';
            
            if( ! $db->execute( 'CREATE TABLE IF NOT EXISTS `' . $table_name__temp . '` LIKE `' . $table_name . '`;' ) ){
                return array( 'error' => 'CREATE TEMP TABLES: COULD NOT CREATE' . $table_name__temp );
            }
            
            if( ! $db->execute( 'TRUNCATE TABLE `' . $table_name__temp . '`;' ) ){
                return array( 'error' => 'CREATE TEMP TABLES: COULD NOT TRUNCATE' . $table_name__temp );
            }
        }
        
        return true;
    }
    
    /**
     * Delete tables with given names if they exists
     *
     * @param DB $db
     * @param array|string $table_names Array with table names to delete
     *
     * @return bool|array
     */
    public static function data_tables__delete( $db, $table_names ){
        
        // Cast it to array for simple input
        $table_names = (array) $table_names;
        
        foreach( $table_names as $table_name ){
            
            if( ! $db->isTableExists( $table_name ) ){
                return array( 'error' => 'DELETE TABLE: TABLE IS NOT EXISTS: ' . $table_name);
            }
            
            $db->execute( 'DROP TABLE ' . $table_name . ';' );
        }
        
        return true;
    }
    
    /**
     * Renaming a temporary updating table into production table name
     *
     * @param DB $db database handler
     * @param array|string $table_names Array with table names to rename
     *
     * @return bool|array
     */
    public static function rename_data_tables__from_temp_to_main( $db, $table_names ){
    
        // Cast it to array for simple input
        $table_names = (array) $table_names;
    
        foreach( $table_names as $table_name ){
    
            $table_name__temp = $table_name . '_temp';
            
            if( ! $db->isTableExists( $table_name__temp ) )
                return array( 'error' => 'RENAME TABLE: TEMPORARY TABLE IS NOT EXISTS: ' . $table_name__temp );
            
            if( $db->isTableExists( $table_name  ) )
                return array( 'error' => 'RENAME TABLE: MAIN TABLE IS STILL EXISTS: ' . $table_name );
            
            $db->execute( 'ALTER TABLE `' . $table_name__temp . '` RENAME `' . $table_name . '`;' );
            
        }
        
        return true;
    }

}