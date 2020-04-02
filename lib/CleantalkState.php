<?php

/**
 * CleanTalk Antispam State class
 * 
 * @package Antiospam Plugin by CleanTalk
 * @subpackage State
 * @Version 2.0
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 */

/**
 * COMMON
 *
 * @property string       api_key
 *
 * STAND ALONE
 *
 * @property ArrayObject  settings
 * @property ArrayObject  data
 *
 * @property mixed        moderate_ip
 * @property mixed|string plugin_version
 * @property mixed|string db_prefix
 * @property string       settings_link
 * @property int          key_is_ok
 * @property string       logo__small__colored
 * @property string       logo__small
 * @property string       logo
 * @property string       plugin_name
 * @property string       base_name
 * @property array|mixed  errors
 *
 * NETWORK
 * @property ArrayObject  network_data
 * @property ArrayObject  network_settings
 * @property mixed        allow_custom_key
 * @property bool         white_label
 * @property mixed        moderate
 */
class CleantalkState
{
	public $user = null;
	public $option_prefix = 'cleantalk';
	public $storage = array();
	public $integrations = array();
	public $def_settings = array(
	
		'spam_firewall'       => 1,
        'apikey'              => '',
        'autoPubRevelantMess' => 0,
		
		/* Forms for protection */
        'registrations_test'             => 1,
        'comments_test'                  => 1, 
        'contact_forms_test'             => 1, 
        'general_contact_forms_test'     => 1, // Antispam test for unsupported and untested contact forms 
		'wc_checkout_test'               => 1, // WooCommerce checkout default test
		'wc_register_from_order'         => 1, // Woocommerce registration during checkout
		'search_test'                    => 1, // Test deafult Wordpress form
		'check_external'                 => 0,
		'check_external__capture_buffer' => 0,
        'check_internal'                 => 0,
		
		/* Comments and messages */
		'disable_comments__all'   => 0,
		'disable_comments__posts' => 0,
		'disable_comments__pages' => 0,
		'disable_comments__media' => 0,
		'bp_private_messages' =>   1, //buddyPress private messages test => ON
		'check_comments_number' => 1,
        'remove_old_spam' =>       0,
		'remove_comments_links' => 0, // Removes links from approved comments
		'show_check_links' =>      1, // Shows check link to Cleantalk's DB.
		'manage_comments_on_public_page' =>      0, // Allows to control comments on public page.
		
		// Data processing
        'protect_logged_in' =>     1, // Do anit-spam tests to for logged in users.
		'use_ajax' =>              1,
		'use_static_js_key' =>     -1,
		'general_postdata_test' => 0, //CAPD
        'set_cookies'=>            1, // Disable cookies generatation to be compatible with Varnish.
        'set_cookies__sessions'=>  0, // Use alt sessions for cookies.
        'ssl_on' =>                0, // Secure connection to servers 
		'use_buitin_http_api' =>   1, // Using Wordpress HTTP built in API
		
		// Exclusions
		'exclusions__urls'               => '',
		'exclusions__urls__use_regexp'   => 0,
		'exclusions__fields'             => '',
		'exclusions__fields__use_regexp' => 0,
		'exclusions__roles'               => array('Administrator'),
		
		// Administrator Panel
        'show_adminbar'    => 1, // Show the admin bar.
		'all_time_counter' => 0,
		'daily_counter'    => 0,
		'sfw_counter'      => 0,
		
		//Others
        'user_token'              => '',
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
		'complete_deactivation' => 0,
    );
	
	public $def_data = array(
		
		// Plugin data
		'plugin_version'     => APBCT_VERSION,
		'js_keys'            => array(), // Keys to do JavaScript antispam test
		'js_keys_store_days' => 14, // JavaScript keys store days - 8 days now
		'js_key_lifetime'    => 86400, // JavaScript key life time in seconds - 1 day now
		'last_remote_call'   => 0, //Timestam of last remote call
		
		// Antispam
		'spam_store_days'         => 15, // Days before delete comments from folder Spam
		'relevance_test'          => 0, // Test comment for relevance
		'notice_api_errors'       => 0, // Send API error notices to WP admin
		
		// Account data
		'service_id'    => 0,
		'moderate'      => 0,
		'moderate_ip'   => 0,
		'ip_license'    => 0,
		'spam_count'    => 0,
		'auto_update'   => 0,
		'user_token'    => '', // User token for auto login into spam statistics
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
		'connection_reports'  => array(
			'success'         => 0,
			'negative'        => 0,
			'negative_report' => array(),
			// 'since'        => date('d M'),
		),
		
		// A-B tests
		'ab_test' => array(
			'sfw_enabled' => false,
		),
		
		// Misc
		'feedback_request' => '',
		'key_is_ok'        => 0,
		'salt'             => '',
	);
	
