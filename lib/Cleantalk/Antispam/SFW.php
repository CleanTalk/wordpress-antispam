<?php

namespace Cleantalk\Antispam;

/**
 * CleanTalk SpamFireWall base class.
 * Compatible with any CMS.
 *
 * @depends       Cleantalk\Antispam\Helper class
 * @depends       Cleantalk\Antispam\API class
 * @depends       Cleantalk\Antispam\DB class
 *
 * @version       3.3
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see           https://github.com/CleanTalk/php-antispam
 */
class SFW
{
	public $ip = 0;
	
	public $ip_array = Array();
	
	public $results = array();
	public $blocked_ip = '';
	public $result = false;
	public $pass = true;
	
	public $test = false;
	
	/**
	 * @var array of arrays array(origin => array(
		'ip'      => '192.168.0.1',
		'network' => '192.168.0.0',
		'mask'    => '24',
	    'status'  => -1 (blocked) | 1 (passed)
		)
	 */
	public $all_ips = array();
	
	/**
	 * @var array of arrays array(origin => array(
		'ip'      => '192.168.0.1',
		)
	 */
	public $passed_ips = array();
	
	/**
	 * @var array of arrays array(origin => array(
		'ip'      => '192.168.0.1',
		'network' => '192.168.0.0',
		'mask'    => '24',
		)
	 */
	public $blocked_ips = array();

	// Database
	protected $db;
	protected $data_table;
	protected $log_table;
	
	//Debug
	public $debug;
	public $debug_data = '';
	
	/**
	 * CleantalkSFW_Base constructor.
	 * Creates Database driver instance.
	 */
	public function __construct()
	{
		if(empty($this->db)){
			// Creating database object. Depends on current CMS.
			$this->db = DB::getInstance();
			
			// Use default tables if not specified
			$this->data_table = defined('CLEANTALK_TBL_FIREWALL_DATA') ? CLEANTALK_TBL_FIREWALL_DATA : $this->db->prefix . 'cleantalk_sfw';
			$this->log_table  = defined('CLEANTALK_TBL_FIREWALL_LOG')  ? CLEANTALK_TBL_FIREWALL_LOG  : $this->db->prefix . 'cleantalk_sfw_logs';
		}
		
		$this->debug = isset($_GET['debug']) && intval($_GET['debug']) === 1 ? true : false;
	}
	
	/**
	 * Getting arrays of IP (REMOTE_ADDR, X-Forwarded-For, X-Real-Ip, Cf_Connecting_Ip)
	 *
	 * @param array $ips_input type of IP you want to receive
	 * @param bool  $v4_only
	 *
	 * @return array|mixed|null
	 */
	public function ip__get($ips_input = array('real', 'remote_addr', 'x_forwarded_for', 'x_real_ip', 'cloud_flare'), $v4_only = true){
		
		$result = Helper::ip__get($ips_input, $v4_only);
		
		$result = !empty($result) ? array('real' => $result) : array();
		
		if(isset($_GET['sfw_test_ip'])){
			if(Helper::ip__validate($_GET['sfw_test_ip']) !== false){
				$result['sfw_test'] = $_GET['sfw_test_ip'];
				$this->test = true;
			}
		}
		
		return $result;
		
	}
	
	/**
	 * Checks IP via Database
	 */
	public function ip_check()
	{
		foreach($this->ip_array as $origin => $current_ip){

			$current_ip_v4 = sprintf("%u", ip2long($current_ip));
			for ( $needles = array(), $m = 6; $m <= 32; $m ++ ) {
				$mask      = sprintf( "%u", ip2long( long2ip( - 1 << ( 32 - (int) $m ) ) ) );
				$needles[] = bindec( decbin( $mask ) & decbin( $current_ip_v4 ) );
			}
			$needles = array_unique( $needles );

			$query = "SELECT
				network, mask, status
				FROM " . $this->data_table . "
				WHERE network IN (". implode( ',', $needles ) .") 
				AND	network = " . $current_ip_v4 . " & mask
				ORDER BY status DESC LIMIT 1;";
			$this->db->set_query($query)->fetch();

			if( ! empty( $this->db->result ) ){

                if ( 1 == $this->db->result['status'] ) {
                    // It is the White Listed network - will be passed.
                    $this->passed_ips[$origin] = array(
                        'ip'     => $current_ip,
                    );
                    $this->all_ips[$origin] = array(
                        'ip'     => $current_ip,
                        'status' => 1,
                    );
                    break;
                } else {
                    $this->pass = false;
                    $this->blocked_ips[$origin] = array(
                        'ip'      => $current_ip,
                        'network' => long2ip($this->db->result['network']),
                        'mask'    => Helper::ip__mask__long_to_number($this->db->result['mask']),
                    );
                    $this->all_ips[$origin] = array(
                        'ip'      => $current_ip,
                        'network' => long2ip($this->db->result['network']),
                        'mask'    => Helper::ip__mask__long_to_number($this->db->result['mask']),
                        'status'  => -1,
                    );
                }

			}else{
				$this->passed_ips[$origin] = array(
					'ip'     => $current_ip,
				);
				$this->all_ips[$origin] = array(
					'ip'     => $current_ip,
					'status' => 1,
				);
			}		
		}
	}
	
