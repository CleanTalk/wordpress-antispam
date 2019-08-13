<?php

// Getting version form main file (look above)
$plugin_info = get_file_data(__FILE__, array('Version' => 'Version', 'Name' => 'Plugin Name',));

// Common params
define('APBCT_NAME',             $plugin_info['Name']);
define('APBCT_VERSION',          $plugin_info['Version']);
define('APBCT_URL_PATH',         plugins_url('', __FILE__)); //HTTP path.   Plugin root folder without '/'.
define('APBCT_DIR_PATH',         plugin_dir_path(__FILE__));          //System path. Plugin root folder with '/'.
define('APBCT_PLUGIN_BASE_NAME', plugin_basename(__FILE__));          //Plugin base name.
define('APBCT_CASERT_PATH',      file_exists(ABSPATH . WPINC . '/certificates/ca-bundle.crt') ? ABSPATH . WPINC . '/certificates/ca-bundle.crt' : ''); // SSL Serttificate path

// API params
define('APBCT_AGENT',        'wordpress-'.str_replace('.', '', $plugin_info['Version']));
define('APBCT_MODERATE_URL', 'http://moderate.cleantalk.org'); //Api URL

// Option names
define('APBCT_DATA',             'cleantalk_data');             //Option name with different plugin data.
define('APBCT_SETTINGS',         'cleantalk_settings');         //Option name with plugin settings.
define('APBCT_NETWORK_SETTINGS', 'cleantalk_network_settings'); //Option name with plugin network settings.
define('APBCT_DEBUG',            'cleantalk_debug');            //Option name with a debug data. Empty by default.

// Multisite
define('APBCT_WPMS', (is_multisite() ? true : false)); // WMPS is enabled

// Sessions
define('APBCT_SEESION__LIVE_TIME', 86400*2);
define('APBCT_SEESION__CHANCE_TO_CLEAN', 100);

// Different params
define('APBCT_REMOTE_CALL_SLEEP', 5); // Minimum time between remote call

// Database tables names
global $wpdb;
$db_prefix = (defined('APBCT_WHITELABEL') && APBCT_WHITELABEL == true) && defined('CLEANTALK_ACCESS_KEY') ? $wpdb->base_prefix : $wpdb->prefix;
// Database constants
define('APBCT_TBL_FIREWALL_DATA', $db_prefix . 'cleantalk_sfw');      // Table with firewall data.
define('APBCT_TBL_FIREWALL_LOG',  $db_prefix . 'cleantalk_sfw_logs'); // Table with firewall logs.
define('APBCT_TBL_SESSIONS',      $db_prefix . 'cleantalk_sessions'); // Table with session data.
define('APBCT_SELECT_LIMIT',      5000); // Select limit for logs.
define('APBCT_WRITE_LIMIT',       5000); // Write limit for firewall data.