	public $def_network_settings = array(
		
		// Key
		'apikey'                => '',
		'allow_custom_key'      => 1,
		'allow_custom_settings' => 1,
		
		// White label settings
		'white_label'              => 0,
		'white_label__hoster_key'  => '',
		'white_label__plugin_name' => 'Anti-Spam by CleanTalk',
	);
	
	public $def_network_data = array(
		'key_is_ok'          => 0,
		'moderate'           => 0,
		'valid'              => 0,
		'user_token'         => '',
		'service_id'         => 0,
		'auto_update'        => 0,
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
		'install_plugin' => array(
			'last_call' => 0,
		),
		'activate_plugin' => array(
			'last_call' => 0,
		),
		'insert_auth_key' => array(
			'last_call' => 0,
		),
		'deactivate_plugin' => array(
			'last_call' => 0,
		),
		'uninstall_plugin' => array(
			'last_call' => 0,
		),
		'update_settings' => array(
			'last_call' => 0,
		),
	);
	
	public $def_stats = array(
		'sfw' => array(
			'last_send_time'   => 0,
			'last_send_amount' => 0,
			'last_update_time' => 0,
			'entries'          => 0,
		),
		'last_sfw_block' => array(
			'time' => 0,
			'ip'   => '',
		),
		'last_request' => array(
			'time'   => 0,
			'server' => '',
		),
		'requests' => array(
			'0' => array(
				'amount' => 1,
				'average_time' => 0,
			),
		)
	);
	
	/**
	 * CleantalkState constructor.
	 *
	 * @param string $option_prefix Database settings prefix
	 * @param array  $options       Array of strings. Types of settings you want to get.
	 * @param bool   $wpms          Is multisite?
	 */
	public function __construct($option_prefix, $options = array('settings'))
	{
		$this->option_prefix = $option_prefix;
		
		// Network settings
		$option = get_site_option($this->option_prefix.'_network_settings');
		$option = is_array($option) ? array_merge($this->def_network_settings, $option) : $this->def_network_settings;
		$this->network_settings = new ArrayObject($option);
		
		// Network data
		$option = get_site_option($this->option_prefix.'_network_data');
		$option = is_array($option) ? array_merge($this->def_network_data, $option) : $this->def_network_data;
		$this->network_data = new ArrayObject($option);
		
		foreach($options as $option_name){
			
			$option = get_option($this->option_prefix.'_'.$option_name);
			
			// Setting default options
			if($this->option_prefix.'_'.$option_name === 'cleantalk_settings'){
				$option = is_array($option) ? array_merge($this->def_settings, $option) : $this->def_settings;
			}
			
			// Setting default data
			if($this->option_prefix.'_'.$option_name === 'cleantalk_data'){
				$option = is_array($option) ? array_merge($this->def_data,     $option) : $this->def_data;
				// Generate salt
				$option['salt'] = empty($option['salt'])
					? str_pad(rand(0, getrandmax()), 6, '0').str_pad(rand(0, getrandmax()), 6, '0')
					: $option['salt'];
			}
			
			// Setting default errors
			if($this->option_prefix.'_'.$option_name === 'cleantalk_errors'){
				$option = $option ? $option : array();
			}
			
			// Default remote calls
			if($this->option_prefix.'_'.$option_name === 'cleantalk_remote_calls'){
				$option = is_array($option) ? array_merge($this->def_remote_calls, $option) : $this->def_remote_calls;
			}

			// Default statistics
			if($this->option_prefix.'_'.$option_name === 'cleantalk_stats'){
				$option = is_array($option) ? array_merge($this->def_stats, $option) : $this->def_stats;
			}
			
			$this->$option_name = is_array($option) ? new ArrayObject($option) : $option;
		}
	}
	
	/**
	 * Get specified option from database
	 *
	 * @param string $option_name
	 */
	private function getOption($option_name)
	{
		$option = get_option('cleantalk_'.$option_name, null);
		$this->$option_name = gettype($option) === 'array'
			? new ArrayObject($option)
			: $option;
	}
	
