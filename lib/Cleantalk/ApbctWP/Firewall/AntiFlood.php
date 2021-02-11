<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\Common\Helper as Helper;
use Cleantalk\Variables\Cookie;
use Cleantalk\Variables\Server;

class AntiFlood extends \Cleantalk\Common\Firewall\FirewallModule{
	
	public $module_name = 'ANTIFLOOD';
	
	private $db__table__ac_logs;

	private $api_key = '';
	private $view_limit = 20;
	private $apbct = array();
	private $store_interval  = 60;
	private $block_period    = 30;
	private $chance_to_clean = 20;

    public $isExcluded = false;
	
	/**
	 * AntiCrawler constructor.
	 *
	 * @param $log_table
	 * @param $ac_logs_table
	 * @param array $params
	 */
	public function __construct( $log_table, $ac_logs_table, $params = array() ) {
		
		$this->db__table__logs    = $log_table ?: null;
		$this->db__table__ac_logs = $ac_logs_table ?: null;
		
		foreach( $params as $param_name => $param ){
			$this->$param_name = isset( $this->$param_name ) ? $param : false;
		}

        $this->isExcluded = $this->check_exclusions();
	}
	
	/**
	 * Use this method to execute main logic of the module.
	 * @return array
	 */
	public function check() {
		
		$results = array();
		
		$this->clear_table();
		
		$time = time() - $this->store_interval;
		
		foreach( $this->ip_array as $ip_origin => $current_ip ){
			
			// Passed
			if( Cookie::get( 'apbct_antiflood_passed' ) === md5( $current_ip . $this->api_key ) ){
				
				if( ! headers_sent() ){
					\Cleantalk\Common\Helper::apbct_cookie__set( 'apbct_antiflood_passed', '0', time() - 86400, '/', null, false, true, 'Lax' );
				}

                // Do logging an one passed request
                $this->update_log( $current_ip, 'PASS_ANTIFLOOD' );

				$results[] = array( 'ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_ANTIFLOOD', );
				
				return $results;
			}
			
			
			// @todo Rename ip column to sign. Use IP + UserAgent for it.
			
			$result = $this->db->fetch_all(
				"SELECT SUM(entries) as total_count"
				. ' FROM `' . $this->db__table__ac_logs . '`'
				. " WHERE ip = '$current_ip' AND interval_start > '$time' AND " . rand( 1, 100000 ) . ";"
			);
			
			if( ! empty( $result ) && isset( $result[0]['total_count'] ) && $result[0]['total_count'] >= $this->view_limit ){
				$results[] = array( 'ip' => $current_ip, 'is_personal' => false, 'status' => 'DENY_ANTIFLOOD', );
			}
		}
		
		if( ! empty( $results ) ){
			// Do block page
			return $results;
		} else{
			// Do logging entries
			$this->update_ac_log();
		}
		
		return $results;
		
	}
	
	private function update_ac_log() {
		
		$interval_time = Helper::time__get_interval_start( $this->store_interval );
		
		// @todo Rename ip column to sign. Use IP + UserAgent for it.
		
		foreach( $this->ip_array as $ip_origin => $current_ip ){
			$id = md5( $current_ip . $interval_time );
			$this->db->execute(
				"INSERT INTO " . $this->db__table__ac_logs . " SET
					id = '$id',
					ip = '$current_ip',
					entries = 1,
					interval_start = $interval_time
				ON DUPLICATE KEY UPDATE
					ip = ip,
					entries = entries + 1,
					interval_start = $interval_time;"
			);
		}
		
	}
	
	public function clear_table() {
		
		if( rand( 0, 100 ) < $this->chance_to_clean ){
			$interval_start = \Cleantalk\ApbctWP\Helper::time__get_interval_start( $this->store_interval );
			$this->db->execute(
				'DELETE
				FROM ' . $this->db__table__ac_logs . '
				WHERE interval_start < '. $interval_start .' 
				AND ua = "" 
				LIMIT 100000;'
			);
		}
	}
	
	/**
	 * Add entry to SFW log.
	 * Writes to database.
	 *
	 * @param string $ip
	 * @param $status
	 */
	public function update_log( $ip, $status ) {

		$id = md5( $ip . $this->module_name );
		$time    = time();
		
		$query = "INSERT INTO " . $this->db__table__logs . "
		SET
			id = '$id',
			ip = '$ip',
			status = '$status',
			all_entries = 1,
			blocked_entries = " . ( strpos( $status, 'DENY' ) !== false ? 1 : 0 ) . ",
			entries_timestamp = '" . intval( $time ) . "',
			ua_name = '" . Server::get('HTTP_USER_AGENT') . "'
		ON DUPLICATE KEY
		UPDATE
			status = '$status',
			all_entries = all_entries + 1,
			blocked_entries = blocked_entries" . ( strpos( $status, 'DENY' ) !== false ? ' + 1' : '' ) . ",
			entries_timestamp = '" . intval( $time ) . "',
			ua_name = '" . Server::get('HTTP_USER_AGENT') . "'";
		
		$this->db->execute( $query );
	}
	
	public function _die( $result ) {
		
		parent::_die( $result );

		global $wpdb;

		// File exists?
		if( file_exists( CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk/ApbctWP/Firewall/die_page_antiflood.html' ) ){
			
			$sfw_die_page = file_get_contents( CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk/ApbctWP/Firewall/die_page_antiflood.html' );

            $net_count = $wpdb->get_var('SELECT COUNT(*) FROM ' . APBCT_TBL_FIREWALL_DATA );
			
			// Translation
			$replaces = array(
				'{SFW_DIE_NOTICE_IP}'              => __( 'Anti-Flood is activated for your IP', 'cleantalk-spam-protect' ),
				'{SFW_DIE_MAKE_SURE_JS_ENABLED}'   => __( 'To continue working with the web site, please make sure that you have enabled JavaScript.', 'cleantalk-spam-protect' ),
				'{SFW_DIE_YOU_WILL_BE_REDIRECTED}' => sprintf( __( 'You will be automatically redirected to the requested page after %d seconds.', 'cleantalk-spam-protect' ), 30 ),
				'{CLEANTALK_TITLE}'                => __( 'Antispam by CleanTalk', 'cleantalk-spam-protect' ),
				'{REMOTE_ADDRESS}'                 => $result['ip'],
				'{REQUEST_URI}'                    => Server::get( 'REQUEST_URI' ),
				'{SERVICE_ID}'                     => $this->apbct->data['service_id'] . ', ' . $net_count,
				'{HOST}'                           => get_home_url() . ', ' . APBCT_VERSION,
				'{GENERATED}'                      => '<p>The page was generated at&nbsp;' . date( 'D, d M Y H:i:s' ) . "</p>",
				'{COOKIE_ANTIFLOOD_PASSED}'      => md5( $this->api_key . $result['ip'] ),
			);
			
			foreach( $replaces as $place_holder => $replace ){
				$sfw_die_page = str_replace( $place_holder, $replace, $sfw_die_page );
			}
			
			wp_die( $sfw_die_page, 'Blacklisted', array( 'response' => 403 ) );
			
		} else{
			wp_die( "IP BLACKLISTED. Blocked by AntiFlood " . $result['ip'], 'Blacklisted', array( 'response' => 403 ) );
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