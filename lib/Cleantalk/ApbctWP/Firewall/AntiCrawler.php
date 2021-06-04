<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\Common\Helper as Helper;
use Cleantalk\Variables\Cookie;
use Cleantalk\Variables\Server;

class AntiCrawler extends \Cleantalk\Common\Firewall\FirewallModule{
	
	public $module_name = 'ANTICRAWLER';
	
	private $db__table__ac_logs = null;
	private $db__table__ac_ua_bl = null;
	private $api_key = '';
	private $apbct = false;
	private $store_interval = 60;
	private $sign; //Signature - User-Agent + Protocol
    private $ua_id = 'null'; //User-Agent

	private $ac_log_result = '';
	
	public $isExcluded = false;
	
	/**
	 * AntiBot constructor.
	 *
	 * @param $log_table
	 * @param $ac_logs_table
	 * @param array $params
	 */
	public function __construct( $log_table, $ac_logs_table, $params = array() ) {
		
		global $apbct;
		$this->apbct = $apbct;
		$this->db__table__logs    = $log_table ?: null;
		$this->db__table__ac_logs = $ac_logs_table ?: null;
        $this->db__table__ac_ua_bl= defined('APBCT_TBL_AC_UA_BL') ? APBCT_TBL_AC_UA_BL : null;
		$this->sign = md5( Server::get('HTTP_USER_AGENT') . Server::get('HTTPS') . Server::get('HTTP_HOST') );
		
		foreach( $params as $param_name => $param ){
			$this->$param_name = isset( $this->$param_name ) ? $param : false;
		}

		$this->isExcluded = $this->check_exclusions();
		
	}

    public static function update( $file_url_ua ) {
	    
        $lines = \Cleantalk\ApbctWP\Helper::http__get_data_from_remote_gz__and_parse_csv( $file_url_ua );
    
        if( empty( $lines['errors'] ) ){
            
            $result__clear_db = self::clear_data_table( \Cleantalk\ApbctWP\DB::getInstance(), APBCT_TBL_AC_UA_BL );
        
            if( empty( $result__clear_db['error'] ) ){

                for( $count_result = 0; current($lines) !== false; ) {

                    $query = "INSERT INTO " . APBCT_TBL_AC_UA_BL . " (id, ua_template, ua_status) VALUES ";

                    for( $i = 0, $values = array(); APBCT_WRITE_LIMIT !== $i && current( $lines ) !== false; $i ++, $count_result ++, next( $lines ) ){

                        $entry = current($lines);

                        if(empty($entry))
                            continue;

                        if ( APBCT_WRITE_LIMIT !== $i ) {

                            if( ! isset( $entry[0], $entry[1] ) ){
                                continue;
                            }

                            // Cast result to int
                            $ua_id        = preg_replace('/[^\d]*/', '', $entry[0]);
                            $ua_template  = isset($entry[1]) && apbct_is_regexp($entry[1]) ? Helper::db__prepare_param( $entry[1] ) : 0;
                            $ua_status    = isset($entry[2]) ? $entry[2] : 0;

                            if( ! $ua_id || ! $ua_template ){
                                continue;
                            }
                        }

                        $values[] = '('. $ua_id .','. $ua_template .','. $ua_status .')';

                    }

                    if( ! empty( $values ) ){
                        $query = $query . implode( ',', $values ) . ';';
                        \Cleantalk\ApbctWP\DB::getInstance()->execute( $query );
                    }

                }
                return $count_result;
            }else
                return $result__clear_db;
        }else
            return array('error' => 'UAL_UPDATE_ERROR: '. $lines['error'] );
    }

