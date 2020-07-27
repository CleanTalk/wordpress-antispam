<?php


namespace Cleantalk\ApbctWP\Firewall;


use Cleantalk\Variables\Cookie;
use Cleantalk\Variables\Server;

class AntiBot extends \Cleantalk\Common\Firewall\FirewallModule{
	
	public $module_name = 'ANTIBOT';
	
	private $db__table__ac_logs;
	private $api_key = '';
	private $apbct = false;
	
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
		
		foreach( $params as $param_name => $param ){
			$this->$param_name = isset( $this->$param_name ) ? $param : false;
		}
		
	}
	
	/**
	 * Use this method to execute main logic of the module.
	 *
	 * @return array  Array of the check results
	 */
	public function check() {
		
		$results = array();
		
		foreach( $this->ip_array as $ip_origin => $current_ip ){
			
			// @todo Rename ip column to sign. Use IP + UserAgent for it.
			
			$result = $this->db->fetch(
				"SELECT ip"
				. ' FROM `' . $this->db__table__ac_logs . '`'
				. " WHERE ip = '$current_ip'"
				. " LIMIT 1;"
			);
			
			if( ! empty( $result ) && isset( $result['ip'] ) ){
				if( Cookie::get('apbct_antibot') !== md5( $this->api_key . $current_ip ) ){
					$results[] = array( 'ip' => $current_ip, 'is_personal' => false, 'status' => 'DENY_ANTIBOT', );
				}
			}else{
				add_action( 'wp_head', array( '\Cleantalk\ApbctWP\Firewall\AntiBot', 'set_cookie' ) );
				global $apbct_antibot_ip;
				$apbct_antibot_ip = $current_ip;
			}
		}
		
		return $results;
		
	}
	
	public static function set_cookie(){
		global $apbct, $apbct_antibot_ip;
		echo '<script>document.cookie = "apbct_antibot=' . md5( $apbct->api_key . $apbct_antibot_ip ) . '; path=/; expires=0; samesite=lax";</script>';
	}
	
	/**
	 * Add entry to SFW log.
	 * Writes to database.
	 *
	 * @param string $ip
	 * @param $status
	 */
	public function update_log( $ip, $status ) {
		
		$blocked = ( strpos( $status, 'DENY' ) !== false ? ' + 1' : '' );
		
		if( $blocked ){
			
			$id   = md5( $ip . $status );
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
					all_entries = all_entries + 1,
					blocked_entries = blocked_entries" . strval( $blocked ) . ",
					entries_timestamp = '" . intval( $time ) . "'";
			
			$this->db->execute( $query );
		}
	}
	
	public function _die( $result ){
		
		// Headers
		if(headers_sent() === false){
			header('Expires: '.date(DATE_RFC822, mktime(0, 0, 0, 1, 1, 1971)));
			header('Cache-Control: no-store, no-cache, must-revalidate');
			header('Cache-Control: post-check=0, pre-check=0', FALSE);
			header('Pragma: no-cache');
			header("HTTP/1.0 403 Forbidden");
		}
		
		// File exists?
		if(file_exists(CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page__antibot.html")){
			
			$sfw_die_page = file_get_contents(CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page__antibot.html");
			
			// Translation
			$request_uri  = Server::get( 'REQUEST_URI' );
			$sfw_die_page = str_replace('{SFW_DIE_NOTICE_IP}',              __('Anti-Crawler Protection is activated for your IP ', 'cleantalk-spam-protect'), $sfw_die_page);
			$sfw_die_page = str_replace('{SFW_DIE_MAKE_SURE_JS_ENABLED}',   __('To continue working with web site, please make sure that you have enabled JavaScript.', 'cleantalk-spam-protect'), $sfw_die_page);
			$sfw_die_page = str_replace('{SFW_DIE_YOU_WILL_BE_REDIRECTED}', sprintf(__('You will be automatically redirected to the requested page after %d seconds.', 'cleantalk-spam-protect'), 30), $sfw_die_page);
			$sfw_die_page = str_replace('{CLEANTALK_TITLE}',                __('Antispam by CleanTalk', 'cleantalk-spam-protect'), $sfw_die_page);
			
			$sfw_die_page = str_replace('{REMOTE_ADDRESS}', $result['ip'], $sfw_die_page);
			
			// Service info
			$sfw_die_page = str_replace('{SERVICE_ID}',     $this->apbct->data['service_id'],      $sfw_die_page);
			$sfw_die_page = str_replace('{HOST}',           Server::get( 'HTTP_HOST' ),           $sfw_die_page);
			
			$sfw_die_page = str_replace('{SFW_COOKIE}', md5( $this->api_key . $result['ip'] ), $sfw_die_page );
			
			$sfw_die_page = str_replace('{GENERATED}', "<p>The page was generated at&nbsp;".date("D, d M Y H:i:s")."</p>",$sfw_die_page);
			
			wp_die($sfw_die_page, "Blacklisted", Array('response'=>403));
			
		}else{
			wp_die("IP BLACKLISTED", "Blacklisted", Array('response'=>403));
		}
		
	}
}