<?php

/*
 * 
 * CleanTalk Antispam State class
 * 
 * @package Antiospam Plugin by CleanTalk
 * @subpackage State
 * @Version 1.0
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 *
 */

class CleantalkState
{	
	public $option_prefix = 'cleantalk';
	public $storage = array();
	public $integrations = array();
	public $def_settings = array(
	
		'spam_firewall'       => 1,
        'apikey'              => '',
		'custom_key'          => 0,
        'autoPubRevelantMess' => 0,
		
		/* Forms for protection */
        'registrations_test'         => 1,
        'comments_test'              => 1, 
        'contact_forms_test'         => 1, 
        'general_contact_forms_test' => 1, // Antispam test for unsupported and untested contact forms 
		'wc_checkout_test'           => 0, //WooCommerce checkout default test => OFF
		'check_external'             => 0,
        'check_internal'             => 0,
//        'validate_email_existence'   => 1,
		
		/* Comments and messages */
		'bp_private_messages' =>   1, //buddyPress private messages test => ON
		'check_comments_number' => 1,
        'remove_old_spam' =>       0,
		'remove_comments_links' => 0, //Removes links from approved comments
		'show_check_links' =>      1, //Shows check link to Cleantalk's DB. And allowing to control comments form public page.
		
		// Data processing
        'protect_logged_in' =>     1, // Do anit-spam tests to for logged in users.
		'use_ajax' =>              1,
		'general_postdata_test' => 0, //CAPD
        'set_cookies'=>            1, // Disable cookies generatation to be compatible with Varnish.
        'set_cookies__sessions'=>  0, // Use alt sessions for cookies.
		'alternative_sessions'=>   0, // AJAX Sessions.
        'ssl_on' =>                0, // Secure connection to servers 
		'use_buitin_http_api' =>   0, // Using Wordpress HTTP built in API
		
		// Administrator Panel
        'show_adminbar'    => 1, // Show the admin bar.
		'all_time_counter' => 0,
		'daily_counter'    => 0,
		'sfw_counter'      => 0,
		
		//Others
        'spam_store_days'         => '15', // Days before delete comments from folder Spam 
        'relevance_test'          => 0, // Test comment for relevance 
        'notice_api_errors'       => 0, // Send API error notices to WP admin
        'user_token'              => '', //user token for auto login into spam statistics
        'collect_details'         => 0, // Collect details about browser of the visitor. 
        'send_connection_reports' => 0, //Send connection reports to Cleantalk servers
		'async_js'                => 0,
		'debug_ajax'              => 0,
		
		// GDPR
		'gdpr_enabled' => 0,
		'gdpr_text'    => 'By using this form you agree with the storage and processing of your data by using the Privacy Policy on this website.',
		
		// Msic
		'store_urls'            => 1,
		'store_urls__sessions'  => 1,
		'comment_notify'        => 1,
		'comment_notify__roles' => array('administrator'),
    );
	
	public $def_data = array(
		
		// Plugin data
		'plugin_version'     => APBCT_VERSION,
        'user_token'         => '', // User token 
        'js_keys'            => array(), // Keys to do JavaScript antispam test 
        'js_keys_store_days' => 14, // JavaScript keys store days - 8 days now
        'js_key_lifetime'    => 86400, // JavaScript key life time in seconds - 1 day now
        'last_remote_call'   => 0, //Timestam of last remote call
		
		// Account data
		'service_id'    => 0,
		'moderate'      => 0,
		'moderate_ip'   => 0,
		'ip_license'    => 0,
		'spam_count'    => 0,
		'auto_update'   => 0,
		'user_token'    => '',
		'license_trial' => 0,
		
		// Notices
		'notice_show' => 0,
		'notice_trial' => 0,
		'notice_renew' => 0,
		'notice_review' => 0,
		'notice_auto_update' => 0,
		
		// Brief data
		'brief_data' => array(
			'spam_stat' => array(),
			'top5_spam_ip' => array(),
		),
		
		'array_accepted'     => array(),
		'array_blocked'      => array(),
		'current_hour'       => '',
		'sfw_counter' => array(
			'all'     => 0,
			'blocked' => 0,
		),
		'all_time_counter' => array(
			'accepted' => 0,
			'blocked'  => 0,
		),
		'user_counter' => array(
			'accepted' => 0,
			'blocked'  => 0,
			// 'since' => date('d M'),
		),
        'connection_reports' => array(
            'success'         => 0,
            'negative'        => 0,
            'negative_report' => array(),
            // 'since'        => date('d M'),
        ),
		
		// A-B tests
		'ab_test' => array(
			'sfw_enabled' => false,
		),
		
		// White label
		'white_label_data' => array(
			'is_key_recieved' => false,
		),
		
		// Misc
		'feedback_request' => '',
		'key_is_ok'        => 0,
    );
	
	public $def_network_data = array(
		'allow_custom_key'   => 0,
		'key_is_ok'          => 0,
		'apikey'           => '',
		'user_token'         => '',
		'service_id'         => 0,
	);
	
	public $def_remote_calls = array(
		'close_renew_banner' => array(
			'last_call' => 0,
		),
		'sfw_update' => array(
			'last_call' => 0,
		),
		'sfw_send_logs' => array(
			'last_call' => 0,
		),
		'update_plugin' => array(
			'last_call' => 0,
		),
		'update_settings' => array(
			'last_call' => 0,
		),
	);
	