	public static function direct_update( array $useragents ) {

		$result__clear_db = self::clear_data_table( \Cleantalk\ApbctWP\DB::getInstance(), APBCT_TBL_AC_UA_BL );

		if( empty( $result__clear_db['error'] ) ){

			for( $count_result = 0; current($useragents) !== false; ) {

				$query = "INSERT INTO " . APBCT_TBL_AC_UA_BL . " (id, ua_template, ua_status) VALUES ";

				for( $i = 0, $values = array(); APBCT_WRITE_LIMIT !== $i && current( $useragents ) !== false; $i ++, $count_result ++, next( $useragents ) ){

					$entry = current($useragents);

					if(empty($entry))
						continue;

					if ( APBCT_WRITE_LIMIT !== $i ) {

						// Cast result to int
						// @ToDo check the output $entry
						$ua_id        = preg_replace('/[^\d]*/', '', $entry[0]);
						$ua_template  = isset($entry[1]) && apbct_is_regexp($entry[1]) ? Helper::db__prepare_param( $entry[1] ) : 0;
						$ua_status    = isset($entry[2]) ? $entry[2] : 0;

					}

					$values[] = '('. $ua_id .','. $ua_template .','. $ua_status .')';

				}

				if( ! empty( $values ) ){
					$query = $query . implode( ',', $values ) . ';';
					\Cleantalk\ApbctWP\DB::getInstance()->execute( $query );
				}

			}
			return $count_result;
		}else
			return $result__clear_db;

	}

    private static function clear_data_table($db, $db__table__data) {

        $db->execute( "TRUNCATE TABLE {$db__table__data};" );
        $db->set_query( "SELECT COUNT(*) as cnt FROM {$db__table__data};" )->fetch(); // Check if it is clear
        if( $db->result['cnt'] != 0 ){
            $db->execute( "DELETE FROM {$db__table__data};" ); // Truncate table
            $db->set_query( "SELECT COUNT(*) as cnt FROM {$db__table__data};" )->fetch(); // Check if it is clear
            if( $db->result['cnt'] != 0 ){
                return array( 'error' => 'COULD_NOT_CLEAR_UA_BL_TABLE' ); // throw an error
            }
        }
        $db->execute( "ALTER TABLE {$db__table__data} AUTO_INCREMENT = 1;" ); // Drop AUTO INCREMENT

    }

    /**
	 * Use this method to execute main logic of the module.
	 *
	 * @return array  Array of the check results
	 */
	public function check() {
		
		$results = array();
				
        foreach( $this->ip_array as $ip_origin => $current_ip ) {
	        
        	// Skip by 301 response code
	        if( $this->is_redirected() ){
		        $results[] = array( 'ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_ANTICRAWLER', );
		        return $results;
	        }

            // UA check
            $ua_bl_results = $this->db->fetch_all(
                "SELECT * FROM " . $this->db__table__ac_ua_bl . " ORDER BY `ua_status` DESC;"
            );

            if( ! empty( $ua_bl_results ) ){

                $is_blocked = false;

                foreach( $ua_bl_results as $ua_bl_result ){

                    if( ! empty( $ua_bl_result['ua_template'] ) && preg_match( "%". str_replace( '"', '', $ua_bl_result['ua_template'] ) ."%i", Server::get('HTTP_USER_AGENT') ) ) {

                        $this->ua_id = $ua_bl_result['id'];

                        if( $ua_bl_result['ua_status'] == 1 ) {
                            // Whitelisted
                            $results[] = array('ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_ANTICRAWLER_UA',);
                            return $results;
                            break;
                        } else {
                            // Blacklisted
                            $results[] = array('ip' => $current_ip, 'is_personal' => false, 'status' => 'DENY_ANTICRAWLER_UA',);
                            $is_blocked = true;
                            break;
                        }

                    }

                }

                if( ! $is_blocked ) {
                    $results[] = array('ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_ANTICRAWLER_UA',);
                }

            }
        	
            // Skip by cookie
            if( Cookie::get('wordpress_apbct_antibot') == hash( 'sha256', $this->api_key . $this->apbct->data['salt'] ) ) {
                if( Cookie::get( 'apbct_anticrawler_passed' ) == 1 ){
                    if( ! headers_sent() )
                        Cookie::set( 'apbct_anticrawler_passed', '0', time() - 86400, '/', null, null, true, 'Lax' );

                    // Do logging an one passed request
                    $this->update_log( $current_ip, 'PASS_ANTICRAWLER' );
                }

                $results[] = array( 'ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_ANTICRAWLER', );

                return $results;

            }
        }
		
        // Common check
		foreach( $this->ip_array as $ip_origin => $current_ip ){

		    // IP check
			$result = $this->db->fetch(
				"SELECT ip"
				. ' FROM `' . $this->db__table__ac_logs . '`'
				. " WHERE ip = '$current_ip'"
				. " AND ua = '$this->sign' AND " . rand( 1, 100000 ) . ";"
			);
			if( isset( $result['ip'] ) ){
				
				if( Cookie::get('wordpress_apbct_antibot') !== hash( 'sha256', $this->api_key . $this->apbct->data['salt'] ) ){
					
					$results[] = array( 'ip' => $current_ip, 'is_personal' => false, 'status' => 'DENY_ANTICRAWLER', );
					
				}else{
					
					if( Cookie::get( 'apbct_anticrawler_passed' ) === '1' ){
						
						if( ! headers_sent() )
							\Cleantalk\ApbctWP\Variables\Cookie::set( 'apbct_anticrawler_passed', '0', time() - 86400, '/', null, false, true, 'Lax' );
						
						$results[] = array( 'ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_ANTICRAWLER', );
						
						return $results;
					}
				}
				
			}else{

                if( ! Cookie::get('wordpress_apbct_antibot') ) {
                    add_action( 'template_redirect', array( & $this, 'update_ac_log' ), 999 );
                }
				
				add_action( 'wp_head', array( '\Cleantalk\ApbctWP\Firewall\AntiCrawler', 'set_cookie' ) );
				add_action( 'login_head', array( '\Cleantalk\ApbctWP\Firewall\AntiCrawler', 'set_cookie' ) );
				
			}

		}
		
		return $results;
		
	}
	
