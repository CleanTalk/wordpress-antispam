<?php
class CleanTalkSFW
{
	public $ip = 0;
	public $ip_str = '';
	public $ip_array = Array();
	public $ip_str_array = Array();
	public $blocked_ip = '';
	public $passed_ip = '';
	public $result = false;
	
	public function cleantalk_get_real_ip()
	{
		$result=Array();
		if ( function_exists( 'apache_request_headers' ) )
			$headers = apache_request_headers();
		else
			$headers = $_SERVER;

		if ( array_key_exists( 'X-Forwarded-For', $headers ) ){
			$the_ip = explode(",", trim($headers['X-Forwarded-For']));
			$the_ip = trim($the_ip[0]);
			$result[] = $the_ip;
			$this->ip_str_array[]=$the_ip;
			$this->ip_array[]=sprintf("%u", ip2long($the_ip));
		}
		
		if ( array_key_exists( 'HTTP_X_FORWARDED_FOR', $headers )){
			$the_ip = explode(",", trim($headers['HTTP_X_FORWARDED_FOR']));
			$the_ip = trim($the_ip[0]);
			$result[] = $the_ip;
			$this->ip_str_array[]=$the_ip;
			$this->ip_array[]=sprintf("%u", ip2long($the_ip));
		}
		
		$the_ip = filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
		$result[] = $the_ip;
		$this->ip_str_array[]=$the_ip;
		$this->ip_array[]=sprintf("%u", ip2long($the_ip));

		if(isset($_GET['sfw_test_ip'])){
			$the_ip = $_GET['sfw_test_ip'];
			$result[] = $the_ip;
			$this->ip_str_array[]=$the_ip;
			$this->ip_array[]=sprintf("%u", ip2long($the_ip));
		}
		
		return $result;
	}
	
	public function check_ip()
	{		
		global $wpdb,$ct_options, $ct_data;
			
		for($i=0;$i<sizeof($this->ip_array);$i++){
			$r = $wpdb->get_results("select count(network) as cnt from `".$wpdb->base_prefix."cleantalk_sfw` where network = ".$this->ip_array[$i]." & mask;", ARRAY_A);
			if($r[0]['cnt']){
				$this->result=true;
				$this->blocked_ip=$this->ip_str_array[$i];
			}else{
				$this->passed_ip = $this->ip_str_array[$i];
			}
		}
		if($this->passed_ip!=''){
			@setcookie ('ct_sfw_pass_key', md5($this->passed_ip.$ct_options['apikey']), 0, "/");
		}
	}
	
	public function sfw_die()
	{
		global $ct_options, $ct_data;
		$sfw_die_page=file_get_contents(dirname(__FILE__)."/sfw_die_page.html");
		$sfw_die_page=str_replace("{REMOTE_ADDRESS}",$this->blocked_ip,$sfw_die_page);
		$sfw_die_page=str_replace("{REQUEST_URI}",$_SERVER['REQUEST_URI'],$sfw_die_page);
		$sfw_die_page=str_replace("{SFW_COOKIE}",md5($this->blocked_ip.$ct_options['apikey']),$sfw_die_page);
		@header('Cache-Control: no-cache');
		@header('Expires: 0');
		@header('HTTP/1.0 403 Forbidden');
		wp_die( $sfw_die_page, "Blacklisted", Array('response'=>403) );
	}
	
	static public function sfw_update($ct_key){
			
		global $wpdb;
		
		if(!function_exists('sendRawRequest'))
			require_once(plugin_dir_path(__FILE__) . 'cleantalk.class.php');
		
		$data = Array('auth_key' => $ct_key, 'method_name' => '2s_blacklists_db');	
		$result=sendRawRequest('https://api.cleantalk.org/2.1',$data,false);

		$result=json_decode($result, true);

		if(isset($result['data'])){

			$wpdb->query("TRUNCATE TABLE `".$wpdb->base_prefix."cleantalk_sfw`;");
			
			$result=$result['data'];
			$query="INSERT INTO `".$wpdb->base_prefix."cleantalk_sfw` VALUES ";
			for($i=0;$i<sizeof($result);$i++){
				if($i==sizeof($result)-1){
					$query.="(".$result[$i][0].",".$result[$i][1].");";
				}else{
					$query.="(".$result[$i][0].",".$result[$i][1]."), ";
				}
			}
			$wpdb->query($query);
		}
	}
	
	//Add entries to SFW log
	static public function sfw_update_logs($ip, $result){
				
		if($ip === NULL || $result === NULL){
			error_log('SFW log update failed');
			return;
		}
				
		global $wpdb;
		
		$blocked = ($result == 'blocked' ? ' + 1' : '');
		$time = time();

		$query = "INSERT INTO `".$wpdb->base_prefix."cleantalk_sfw_logs`
		SET 
			`ip` = '$ip',
			`all` = 1,
			`blocked` = 1,
			`timestamp` = '".$time."'
		ON DUPLICATE KEY 
		UPDATE 
			`all` = `all` + 1,
			`blocked` = `blocked`".$blocked.",
			`timestamp` = '".$time."'";

		$result = $wpdb->query($query);
	}
	
	//*Send and wipe SFW log
	public static function send_logs($ct_key){
		
		global $wpdb;
		
		//Getting logs
		$result = $wpdb->get_results("SELECT * FROM `".$wpdb->base_prefix."cleantalk_sfw_logs`", ARRAY_A);
				
		if(count($result)){
			//Compile logs
			$data = array();
			
			$for_return['all'] = 0;
			$for_return['blocked'] = 0;
			
			foreach($result as $key => $value){
				//Compile log
				$data[] = array(trim($value['ip']), $value['all'], $value['all']-$value['blocked'], $value['timestamp']);
				//Compile to return;
				$for_return['all'] = $for_return['all'] + $value['all'];
				$for_return['blocked'] = $for_return['blocked'] + $value['blocked'];
			} unset($key, $value, $result);
			
			//Final compile
			$qdata = array (
				'data' => json_encode($data),
				'rows' => count($data),
				'timestamp' => time()
			);
			
			if(!function_exists('sendRawRequest'))
				require_once(plugin_dir_path(__FILE__) . 'cleantalk.class.php');
			
			//Sendings request
			$result=sendRawRequest('https://api.cleantalk.org/?method_name=sfw_logs&auth_key='.$ct_key, $qdata, false);
			
			$result = json_decode($result);
			//Checking answer and truncate table
			if(isset($result->data) && isset($result->data->rows))
				if($result->data->rows == count($data)){
					$wpdb->query("TRUNCATE TABLE `".$wpdb->base_prefix."cleantalk_sfw_logs`");
					return $for_return;
				}
				
		}else		
			return false;
	}
}
