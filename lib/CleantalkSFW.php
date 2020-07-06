<?php

/**
 * CleanTalk SpamFireWall Wordpress class
 * Compatible only with Wordpress.
 *
 * @depends       Cleantalk\Antispam\SFW
 *
 * @version       3.3
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see           https://github.com/CleanTalk/wordpress-antispam
 */
class CleantalkSFW extends Cleantalk\Antispam\SFW
{
	/**
	 * CleantalkSFW_Base constructor.
	 * Creates Database driver instance.
	 */
	public function __construct()
	{
		
		// Creating database object. Depends on current CMS.
		$this->db = CleantalkDB::getInstance();
		
		// Use default tables if not specified
		$this->data_table = defined('APBCT_TBL_FIREWALL_DATA') ? APBCT_TBL_FIREWALL_DATA : $this->db->prefix . 'cleantalk_sfw';
		$this->log_table  = defined('APBCT_TBL_FIREWALL_LOG')  ? APBCT_TBL_FIREWALL_LOG  : $this->db->prefix . 'cleantalk_sfw_logs';
		
		parent::__construct();
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
	public function sfw_die($api_key, $cookie_prefix = '', $cookie_domain = '', $test = false){
		
		global $apbct;
		
		// Statistics
		if(!empty($this->blocked_ips)){
			reset($this->blocked_ips);
			$apbct->stats['last_sfw_block']['time'] = time();
			$apbct->stats['last_sfw_block']['ip'] = $this->blocked_ips[key($this->blocked_ips)]['ip'];
			$apbct->save('stats');
		}
		
		// Headers
		if(headers_sent() === false){
			header('Expires: '.date(DATE_RFC822, mktime(0, 0, 0, 1, 1, 1971)));
			header('Cache-Control: no-store, no-cache, must-revalidate');
			header('Cache-Control: post-check=0, pre-check=0', FALSE);
			header('Pragma: no-cache');
			header("HTTP/1.0 403 Forbidden");
		}
		
		// File exists?
		if(file_exists(CLEANTALK_PLUGIN_DIR . "inc/sfw_die_page.html")){
			
			$sfw_die_page = file_get_contents(CLEANTALK_PLUGIN_DIR . "inc/sfw_die_page.html");

			// Translation
			$request_uri  = apbct_get_server_variable( 'REQUEST_URI' );
			$sfw_die_page = str_replace('{SFW_DIE_NOTICE_IP}',              __('SpamFireWall is activated for your IP ', 'cleantalk-spam-protect'), $sfw_die_page);
			$sfw_die_page = str_replace('{SFW_DIE_MAKE_SURE_JS_ENABLED}',   __('To continue working with web site, please make sure that you have enabled JavaScript.', 'cleantalk-spam-protect'), $sfw_die_page);
			$sfw_die_page = str_replace('{SFW_DIE_CLICK_TO_PASS}',          __('Please click the link below to pass the protection,', 'cleantalk-spam-protect'), $sfw_die_page);
			$sfw_die_page = str_replace('{SFW_DIE_YOU_WILL_BE_REDIRECTED}', sprintf(__('Or you will be automatically redirected to the requested page after %d seconds.', 'cleantalk-spam-protect'), 3), $sfw_die_page);
			$sfw_die_page = str_replace('{CLEANTALK_TITLE}',                __('Antispam by CleanTalk', 'cleantalk-spam-protect'), $sfw_die_page);
			$sfw_die_page = str_replace('{TEST_TITLE}',                     ($this->test ? __('This is the testing page for SpamFireWall', 'cleantalk-spam-protect') : ''), $sfw_die_page);
	
			if($this->test){
				$sfw_die_page = str_replace('{REAL_IP__HEADER}', 'Real IP:', $sfw_die_page);
				$sfw_die_page = str_replace('{TEST_IP__HEADER}', 'Test IP:', $sfw_die_page);
				$sfw_die_page = str_replace('{TEST_IP}', $this->all_ips['sfw_test']['ip'], $sfw_die_page);
				$sfw_die_page = str_replace('{REAL_IP}', $this->all_ips['real']['ip'],     $sfw_die_page);
				$sfw_die_page = str_replace('{TEST_IP_BLOCKED}', $this->all_ips['sfw_test']['status'] == 1 ? 'Passed' : 'Blocked', $sfw_die_page);
				$sfw_die_page = str_replace('{REAL_IP_BLOCKED}', $this->all_ips['real']['status'] == 1 ? 'Passed' : 'Blocked',     $sfw_die_page);
			}else{
				$sfw_die_page = str_replace('{REAL_IP__HEADER}', '', $sfw_die_page);
				$sfw_die_page = str_replace('{TEST_IP__HEADER}', '', $sfw_die_page);
				$sfw_die_page = str_replace('{TEST_IP}', '', $sfw_die_page);
				$sfw_die_page = str_replace('{REAL_IP}', '', $sfw_die_page);
				$sfw_die_page = str_replace('{TEST_IP_BLOCKED}', '', $sfw_die_page);
				$sfw_die_page = str_replace('{REAL_IP_BLOCKED}', '', $sfw_die_page);
			}
			
			$sfw_die_page = str_replace('{REMOTE_ADDRESS}', $this->blocked_ips ? $this->blocked_ips[key($this->blocked_ips)]['ip'] : '', $sfw_die_page);
			
			// Service info
			$sfw_die_page = str_replace('{REQUEST_URI}',    $request_uri,                    $sfw_die_page);
			$sfw_die_page = str_replace('{COOKIE_PREFIX}',  $cookie_prefix,                  $sfw_die_page);
			$sfw_die_page = str_replace('{COOKIE_DOMAIN}',  $cookie_domain,                  $sfw_die_page);
			$sfw_die_page = str_replace('{SERVICE_ID}',     $apbct->data['service_id'],      $sfw_die_page);
			$sfw_die_page = str_replace('{HOST}',           apbct_get_server_variable( 'HTTP_HOST' ),           $sfw_die_page);
			
			$sfw_die_page = str_replace(
				'{SFW_COOKIE}',
				$this->test
					? $this->all_ips['sfw_test']['ip']
					: md5(current(end($this->blocked_ips)).$api_key),
				$sfw_die_page
			);
			
			if($this->debug){
				$debug = '<h1>IP and Networks</h1>'
					. var_export($this->all_ips, true)
					.'<h1>Blocked IPs</h1>'
			        . var_export($this->blocked_ips, true)
			        .'<h1>Passed IPs</h1>'
			        . var_export($this->passed_ips, true)
					. '<h1>Headers</h1>'
					. var_export(apache_request_headers(), true)
					. '<h1>REMOTE_ADDR</h1>'
					. var_export(apbct_get_server_variable( 'REMOTE_ADDR' ), true)
					. '<h1>SERVER_ADDR</h1>'
					. var_export(apbct_get_server_variable( 'REMOTE_ADDR' ), true)
					. '<h1>IP_ARRAY</h1>'
					. var_export($this->ip_array, true)
					. '<h1>ADDITIONAL</h1>'
					. var_export($this->debug_data, true);
			}else
				$debug = '';
			
			$sfw_die_page = str_replace( "{DEBUG}", $debug, $sfw_die_page );
			$sfw_die_page = str_replace('{GENERATED}', "<p>The page was generated at&nbsp;".date("D, d M Y H:i:s")."</p>",$sfw_die_page);
			
			wp_die($sfw_die_page, "Blacklisted", Array('response'=>403));
			
		}else{
			wp_die("IP BLACKLISTED", "Blacklisted", Array('response'=>403));
		}
		
	}
}