	public function update_ac_log() {
		$interval_time = Helper::time__get_interval_start( $this->store_interval );
		
		// @todo Rename ip column to sign. Use IP + UserAgent for it.
		
		foreach( $this->ip_array as $ip_origin => $current_ip ){
			$id = md5( $current_ip . $this->sign . $interval_time );
			$this->db->execute(
				"INSERT INTO " . $this->db__table__ac_logs . " SET
					id = '$id',
					ip = '$current_ip',
					ua = '$this->sign',
					entries = 1,
					interval_start = $interval_time
				ON DUPLICATE KEY UPDATE
					ip = ip,
					entries = entries + 1,
					interval_start = $interval_time;"
			);
		}
		
	}
	
	
	public static function set_cookie(){
		global $apbct;
		echo '<script>var ctSecure = location.protocol === "https:" ? "; secure" : ""; document.cookie = "wordpress_apbct_antibot=' . hash( 'sha256', $apbct->api_key . $apbct->data['salt'] ) . '; path=/; expires=0; samesite=lax" + ctSecure;</script>';
	}
	
	/**
	 * Add entry to SFW log.
	 * Writes to database.
	 *
	 * @param string $ip
	 * @param $status
	 */
	public function update_log( $ip, $status ) {

	    if( strpos( '_UA', $status ) !== false ) {
	        $id_str = $ip . $this->module_name . '_UA';
        } else {
            $id_str = $ip . $this->module_name;
        }
		$id   = md5( $id_str );
		$time = time();
		
		$query = "INSERT INTO " . $this->db__table__logs . "
			SET
				id = '$id',
				ip = '$ip',
				status = '$status',
				all_entries = 1,
				blocked_entries = " . ( strpos( $status, 'DENY' ) !== false ? 1 : 0 ) . ",
				entries_timestamp = '" . intval( $time ) . "',
				ua_id = " . $this->ua_id . ",
				ua_name = %s,
				first_url = %s,
                last_url = %s
			ON DUPLICATE KEY
			UPDATE
			    status = '$status',
				all_entries = all_entries + 1,
				blocked_entries = blocked_entries" . ( strpos( $status, 'DENY' ) !== false ? ' + 1' : '' ) . ",
				entries_timestamp = '" . intval( $time ) . "',
				ua_id = " . $this->ua_id . ",
				ua_name = %s,
				last_url = %s";

		$this->db->prepare(
		    $query,
            array(
                Server::get('HTTP_USER_AGENT'),
                substr( Server::get( 'HTTP_HOST' ) . Server::get( 'REQUEST_URI' ), 0, 100 ),
                substr( Server::get( 'HTTP_HOST' ) . Server::get( 'REQUEST_URI' ), 0, 100 ),
    
                Server::get('HTTP_USER_AGENT'),
                substr( Server::get( 'HTTP_HOST' ) . Server::get( 'REQUEST_URI' ), 0, 100 ),
            )
        );
		$this->db->execute( $this->db->get_query() );
	}
	