	public function __construct($option_prefix, $options = array('settings'), $wpms = false)
	{
		$this->option_prefix = $option_prefix;
		
		if($wpms){
			$option = get_site_option($this->option_prefix.'_network_data');
			$option = is_array($option) ? $option : $this->def_network_data;
			$this->network_data = new ArrayObject($option);
		}
		
		foreach($options as $option_name){
			
			$option = get_option($this->option_prefix.'_'.$option_name);
			
			// Setting default options
			if($this->option_prefix.'_'.$option_name === 'cleantalk_settings'){
				$option = is_array($option) ? array_merge($this->def_settings, $option) : $this->def_settings;
			}
			
			// Setting default data
			if($this->option_prefix.'_'.$option_name === 'cleantalk_data'){
				$option = is_array($option) ? array_merge($this->def_data,     $option) : $this->def_data;
			}
			
			// Setting default errors
			if($this->option_prefix.'_'.$option_name === 'cleantalk_errors'){
				$option = $option ? $option : array();
			}
			
			// Default remote calls
			if($this->option_prefix.'_'.$option_name === 'cleantalk_remote_calls'){
				$option = is_array($option) ? array_merge($this->def_remote_calls, $option) : $this->def_remote_calls;
			}
			
			$this->$option_name = is_array($option) ? new ArrayObject($option) : $option;
		}
	}
	
	private function getOption($option_name)
	{
		$option = get_option('cleantalk_'.$option_name, null);
		$this->$option_name = gettype($option) === 'array'
			? new ArrayObject($option)
			: $option;
	}
	
	public function save($option_name, $use_perfix = true, $autoload = true)
	{	
		$option_name_to_save = $use_perfix ? $this->option_prefix.'_'.$option_name : $option_name;
		$arr = array();
		foreach($this->$option_name as $key => $value){
			$arr[$key] = $value;
		}
		update_option($option_name_to_save, $arr, $autoload);
	}
	
	public function saveSettings()
	{
		update_option($this->option_prefix.'_settings', (array)$this->settings);
	}
	
	public function saveData()
	{
		update_option($this->option_prefix.'_data', (array)$this->data);
	}
	
	public function saveErrors()
	{
		update_option($this->option_prefix.'_errors', (array)$this->errors);
	}
	
	public function saveNetworkData()
	{		
		update_site_option($this->option_prefix.'_network_data', $this->network_data);
	}
	
	public function deleteOption($option_name, $use_prefix = false)
	{
		if($this->__isset($option_name)){
			$this->__unset($option_name);
			delete_option( ($use_prefix ? $this->option_prefix.'_' : '') . $option_name);
		}		
	}
	
	/**
	 * Prepares an adds an error to the plugin's data
	 *
	 * @param string type
	 * @param mixed array || string
	 * @returns null
	 */
	public function error_add($type, $error, $major_type = null, $set_time = true)
	{
		$error = is_array($error)
			? $error['error_string']
			: $error;
		
		$error = array(
			'error_string' => $error,
			'error_time'   => $set_time ? current_time('timestamp') : null,
		);
		
		if(!empty($major_type)){
			$this->errors[$major_type][$type] = $error;
		}else{
			$this->errors[$type] = $error;			
		}
		
		$this->saveErrors();
	}
	
	/**
	 * Deletes an error from the plugin's data
	 *
	 * @param mixed (array of strings || string 'elem1 elem2...' || string 'elem') type
	 * @param delay saving
	 * @returns null
	 */
	public function error_delete($type, $save_flag = false, $major_type = null)
	{
		if(is_string($type))
			$type = explode(' ', $type);
		
		foreach($type as $val){
			if($major_type){
				if(isset($this->errors[$major_type][$val]))
					unset($this->errors[$major_type][$val]);
			}else{
				if(isset($this->errors[$val]))
					unset($this->errors[$val]);
			}
		}
		
		// Save if flag is set and there are changes
		if($save_flag)
			$this->saveErrors();
	}
	
	/**
	 * Deletes all errors from the plugin's data
	 *
	 * @param delay saving
	 * @returns null
	 */
	public function error_delete_all($save_flag = false)
	{
		$this->errors = array();
		if($save_flag)
			$this->saveErrors();
	}
	
	public function __set($name, $value) 
    {
        $this->storage[$name] = $value;
		if(isset($this->storage['data']) && array_key_exists($name, $this->storage['data'])){
			$this->storage['data'][$name] = $value;
		}
    }
	
    public function __get($name) 
    {
		// First check in storage
        if (array_key_exists($name, $this->storage)){
            return $this->storage[$name];
			
		// Then in data
        }elseif(array_key_exists($name, $this->storage['data'])){
			$this->$name = $this->storage['data'][$name];
			return $this->storage['data'][$name];
		
		// Maybe it's apikey?
		}elseif($name == 'api_key'){
			$this->$name = $this->storage['settings']['apikey'];
			return $this->storage['settings']['apikey'];
		
		// Otherwise try to get it from db settings table
		// it will be arrayObject || scalar || null
		}else{
			$this->getOption($name);
			return $this->storage[$name];
		}
		
    }
	
    public function __isset($name) 
    {
        return isset($this->storage[$name]);
    }
	
    public function __unset($name) 
    {
        unset($this->storage[$name]);
    }
	
	public function __call($name, $arguments)
	{
        error_log ("Calling method '$name' with arguments: " . implode(', ', $arguments). "\n");
    }
	
    public static function __callStatic($name, $arguments)
	{
        error_log("Calling static method '$name' with arguments: " . implode(', ', $arguments). "\n");
    }
}
