<?php

/*
 * CleanTalk SpamFireWall Wordpress class
 * Compatible only with Wordpress.
 * Version 3.0-wp
 * author Cleantalk team (welcome@cleantalk.org)
 * copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * see https://github.com/CleanTalk/php-antispam
*/

class CleantalkSFW extends CleantalkSFW_Base
{
	
	public function __construct()
	{
		// Creating database object. Depends on current CMS.
		$this->db = new CleantalkDB_Wordpress();

		// Use default tables if not specified
		$this->data_table = defined('APBCT_TBL_FIREWALL_DATA') ? APBCT_TBL_FIREWALL_DATA : $this->db->prefix . 'cleantalk_sfw';
		$this->log_table  = defined('APBCT_TBL_FIREWALL_LOG')  ? APBCT_TBL_FIREWALL_LOG  : $this->db->prefix . 'cleantalk_sfw_logs';
		
		$this->debug = isset($_GET['show_debug']) && intval($_GET['show_debug']) === 1 ? true : false;
	}
	
	/*
	* Shows DIE page
	* 
	* Stops script executing
	*/	
	public function sfw_die($api_key, $cookie_prefix = '', $cookie_domain = ''){
		
		global $apbct;
		
		// File exists?
		if(file_exists(CLEANTALK_PLUGIN_DIR . "inc/sfw_die_page.html")){
			$sfw_die_page = file_get_contents(CLEANTALK_PLUGIN_DIR . "inc/sfw_die_page.html");
		}else{
			wp_die("IP BLACKLISTED", "Blacklisted", Array('response'=>403), true);
		}
		
		// Translation
		$request_uri = $_SERVER['REQUEST_URI'];
		$sfw_die_page = str_replace('{SFW_DIE_NOTICE_IP}',              __('SpamFireWall is activated for your IP ', 'cleantalk'), $sfw_die_page);
		$sfw_die_page = str_replace('{SFW_DIE_MAKE_SURE_JS_ENABLED}',   __('To continue working with web site, please make sure that you have enabled JavaScript.', 'cleantalk'), $sfw_die_page);
		$sfw_die_page = str_replace('{SFW_DIE_CLICK_TO_PASS}',          __('Please click below to pass protection,', 'cleantalk'), $sfw_die_page);
		$sfw_die_page = str_replace('{SFW_DIE_YOU_WILL_BE_REDIRECTED}', sprintf(__('Or you will be automatically redirected to the requested page after %d seconds.', 'cleantalk'), 1), $sfw_die_page);
		$sfw_die_page = str_replace('{CLEANTALK_TITLE}',                __('Antispam by CleanTalk', 'cleantalk'), $sfw_die_page);
		$sfw_die_page = str_replace('{TEST_TITLE}',                     ($this->is_test ? __('This is the testing page for SpamFireWall', 'cleantalk') : ''), $sfw_die_page);
		
		// Service info
		$sfw_die_page = str_replace('{REMOTE_ADDRESS}', $this->blocked_ip,               $sfw_die_page);
		$sfw_die_page = str_replace('{REQUEST_URI}',    $request_uri,                    $sfw_die_page);
		$sfw_die_page = str_replace('{COOKIE_PREFIX}',  $cookie_prefix,                  $sfw_die_page);
		$sfw_die_page = str_replace('{COOKIE_DOMAIN}',  $cookie_domain,                  $sfw_die_page);
		$sfw_die_page = str_replace('{SFW_COOKIE}',     md5($this->blocked_ip.$api_key), $sfw_die_page);
		$sfw_die_page = str_replace( "{SERVICE_ID}",    $apbct->data['service_id'],      $sfw_die_page );
		$sfw_die_page = str_replace( "{HOST}",          $_SERVER['HTTP_HOST'],           $sfw_die_page );
				
		if($this->debug){
			$debug = '<h1>Networks</h1>'
				. var_export($this->debug_networks, true)
				. '<h1>Headers</h1>'
				. var_export(apache_request_headers(), true)
				. '<h1>REMOTE_ADDR</h1>'
				. var_export($_SERVER['REMOTE_ADDR'], true)
				. '<h1>SERVER_ADDR</h1>'
				. var_export($_SERVER['SERVER_ADDR'], true)
				. '<h1>IP_ARRAY</h1>'
				. var_export($this->ip_array, true)
				. '<h1>ADDITIONAL</h1>'
				. var_export($this->debug_data, true);
		}else
			$debug = '';
		
		$sfw_die_page = str_replace( "{DEBUG}", $debug, $sfw_die_page );
		
		// Headers
		if(headers_sent() === false){
			header('Expires: '.date(DATE_RFC822, mktime(0, 0, 0, 1, 1, 1971)));
			header('Cache-Control: no-store, no-cache, must-revalidate');
			header('Cache-Control: post-check=0, pre-check=0', FALSE);
			header('Pragma: no-cache');
			header("HTTP/1.0 403 Forbidden");
		}
		$sfw_die_page = str_replace('{GENERATED}', "<p>The page was generated at&nbsp;".date("D, d M Y H:i:s")."</p>",$sfw_die_page);
		
		wp_die($sfw_die_page, "Blacklisted", Array('response'=>403));
		
	}
}
