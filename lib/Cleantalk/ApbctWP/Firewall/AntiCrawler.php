<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\Common\Helper as Helper;
use Cleantalk\Variables\Cookie;
use Cleantalk\Variables\Server;

class AntiCrawler extends \Cleantalk\Common\Firewall\FirewallModule{
	
	public $module_name = 'ANTICRAWLER';
	
	private $db__table__ac_logs;
	private $api_key = '';
	private $apbct = false;
	private $store_interval = 60;
	private $ua; //User-Agent

	public $isExcluded = false;
	
	/**
	 * AntiBot constructor.
	 *
	 * @param $log_table
	 * @param $ac_logs_table
	 * @param array $params
	 */
	public function __construct( $log_table, $ac_logs_table, $params = array() ) {
		
		$this->db__table__logs    = $log_table ?: null;
		$this->db__table__ac_logs = $ac_logs_table ?: null;
		$this->ua = md5( Server::get('HTTP_USER_AGENT') );
		
		foreach( $params as $param_name => $param ){
			$this->$param_name = isset( $this->$param_name ) ? $param : false;
		}

		$this->isExcluded = $this->check_exclusions();
		
	}
	
	/**
	 * Use this method to execute main logic of the module.
	 *
	 * @return array  Array of the check results
	 */
	public function check() {
		
		$results = array();

        // Skip by cookie
        foreach( $this->ip_array as $ip_origin => $current_ip ) {

            if( Cookie::get('apbct_antibot') == md5( $this->api_key . $current_ip ) ) {

                if( Cookie::get( 'apbct_anticrawler_passed' ) === '1' ){
                    if( ! headers_sent() )
                        \Cleantalk\Common\Helper::apbct_cookie__set( 'apbct_anticrawler_passed', '0', time() - 86400, '/', null, false, true, 'Lax' );
                }

                $results[] = array( 'ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_ANTICRAWLER', );

                return $results;

            }
        }

        // Common check
		foreach( $this->ip_array as $ip_origin => $current_ip ){

			$result = $this->db->fetch(
				"SELECT ip"
				. ' FROM `' . $this->db__table__ac_logs . '`'
				. " WHERE ip = '$current_ip'"
                . " AND ua = '$this->ua'"
				. " LIMIT 1;"
			);
			
			if( ! empty( $result ) && isset( $result['ip'] ) ){
				
				if( Cookie::get('apbct_antibot') !== md5( $this->api_key . $current_ip ) ){
					
					$results[] = array( 'ip' => $current_ip, 'is_personal' => false, 'status' => 'DENY_ANTICRAWLER', );
					
				}else{
					
					if( Cookie::get( 'apbct_anticrawler_passed' ) === '1' ){
						
						if( ! headers_sent() )
							\Cleantalk\Common\Helper::apbct_cookie__set( 'apbct_anticrawler_passed', '0', time() - 86400, '/', null, false, true, 'Lax' );
						
						$results[] = array( 'ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_ANTICRAWLER', );
						
						return $results;
					}
				}
				
			}else{

                if( ! Cookie::get('apbct_antibot') ) {
                    $this->update_ac_log();
                }
				
				add_action( 'wp_head', array( '\Cleantalk\ApbctWP\Firewall\AntiCrawler', 'set_cookie' ) );
				global $apbct_anticrawler_ip;
				$apbct_anticrawler_ip = $current_ip;
				
			}
		}
		
		return $results;
		
	}
	
	private function update_ac_log() {
		
		$interval_time = Helper::time__get_interval_start( $this->store_interval );
		
		// @todo Rename ip column to sign. Use IP + UserAgent for it.
		
		foreach( $this->ip_array as $ip_origin => $current_ip ){
			$id = md5( $current_ip . $this->ua. $interval_time );
			$this->db->execute(
				"INSERT INTO " . $this->db__table__ac_logs . " SET
					id = '$id',
					ip = '$current_ip',
					ua = '$this->ua',
					entries = 1,
					interval_start = $interval_time
				ON DUPLICATE KEY UPDATE
					ip = ip,
					ua = '$this->ua',
					entries = entries + 1,
					interval_start = $interval_time;"
			);
		}
		
	}
	
	
	public static function set_cookie(){
		global $apbct, $apbct_anticrawler_ip;
		echo '<script>document.cookie = "apbct_antibot=' . md5( $apbct->api_key . $apbct_anticrawler_ip ) . '; path=/; expires=0; samesite=lax";</script>';
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
				blocked_entries = 1,
				entries_timestamp = '" . intval( $time ) . "'
			ON DUPLICATE KEY
			UPDATE
				status = '$status',
				all_entries = all_entries + 1,
				blocked_entries = blocked_entries" . ( strpos( $status, 'DENY' ) !== false ? ' + 1' : '' ) . ",
				entries_timestamp = '" . intval( $time ) . "'";
		
		$this->db->execute( $query );
	}
	
	public function _die( $result ){
		
		// File exists?
		if(file_exists(CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page_anticrawler.html")){
			
			$sfw_die_page = file_get_contents(CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page_anticrawler.html");
			
			// Translation
			$replaces = array(
				'{SFW_DIE_NOTICE_IP}'              => __('Anti-Crawler Protection is activated for your IP ', 'cleantalk-spam-protect'),
				'{SFW_DIE_MAKE_SURE_JS_ENABLED}'   => __( 'To continue working with web site, please make sure that you have enabled JavaScript.', 'cleantalk-spam-protect' ),
				'{SFW_DIE_YOU_WILL_BE_REDIRECTED}' => sprintf( __( 'You will be automatically redirected to the requested page after %d seconds.', 'cleantalk-spam-protect' ), 30 ) . '<br>' . __( 'Don\'t close this page. Please, wait for 30 seconds to pass to the page.', 'cleantalk-spam-protect' ),
				'{CLEANTALK_TITLE}'                => __( 'Antispam by CleanTalk', 'cleantalk-spam-protect' ),
				'{REMOTE_ADDRESS}'                 => $result['ip'],
				'{SERVICE_ID}'                     => $this->apbct->data['service_id'],
				'{HOST}'                           => Server::get( 'HTTP_HOST' ),
				'{COOKIE_ANTICRAWLER}'             => md5( $this->api_key . $result['ip'] ),
				'{COOKIE_ANTICRAWLER_PASSED}'      => '1',
				'{GENERATED}'                      => '<p>The page was generated at&nbsp;' . date( 'D, d M Y H:i:s' ) . "</p>",
			);
			
			foreach( $replaces as $place_holder => $replace ){
				$sfw_die_page = str_replace( $place_holder, $replace, $sfw_die_page );
			}
			
			wp_die($sfw_die_page, "Blacklisted", Array('response'=>403));
			
		}else{
			wp_die("IP BLACKLISTED. Blocked by AntiCrawler " . $result['ip'], "Blacklisted", Array('response'=>403));
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
}