	/**
	 * Save option to database
	 *
	 * @param string $option_name
	 * @param bool   $use_perfix
	 * @param bool   $autoload Use autoload flag?
	 */
	public function save($option_name, $use_perfix = true, $autoload = true)
	{
		$option_name_to_save = $use_perfix ? $this->option_prefix.'_'.$option_name : $option_name;
		$arr = array();
		foreach($this->$option_name as $key => $value){
			$arr[$key] = $value;
		}
		update_option($option_name_to_save, $arr, $autoload);
	}
	
	/**
	 * Save PREFIX_setting to DB.
	 */
	public function saveSettings()
	{
		update_option($this->option_prefix.'_settings', (array)$this->settings);
	}
	
	/**
	 * Save PREFIX_data to DB.
	 */
	public function saveData()
	{
		update_option($this->option_prefix.'_data', (array)$this->data);
	}
	
	/**
	 * Save PREFIX_error to DB.
	 */
	public function saveErrors()
	{
		update_option($this->option_prefix.'_errors', (array)$this->errors);
	}
	
	/**
	 * Save PREFIX_network_data to DB.
	 */
	public function saveNetworkData()
	{
		update_site_option($this->option_prefix.'_network_data', $this->network_data);
	}
	
	/**
	 * Save PREFIX_network_data to DB.
	 */
	public function saveNetworkSettings()
	{
		update_site_option($this->option_prefix.'_network_settings', $this->network_settings);
	}
	
	/**
	 * Unset and delete option from DB.
	 *
	 * @param string $option_name
	 * @param bool   $use_prefix
	 */
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
	 * @param string       $type       Error type/subtype
	 * @param string|array $error      Error
	 * @param string       $major_type Error major type
	 * @param bool         $set_time   Do we need to set time of this error
	 *
	 * @returns null
	 */
	public function error_add($type, $error, $major_type = null, $set_time = true)
	{
		$error = is_array($error)
			? $error['error']
			: $error;
		
		// Exceptions
		if( ($type == 'send_logs'          && $error == 'NO_LOGS_TO_SEND') ||
			($type == 'send_firewall_logs' && $error == 'NO_LOGS_TO_SEND') ||
			$error == 'LOG_FILE_NOT_EXISTS'
		)
			return;
		
		$error = array(
			'error'      => $error,
			'error_time' => $set_time ? current_time('timestamp') : null,
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
	 * @param array|string $type       Error type to delete
	 * @param bool         $save_flag  Do we need to save data after error was deleted
	 * @param string       $major_type Error major type to delete
	 *
	 * @returns null
	 */
	public function error_delete($type, $save_flag = false, $major_type = null)
	{
		/** @noinspection DuplicatedCode */
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
	 * @param bool $save_flag Do we need to save data after all errors was deleted
	 *
	 * @returns null
	 */
	public function error_delete_all($save_flag = false)
	{
		$this->errors = array();
		if($save_flag)
			$this->saveErrors();
	}
	
	/**
	 * Magic.
	 * Add new variables to storage[NEW_VARIABLE]
	 * And duplicates it in storage['data'][NEW_VARIABLE]
	 *
	 * @param string $name
	 * @param mixed  $value
	 */
	public function __set($name, $value)
    {
        $this->storage[$name] = $value;
		if(isset($this->storage['data'][$name])){
			$this->storage['data'][$name] = $value;
		}
    }
	
	/**
	 * Magic.
	 * Search and get param from: storage, data, api_key, database
	 *
	 * @param $name
	 *
	 * @return mixed
	 */
	public function __get($name)
    {
		// First check in storage
        if (isset($this->storage[$name])){
            return $this->storage[$name];
	        
		// Then in data
        }elseif(isset($this->storage['data'][$name])){
			$this->$name = $this->storage['data'][$name];
			return $this->storage['data'][$name];
			
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
	
	public function server(){
		return \Cleantalk\Common\Server::getInstance();
	}
	public function cookie(){
		return \Cleantalk\Common\Cookie::getInstance();
	}
	public function request(){
		return \Cleantalk\Common\Request::getInstance();
	}
	public function post(){
		return \Cleantalk\Common\Post::getInstance();
	}
	public function get(){
		return \Cleantalk\Common\Get::getInstance();
	}
}
