<?php

/*
 * CleanTalk SpamFireWall base class
 * Compatible only with Wordpress.
 * @depends on CleantalkHelper class
 * @depends on CleantalkAPI class
 * @depends on CleantalkDB class
 * Version 3.0-base
 * author Cleantalk team (welcome@cleantalk.org)
 * copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * see https://github.com/CleanTalk/php-antispam
*/

class CleantalkSFW_Base
{
	public $ip = 0;
	public $ip_str = '';
	public $ip_array = Array();
	public $ip_str_array = Array();
	public $blocked_ip = '';
	public $passed_ip = '';
	public $result = false;
	
	public $is_test = false;
	
	protected $data_table;
	protected $log_table;
	
	public $debug;
	public $debug_data = '';
	public $debug_networks = array();
	
	/**
	* Creates connection to database
	* 
	* @param array $params
	*   array((string)'hostname', (string)'db_name', (string)'charset', (array)PDO options)
	* @param string $username
	* @param string $password
	*
	* @return void
	*/
	public function __construct()
	{
		// Creating database object
		$this->db = new ClentalkDB();
		
		// Use default tables if not specified
		$this->data_table = $this->db->table_prefix . 'cleantalk_sfw';
		$this->log_table  = $this->db->table_prefix . 'cleantalk_sfw_logs';
	}
	
	/*
	*	Getting arrays of IP (REMOTE_ADDR, X-Forwarded-For, X-Real-Ip, Cf_Connecting_Ip)
	*	reutrns array('remote_addr' => 'val', ['x_forwarded_for' => 'val', ['x_real_ip' => 'val', ['cloud_flare' => 'val']]])
	*/
	public function ip__get($ips_input = array('real', 'remote_addr', 'x_forwarded_for', 'x_real_ip', 'cloud_flare'), $v4_only = true){
		
		$result = (array)CleantalkHelper::ip__get($ips_input, $v4_only);
		
		$result = !empty($result) ? $result : array();
		
		if(isset($_GET['sfw_test_ip'])){
			if(CleantalkHelper::ip__validate($_GET['sfw_test_ip']) !== false){
				$result['sfw_test'] = $_GET['sfw_test_ip'];
				$this->is_test = true;
			}
		}
		
		return $result;
		
	}
	
	/*
	*	Checks IP via Database
	*/
	public function ip_check(){
		
		foreach($this->ip_array as $current_ip){
		
			$query = "SELECT 
				COUNT(network) AS cnt, network, mask
				FROM ".$this->data_table."
				WHERE network = ".sprintf("%u", ip2long($current_ip))." & mask;";
			$this->db->query($query)->fetch();
			if($this->db->result['cnt']){
				$this->result = true;
				$this->blocked_ip = $current_ip;
				$this->debug_networks[] = $this->db->result['network'].'/'.$this->db->result['mask'];
			}else{
				$this->passed_ip = $current_ip;
			}
			
		}
	}
		
	/*
	*	Add entry to SFW log
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

		$this->db->query($query, true);
	}
	
	/*
	* Sends and wipe SFW log
	* 
	* returns mixed true || array('error' => true, 'error_string' => STRING)
	*/
	public function logs__send($ct_key){
		
		//Getting logs
		$query = "SELECT * FROM ".$this->log_table.";";
		$this->db->query($query)->fetch_all();
		
		if(count($this->db->result)){
			
			//Compile logs
			$data = array();
			foreach($this->db->result as $key => $value){
				$data[] = array(trim($value['ip']), $value['all_entries'], $value['all_entries']-$value['blocked_entries'], $value['entries_timestamp']);
			}
			unset($key, $value);
			
			//Sending the request
			$result = CleantalkAPI::method__sfw_logs($ct_key, $data);
			
			//Checking answer and deleting all lines from the table
			if(empty($result['error'])){
				if($result['rows'] == count($data)){
					$this->db->query("DELETE FROM ".$this->log_table.";", true);
					return true;
				}
			}else{
				return $result;
			}
				
		}else{
			return array('error' => true, 'error_string' => 'NO_LOGS_TO_SEND');
		}
	}
	
	/*
	* Updates SFW local base
	* 
	* return mixed true || array('error' => true, 'error_string' => STRING)
	*/
	public function sfw_update($ct_key, $file_url = null, $immediate = false){
		
		// Getting remote file name
		if(!$file_url){
			
			$result = CleantalkAPI::method__get_2s_blacklists_db($ct_key, 'file');
						
			if(empty($result['error'])){
			
				if( !empty($result['file_url']) ){
					
					$file_url = $result['file_url'];
					
					$pattenrs = array();
					$pattenrs[] = 'get';
					if(!$immediate) $pattenrs[] = 'dont_wait_for_answer';
					
					return CleantalkHelper::http__request(
						get_option('siteurl'), 
						array(
							'spbc_remote_call_token'  => md5($ct_key),
							'spbc_remote_call_action' => 'sfw_update',
							'plugin_name'             => 'apbct',
							'file_url'                => $result['file_url'],
						),
						$pattenrs
					);
					
				}else
					return array('error' => true, 'error_string' => 'BAD_RESPONSE');
			}else
				return $result;
		}else{
			
			sleep(3);
			
			if(CleantalkHelper::http__request($file_url, array(), 'get_code') === 200){ // Check if it's there
				
				$this->db->query("DELETE FROM ".$this->data_table.";", true);

				$gf = gzopen($file_url, 'rb');
				
				if($gf){
					
					for($count_result = 0; !gzeof($gf); ){
						
						
						$query = "INSERT INTO ".$this->data_table." VALUES %s";
						
						for($i=0, $values = array(); APBCT_WRITE_LIMIT !== $i && !gzeof($gf); $i++, $count_result++){
							
							$entry = trim(gzgets($gf, 1024));
							
							if(empty($entry)) continue;
							
							$entry = explode(',', $entry);
							
							// Cast result to int
							$ip   = preg_replace('/[^\d]*/', '', $entry[0]);
							$mask = preg_replace('/[^\d]*/', '', $entry[1]);
							
							if(!$ip || !$mask) continue;
							
							$values[] = '('. $ip .','. $mask .')';
							
						}
						
						$query = sprintf($query, implode(',', $values).';');
						$this->db->query($query, true);
												
					}
					
					gzclose($gf);
					return $count_result;
					
				}else
					return array('error' => true, 'error_string' => 'ERROR_OPEN_GZ_FILE');
			}else
				return array('error' => true, 'error_string' => 'NO_REMOTE_FILE_FOUND');
		}			
	}
	
	/*
	* Shows DIE page
	* 
	* Stops script executing
	*/	
	public function sfw_die($api_key, $cookie_prefix = '', $cookie_domain = '')
	{	
		die("IP {$this->blocked_ip} BLACKLISTED");
	}
}