	public function _die( $result ){
		
		global $apbct;
		
		// File exists?
		if(file_exists(CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page_anticrawler.html")){
			
			$sfw_die_page = file_get_contents(CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page_anticrawler.html");

			$net_count = $apbct->stats['sfw']['entries'];

			// Translation
			$replaces = array(
				'{SFW_DIE_NOTICE_IP}'              => __('Anti-Crawler Protection is activated for your IP ', 'cleantalk-spam-protect'),
				'{SFW_DIE_MAKE_SURE_JS_ENABLED}'   => __( 'To continue working with the web site, please make sure that you have enabled JavaScript.', 'cleantalk-spam-protect' ),
				'{SFW_DIE_YOU_WILL_BE_REDIRECTED}' => sprintf( __( 'You will be automatically redirected to the requested page after %d seconds.', 'cleantalk-spam-protect' ), 3 ) . '<br>' . __( 'Don\'t close this page. Please, wait for 3 seconds to pass to the page.', 'cleantalk-spam-protect' ),
				'{CLEANTALK_TITLE}'                => __( 'Antispam by CleanTalk', 'cleantalk-spam-protect' ),
				'{REMOTE_ADDRESS}'                 => $result['ip'],
				'{SERVICE_ID}'                     => $this->apbct->data['service_id'] . ', ' . $net_count,
				'{HOST}'                           => get_home_url() . ', ' . APBCT_VERSION,
				'{COOKIE_ANTICRAWLER}'             => hash( 'sha256', $apbct->api_key . $apbct->data['salt'] ),
				'{COOKIE_ANTICRAWLER_PASSED}'      => '1',
				'{GENERATED}'                      => '<p>The page was generated at&nbsp;' . date( 'D, d M Y H:i:s' ) . "</p>",
			);
			
			foreach( $replaces as $place_holder => $replace ){
				$sfw_die_page = str_replace( $place_holder, $replace, $sfw_die_page );
			}
			
			if( isset( $_GET['debug'] ) ){
				$debug = '<h1>Headers</h1>'
				         . str_replace( "\n", "<br>", print_r( \apache_request_headers(), true ) )
				         . '<h1>$_SERVER</h1>'
				         . str_replace( "\n", "<br>", print_r( $_SERVER, true ) )
				         . '<h1>AC_LOG_RESULT</h1>'
				         . str_replace( "\n", "<br>", print_r( $this->ac_log_result, true ) )
				         . '<h1>IPS</h1>'
				         . str_replace( "\n", "<br>", print_r( $this->ip_array, true ) );
			}else{
				$debug = '';
			}
			$sfw_die_page = str_replace( "{DEBUG}", $debug, $sfw_die_page );

            http_response_code(403);
            die($sfw_die_page);

		}else{
            http_response_code(403);
            die("IP BLACKLISTED. Blocked by AntiCrawler " . $result['ip']);
		}
		
	}

    private function check_exclusions() {

	    $allowed_roles = array( 'administrator', 'editor' );
	    $user = apbct_wp_get_current_user();

        if( ! $user ) {
            return false;
        }

	    foreach( $allowed_roles as $role ) {
            if( in_array( $role, (array) $user->roles ) ) {
                return true;
            }
        }

        return false;

    }

	private function is_redirected()
	{
		$is_redirect = false;
		if( Server::get( 'HTTP_REFERER' ) !== '' && Server::get( 'HTTP_HOST' ) !== '' && $this->is_cloudflare() ) {
			$parse_referer = parse_url( Server::get( 'HTTP_REFERER' ) );
			$is_redirect = Server::get( 'HTTP_HOST' ) !== $parse_referer['host'];
		}
		return http_response_code() == 301 || $is_redirect;
	}

	private function is_cloudflare()
	{
		return Server::get('HTTP_CF_RAY') && Server::get('HTTP_CF_CONNECTING_IP') && Server::get('HTTP_CF_REQUEST_ID');
	}
}