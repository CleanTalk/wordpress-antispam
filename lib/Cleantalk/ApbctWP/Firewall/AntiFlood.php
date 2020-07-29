<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\Common\Helper as Helper;
use Cleantalk\Variables\Cookie;
use Cleantalk\Variables\Server;

class AntiFlood extends \Cleantalk\Common\Firewall\FirewallModule{
	
	public $module_name = 'ANTIFLOOD';
	
	private $db__table__ac_logs;

	private $api_key = '';
	private $view_limit = 10;
	private $apbct = array();
	private $store_interval  = 60;
	private $block_period    = 30;
	private $chance_to_clean = 200;
	
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
				
				$results[] = array( 'ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_ANTIFLOOD', );
				
				return $results;
			}
			
			
			// @todo Rename ip column to sign. Use IP + UserAgent for it.
			
			$result = $this->db->fetch_all(
				"SELECT SUM(entries) as total_count"
				. ' FROM `' . $this->db__table__ac_logs . '`'
				. " WHERE ip = '$current_ip' AND interval_start > '$time';"
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
	
	private function clear_table() {
		
		if( rand( 0, 1000 ) < $this->chance_to_clean ){
			$interval_start = \Cleantalk\ApbctWP\Helper::time__get_interval_start( $this->store_interval );
			$this->db->execute(
				'DELETE
				FROM ' . $this->db__table__ac_logs . '
				WHERE interval_start < '. $interval_start .'
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
		
		$id = md5( $ip );
		$time    = time();
		
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
	
	public function _die( $result ) {
		
		parent::_die( $result );
		
		// File exists?
		if( file_exists( CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk/ApbctWP/Firewall/die_page__AntiFlood.html' ) ){
			
			$sfw_die_page = file_get_contents( CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk/ApbctWP/Firewall/die_page__AntiFlood.html' );
			
			// Translation
			$replaces = array(
				'{SFW_DIE_NOTICE_IP}'              => __( 'Anti-Flood is activated for your IP', 'cleantalk-spam-protect' ),
				'{SFW_DIE_MAKE_SURE_JS_ENABLED}'   => __( 'To continue working with web site, please make sure that you have enabled JavaScript.', 'cleantalk-spam-protect' ),
				'{SFW_DIE_YOU_WILL_BE_REDIRECTED}' => sprintf( __( 'You will be automatically redirected to the requested page after %d seconds.', 'cleantalk-spam-protect' ), 30 ),
				'{CLEANTALK_TITLE}'                => __( 'Antispam by CleanTalk', 'cleantalk-spam-protect' ),
				'{REMOTE_ADDRESS}'                 => $result['ip'],
				'{REQUEST_URI}'                    => Server::get( 'REQUEST_URI' ),
				'{SERVICE_ID}'                     => $this->apbct->data['service_id'],
				'{HOST}'                           => Server::get( 'HTTP_HOST' ),
				'{GENERATED}'                      => '<p>The page was generated at&nbsp;' . date( 'D, d M Y H:i:s' ) . "</p>",
				'{COOKIE_ANTIFLOOD_PASSED}'      => md5( $this->api_key . $result['ip'] ),
			);
			
			foreach( $replaces as $place_holder => $replace ){
				$sfw_die_page = str_replace( $place_holder, $replace, $sfw_die_page );
			}
			
			wp_die( $sfw_die_page, 'Blacklisted', array( 'response' => 403 ) );
			
		} else{
			wp_die( 'IP BLACKLISTED', 'Blacklisted', array( 'response' => 403 ) );
		}
		
	}
}