	/**
	 * Add entry to SFW log.
	 * Writes to database.
	 *
	 * @param string $ip
	 * @param string $result "blocked" or "passed"
	 */
	public function logs__update($ip, $result){
		
		if($ip === NULL || $result === NULL){
			return;
		}
		
		$blocked = ($result == 'blocked' ? ' + 1' : '');
		$time = time();

		$query = "INSERT INTO ".$this->log_table."
		SET 
			ip = '$ip',
			all_entries = 1,
			blocked_entries = 1,
			entries_timestamp = '".intval($time)."'
		ON DUPLICATE KEY 
		UPDATE 
			all_entries = all_entries + 1,
			blocked_entries = blocked_entries".strval($blocked).",
			entries_timestamp = '".intval($time)."'";

		$this->db->execute($query);
	}
	
	/**
	 * Sends and wipe SFW log
	 *
	 * @param string $ct_key API key
	 *
	 * @return array|bool array('error' => STRING)
	 */
	public function logs__send($ct_key){
		
		//Getting logs
		$query = "SELECT * FROM ".$this->log_table.";";
		$this->db->fetch_all($query);
		
		if(count($this->db->result)){
			
			//Compile logs
			$data = array();
			foreach($this->db->result as $key => $value){
				$data[] = array(trim($value['ip']), $value['all_entries'], $value['all_entries']-$value['blocked_entries'], $value['entries_timestamp']);
			}
			unset($key, $value);
			
			//Sending the request
			$result = API::method__sfw_logs($ct_key, $data);
			//Checking answer and deleting all lines from the table
			if(empty($result['error'])){
				if($result['rows'] == count($data)){
					$this->db->execute("TRUNCATE TABLE ".$this->log_table.";");
					return $result;
				}
				return array('error' => 'SENT_AND_RECEIVED_LOGS_COUNT_DOESNT_MACH');
			}else{
				return $result;
			}
				
		} else {
		    return $result = array( 'rows' => 0 );
        }
	}
	
	/**
	 * Updates SFW local base
	 *
	 * @param string      $ct_key    API key
	 * @param null|string $file_url  File URL with SFW data.
	 * @param bool        $immediate Requires immmediate update. Without remote call
	 *
	 * @return array|bool array('error' => STRING)
	 */
	public function sfw_update($ct_key, $file_url = null, $immediate = false){

		// Getting remote file name
		if(!$file_url){

			$result = API::method__get_2s_blacklists_db($ct_key, 'multifiles', '2_0');

			if(empty($result['error'])){
			
				if( !empty($result['file_url']) ){

					if(Helper::http__request($result['file_url'], array(), 'get_code') === 200) {

						if(ini_get('allow_url_fopen')) {

							$pattenrs = array();
							$pattenrs[] = 'get';

							if(!$immediate) $pattenrs[] = 'async';		

							// Clear SFW table
							$this->db->execute("TRUNCATE TABLE {$this->data_table};");
							$this->db->set_query("SELECT COUNT(network) as cnt FROM {$this->data_table};")->fetch(); // Check if it is clear
							if($this->db->result['cnt'] != 0){
								$this->db->execute("DELETE FROM {$this->data_table};"); // Truncate table
								$this->db->set_query("SELECT COUNT(network) as cnt FROM {$this->data_table};")->fetch(); // Check if it is clear
								if($this->db->result['cnt'] != 0){
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
									$pattenrs
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
	
								$query = "INSERT INTO ".$this->data_table." VALUES %s";
	
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
									$this->db->execute($query);
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
	
	/**
	 * Shows DIE page.
	 * Stops script executing.
	 *
	 * @param string $api_key
	 * @param string $cookie_prefix
	 * @param string $cookie_domain
	 * @param bool   $test
	 */
	public function sfw_die($api_key, $cookie_prefix = '', $cookie_domain = '', $test = false)
	{	
		die("IP {$this->blocked_ip} BLACKLISTED");
	}
}
