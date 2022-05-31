<?php

namespace Cleantalk\ApbctWP;

use ArrayObject;

/**
 * CleanTalk Anti-Spam State class
 *
 * @package Antiospam Plugin by CleanTalk
 * @subpackage State
 * @Version 3.0
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2021 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 *
 * @psalm-suppress PossiblyUnusedProperty
 */

class State extends \Cleantalk\Common\State
{
    /**
     * @var  \WP_User
     */
    public $user;

    /**
     * @var array
     */
    public $storage = array();

    public $def_settings = array(

        'apikey'                                   => '',

        // SpamFireWall settings
        'sfw__enabled'                             => 1,
        'sfw__anti_flood'                          => 0,
        'sfw__anti_flood__view_limit'              => 20,
        'sfw__anti_crawler'                        => 0,
        'sfw__use_delete_to_clear_table'           => 0,
        'sfw__random_get'                          => -1,

        // Forms for protection
        'forms__registrations_test'                => 1,
        'forms__comments_test'                     => 1,
        'forms__contact_forms_test'                => 1,
        'forms__general_contact_forms_test'        => 1,
        // Anti-Spam test for unsupported and untested contact forms
        'forms__wc_checkout_test'                  => 1,
        // WooCommerce checkout default test
        'forms__wc_register_from_order'            => 1,
        // Woocommerce registration during checkout
        'forms__wc_add_to_cart'                    => 0,
        // Woocommerce add to cart
        'forms__search_test'                       => 1,
        // Test default WordPress form
        'forms__check_external'                    => 0,
        'forms__check_external__capture_buffer'    => 0,
        'forms__check_internal'                    => 0,

        // Comments and messages
        'comments__disable_comments__all'          => 0,
        'comments__disable_comments__posts'        => 0,
        'comments__disable_comments__pages'        => 0,
        'comments__disable_comments__media'        => 0,
        'comments__bp_private_messages'            => 1,
        //buddyPress private messages test => ON
        'comments__check_comments_number'          => 1,
        'comments__remove_old_spam'                => 0,
        'comments__remove_comments_links'          => 0,
        // Removes links from approved comments
        'comments__show_check_links'               => 1,
        // Shows check link to Cleantalk's DB.
        'comments__manage_comments_on_public_page' => 0,
        // Allows to control comments on public page.
        'comments__hide_website_field'             => 0,
        // Hide website field from comment form

        // Data processing
        'data__protect_logged_in'                  => 1,
        // Do anti-spam tests to for logged in users.
        'data__use_ajax'                           => 0,
        'data__use_static_js_key'                  => -1,
        'data__general_postdata_test'              => 0,
        //CAPD
        'data__set_cookies'                        => 3,
        // Cookies type: 0 - Off / 1 - Native cookies / 2 - Alt cookies / 3 - Auto
        'data__ssl_on'                             => 0,
        // Secure connection to servers
        'data__pixel'                              => '3',
        'data__email_check_before_post'            => 1,
        'data__honeypot_field'                     => 1,
        'data__email_decoder'                      => 0,

        // Exclusions
        // Send to the cloud some excepted requests
        'exclusions__log_excluded_requests'        => 0,
        'exclusions__urls'                         => '',
        'exclusions__urls__use_regexp'             => 0,
        'exclusions__fields'                       => '',
        'exclusions__fields__use_regexp'           => 0,
        'exclusions__roles'                        => array('Administrator'),

        // Administrator Panel
        'admin_bar__show'                          => 1,
        // Show the admin bar.
        'admin_bar__all_time_counter'              => 0,
        'admin_bar__daily_counter'                 => 0,
        'admin_bar__sfw_counter'                   => 0,

        // GDPR
        'gdpr__enabled'                            => 0,
        'gdpr__text'                               => 'By using this form you agree with the storage and processing of your data by using the Privacy Policy on this website.',

        // Misc
        'misc__send_connection_reports'            => 0,
        // Send connection reports to Cleantalk servers
        'misc__async_js'                           => 0,
        'misc__store_urls'                         => 1,
        'misc__complete_deactivation'              => 0,
        'misc__debug_ajax'                         => 0,

        // WordPress
        'wp__use_builtin_http_api'                 => 1,
        // Using WordPress HTTP built in API
        'wp__comment_notify'                       => 1,
        'wp__comment_notify__roles'                => array('administrator'),
        'wp__dashboard_widget__show'               => 1,

    );

    public $def_data = array(

        // Plugin data
        'plugin_version'                 => APBCT_VERSION,
        'js_keys'                        => array(), // Keys to do JavaScript antispam test
        'js_keys_store_days'             => 14, // JavaScript keys store days - 8 days now
        'js_key_lifetime'                => 86400, // JavaScript key life time in seconds - 1 day now
        'last_remote_call'               => 0, //Timestam of last remote call
        'current_settings_template_id'   => null,  // Loaded settings template id
        'current_settings_template_name' => null,  // Loaded settings template name
        'ajax_type'                      => 'admin_ajax', // Ajax type - admin_ajax|REST
        'cookies_type'                   => 'native', // Native / Alternative / None

        // Anti-Spam
        'spam_store_days'                => 15, // Days before delete comments from folder Spam
        'relevance_test'                 => 0, // Test comment for relevance
        'notice_api_errors'              => 0, // Send API error notices to WP admin

        // Account data
        'account_email'                  => '',
        'service_id'                     => 0,
        'moderate'                       => 0,
        'moderate_ip'                    => 0,
        'ip_license'                     => 0,
        'spam_count'                     => 0,
        'auto_update'                    => 0,
        'user_token'                     => '', // User token for auto login into spam statistics
        'license_trial'                  => 0,

        // Notices
        'notice_show'                    => 0,
        'notice_trial'                   => 0,
        'notice_renew'                   => 0,
        'notice_review'                  => 0,
        'notice_auto_update'             => 0,
        'notice_incompatibility'         => array(),

        // Brief data
        'brief_data'                     => array(
            'spam_stat'    => array(),
            'top5_spam_ip' => array(),
        ),

        'array_accepted'              => array(),
        'array_blocked'               => array(),
        'current_hour'                => '',
        'admin_bar__sfw_counter'      => array(
            'all'     => 0,
            'blocked' => 0,
        ),
        'admin_bar__all_time_counter' => array(
            'accepted' => 0,
            'blocked'  => 0,
        ),
        'user_counter'                => array(
            'accepted' => 0,
            'blocked'  => 0,
            // 'since' => date('d M'),
        ),
        'connection_reports'          => array(
            'success'         => 0,
            'negative'        => 0,
            'negative_report' => array(),
            // 'since'        => date('d M'),
        ),

        // A-B tests
        'ab_test'                     => array(
            'sfw_enabled' => false,
        ),

        // Misc
        'feedback_request'            => '',
        'key_is_ok'                   => 0,
        'salt'                        => '',

        // Comment's test
        'count_checked_comments'      => 0,
        'count_bad_comments'          => 0,

        // User's test
        'count_checked_users'      => 0,
        'count_bad_users'          => 0,

        // Check URL exclusion by the new way - as URL
        'check_exclusion_as_url'  => true,
    );

    public $def_network_settings = array(

        // Access key
        'apikey'                                                        => '',
        'multisite__allow_custom_settings'                              => 1,
        'multisite__work_mode'                                          => 1,
        'multisite__hoster_api_key'                                     => '',

        // White label settings
        'multisite__white_label'                                        => 0,
        'multisite__white_label__plugin_name'                           => 'Anti-Spam by CleanTalk',
        'multisite__use_settings_template'                              => 0,
        'multisite__use_settings_template_apply_for_new'                => 0,
        'multisite__use_settings_template_apply_for_current'            => 0,
        'multisite__use_settings_template_apply_for_current_list_sites' => '',
    );

    public $def_network_data = array(
        'key_is_ok'   => 0,
        'moderate'    => 0,
        'valid'       => 0,
        'user_token'  => '',
        'service_id'  => 0,
        'auto_update' => 0,
    );

    public $def_remote_calls = array(

        //Common
        'close_renew_banner' => array('last_call' => 0, 'cooldown' => 0),
        'check_website'      => array('last_call' => 0, 'cooldown' => 0),
        'update_settings'    => array('last_call' => 0, 'cooldown' => 0),

        // Firewall
        'sfw_update'         => array('last_call' => 0, 'cooldown' => 0),
        'sfw_update__worker' => array('last_call' => 0, 'cooldown' => 0),
        'sfw_send_logs'      => array('last_call' => 0, 'cooldown' => 0),

        // Installation
        'install_plugin'     => array('last_call' => 0, 'cooldown' => 0),
        'activate_plugin'    => array('last_call' => 0, 'cooldown' => 0),
        'insert_auth_key'    => array('last_call' => 0, 'cooldown' => 0),
        'deactivate_plugin'  => array('last_call' => 0, 'cooldown' => 0),
        'uninstall_plugin'   => array('last_call' => 0, 'cooldown' => 0),

        // debug
        'debug'              => array('last_call' => 0, 'cooldown' => 0),
        'debug_sfw'          => array('last_call' => 0, 'cooldown' => 0),

        // cron update
        'cron_update_task'   => array('last_call' => 0),
    );

    public $def_stats = array(
        'sfw'            => array(
            'sending_logs__timestamp' => 0,
            'last_send_time'          => 0,
            'last_send_amount'        => 0,
            'last_update_time'        => 0,
            'last_update_way'         => '',
            'entries'                 => 0,
            'update_period'           => 14400,
        ),
        'last_sfw_block' => array(
            'time' => 0,
            'ip'   => '',
        ),
        'last_request'   => array(
            'time'   => 0,
            'server' => '',
        ),
        'requests'       => array(
            '0' => array(
                'amount'       => 1,
                'average_time' => 0,
            ),
        ),
        'plugin'         => array(
            'install__timestamp'             => 0,
            'activation__timestamp'          => 0,
            'activation_previous__timestamp' => 0,
            'activation__times'              => 0,
            'plugin_is_being_updated'        => 0,
        ),
        'cron'           => array(
            'last_start' => 0,
        ),
    );

    private $default_fw_stats = array(
        'firewall_updating'            => false,
        'updating_folder'              => '',
        'firewall_updating_id'         => null,
        'firewall_update_percent'      => 0,
        'firewall_updating_last_start' => 0,
        'expected_networks_count'      => 0,
        'expected_ua_count'            => 0,
        'update_mode'                  => 0,
    );

    public $errors;

    protected function setDefinitions()
    {
        global $wpdb;

        $db_prefix = is_multisite() && is_main_site() ? $wpdb->base_prefix : $wpdb->prefix;
        // Use tables from main site on wpms_mode=2
        $fw_db_prefix =
            is_multisite() && ! is_main_site() && $this->network_settings['multisite__work_mode'] == 2
                ? $wpdb->base_prefix
                : $db_prefix;

        if ( ! defined('APBCT_SEESION__LIVE_TIME')) {
            define('APBCT_SEESION__LIVE_TIME', 86400);
        }
        if ( ! defined('APBCT_SEESION__CHANCE_TO_CLEAN')) {
            define('APBCT_SEESION__CHANCE_TO_CLEAN', 100);
        }

        // Database constants
        if ( ! defined('APBCT_TBL_FIREWALL_DATA')) {
            // Table with firewall data.
            define('APBCT_TBL_FIREWALL_DATA', $fw_db_prefix . 'cleantalk_sfw');
        }
        if ( ! defined('APBCT_TBL_FIREWALL_LOG')) {
            // Table with firewall logs.
            define('APBCT_TBL_FIREWALL_LOG', $fw_db_prefix . 'cleantalk_sfw_logs');
        }
        if ( ! defined('APBCT_TBL_AC_LOG')) {
            // Table with firewall logs.
            define('APBCT_TBL_AC_LOG', $fw_db_prefix . 'cleantalk_ac_log');
        }
        if ( ! defined('APBCT_TBL_AC_UA_BL')) {
            // Table with User-Agents blacklist.
            define('APBCT_TBL_AC_UA_BL', $fw_db_prefix . 'cleantalk_ua_bl');
        }
        if ( ! defined('APBCT_TBL_SESSIONS')) {
            // Table with session data.
            define('APBCT_TBL_SESSIONS', $db_prefix . 'cleantalk_sessions');
        }
        if ( ! defined('APBCT_SPAMSCAN_LOGS')) {
            // Table with session data.
            define('APBCT_SPAMSCAN_LOGS', $db_prefix . 'cleantalk_spamscan_logs');
        }
        if ( ! defined('APBCT_SELECT_LIMIT')) {
            // Select limit for logs.
            define('APBCT_SELECT_LIMIT', 5000);
        }
        if ( ! defined('APBCT_WRITE_LIMIT')) {
            // Write limit for firewall data.
            define('APBCT_WRITE_LIMIT', 5000);
        }
        if ( ! defined('APBCT_SFW_SEND_LOGS_LIMIT')) {
            // Limit for firewall logs sending.
            define('APBCT_SFW_SEND_LOGS_LIMIT', 1000);
        }
    }

    protected function setOptions()
    {
        // Network settings
        $net_option                 = get_site_option($this->option_prefix . '_network_settings');
        $net_option                 = is_array($net_option)
            ? array_merge($this->def_network_settings, $net_option)
            : $this->def_network_settings;
        $this->network_settings     = new ArrayObject($net_option);

        // Network data
        $net_data             = get_site_option($this->option_prefix . '_network_data');
        $net_data             = is_array($net_data)
            ? array_merge($this->def_network_data, $net_data)
            : $this->def_network_data;
        $this->network_data   = new ArrayObject($net_data);

        foreach ($this->options as $option_name) {
            $option = get_option($this->option_prefix . '_' . $option_name);

            // Setting default options
            if ($this->option_prefix . '_' . $option_name === 'cleantalk_settings') {
                $option = is_array($option) ? array_merge($this->def_settings, $option) : $this->def_settings;
            }

            // Setting default data
            if ($this->option_prefix . '_' . $option_name === 'cleantalk_data') {
                $option = is_array($option) ? array_merge($this->def_data, $option) : $this->def_data;
                // Generate salt
                $option['salt'] = empty($option['salt'])
                    ? str_pad((string)rand(0, getrandmax()), 6, '0') . str_pad((string)rand(0, getrandmax()), 6, '0')
                    : $option['salt'];
            }

            // Setting default errors
            if ($this->option_prefix . '_' . $option_name === 'cleantalk_errors') {
                $option = $option ?: array();
            }

            // Default remote calls
            if ($this->option_prefix . '_' . $option_name === 'cleantalk_remote_calls') {
                $option = is_array($option) ? array_merge($this->def_remote_calls, $option) : $this->def_remote_calls;
            }

            // Default statistics
            if ($this->option_prefix . '_' . $option_name === 'cleantalk_stats') {
                $option = is_array($option) ? array_merge($this->def_stats, $option) : $this->def_stats;
            }

            // Default statistics
            if ($this->option_prefix . '_' . $option_name === 'cleantalk_fw_stats') {
                $option = is_array($option) ? array_merge($this->default_fw_stats, $option) : $this->default_fw_stats;
            }

            $this->$option_name = is_array($option) ? new ArrayObject($option) : $option;
        }
    }

    protected function init()
    {
        // Standalone or main site
        $this->api_key        = $this->settings['apikey'];
        $this->dashboard_link = 'https://cleantalk.org/my/' . ($this->user_token ? '?user_token=' . $this->user_token : '');
        $this->notice_show    = $this->data['notice_trial'] || $this->data['notice_renew'] || $this->data['notice_incompatibility'] || $this->isHaveErrors();

        // Set cookies type to the DATA
        if ( $this->settings['data__set_cookies'] ) {
            $this->data['cookies_type'] =
                ( $this->settings['data__set_cookies'] == 3 && $this->isServerCacheDetected() ) ||
                $this->settings['data__set_cookies'] == 2
                    ? 'alternative'
                    : 'native';
        } else {
            $this->data['cookies_type'] = 'none';
        }

        // Network with Mutual Access key
        if ( ! is_main_site() && $this->network_settings['multisite__work_mode'] == 2 ) {
            // Get stats from main blog
            switch_to_blog(get_main_site_id());
            $main_blog_stats = get_option($this->option_prefix . '_stats');
            restore_current_blog();
            $this->stats = $main_blog_stats;
            $this->api_key     = $this->network_settings['apikey'];
            $this->key_is_ok   = $this->network_data['key_is_ok'];
            $this->user_token  = $this->network_data['user_token'];
            $this->service_id  = $this->network_data['service_id'];
            $this->moderate    = $this->network_data['moderate'];
            $this->notice_show = false;
        }
    }

    /**
     * Get specified option from database
     *
     * @param string $option_name
     */
    protected function getOption($option_name)
    {
        $option = get_option($this->option_prefix . '_' . $option_name, null);

        $this->$option_name = is_array($option)
            ? new ArrayObject($option)
            : $option;

        return $option;
    }

    /**
     * Save option to database
     *
     * @param string $option_name
     * @param bool $use_prefix
     * @param bool $autoload Use autoload flag?
     */
    public function save($option_name, $use_prefix = true, $autoload = true)
    {
        $option_name_to_save = $use_prefix ? $this->option_prefix . '_' . $option_name : $option_name;
        $arr                 = array();
        foreach ($this->$option_name as $key => $value) {
            $arr[$key] = $value;
        }
        update_option($option_name_to_save, $arr, $autoload);
    }

    /**
     * Save PREFIX_setting to DB.
     */
    public function saveSettings()
    {
        return update_option($this->option_prefix . '_settings', (array)$this->settings);
    }

    /**
     * Save PREFIX_data to DB.
     */
    public function saveData()
    {
        update_option($this->option_prefix . '_data', (array)$this->data);
    }

    /**
     * Save PREFIX_error to DB.
     */
    public function saveErrors()
    {
        update_option($this->option_prefix . '_errors', (array)$this->errors);
    }

    /**
     * Save PREFIX_network_data to DB.
     */
    public function saveNetworkData()
    {
        update_site_option($this->option_prefix . '_network_data', (array)$this->network_data);
    }

    /**
     * Save PREFIX_network_data to DB.
     */
    public function saveNetworkSettings()
    {
        update_site_option($this->option_prefix . '_network_settings', (array)$this->network_settings);
    }

    /**
     * Unset and delete option from DB.
     *
     * @param string $option_name
     * @param bool $use_prefix
     */
    public function deleteOption($option_name, $use_prefix = false)
    {
        if ($this->__isset($option_name)) {
            $this->__unset($option_name);
            delete_option(($use_prefix ? $this->option_prefix . '_' : '') . $option_name);
        }
    }

    /**
     * Prepares an adds an error to the plugin's data
     *
     * @param string $type Error type/subtype
     * @param string|array $error Error
     * @param string $major_type Error major type
     * @param bool $set_time Do we need to set time of this error
     *
     * @returns null
     */
    public function errorAdd($type, $error, $major_type = null, $set_time = true)
    {
        $error = is_array($error)
            ? $error['error']
            : $error;

        // Exceptions
        if (($type == 'send_logs' && $error == 'NO_LOGS_TO_SEND') ||
            ($type == 'send_firewall_logs' && $error == 'NO_LOGS_TO_SEND') ||
            $error == 'LOG_FILE_NOT_EXISTS'
        ) {
            return;
        }

        $error = array(
            'error'      => $error,
            'error_time' => $set_time ? current_time('timestamp') : null,
        );

        //@ToDo Have to rebuild subtypes. These are too difficult to process now.
        if ( ! empty($major_type)) {
            if ( is_array($this->errors[$major_type][$type]) && count($this->errors[$major_type][$type]) >= 5 ) {
                array_shift($this->errors[$major_type][$type]);
            }
            $this->errors[$major_type][$type][] = $error;
        } else {
            // Remove subtype errors from processing.
            // No need to array_shift for these
            $sub_errors = array();
            if ( isset($this->errors[$type]) && is_array($this->errors[$type]) ) {
                foreach ( $this->errors[$type] as $key => $sub_error ) {
                    if ( is_string($key) ) {
                        $sub_errors[$key] = $sub_error;
                        unset($this->errors[$type][$key]);
                    }
                }
            }

            // Drop first element if errors array length is more than 5
            if ( isset($this->errors[$type]) && is_array($this->errors[$type]) && count($this->errors[$type]) >= 5 ) {
                array_shift($this->errors[$type]);
            }

            // Add the error to the errors array
            $this->errors[$type][] = $error;

            // Add the sub error to the errors array
            if ( count($sub_errors) ) {
                foreach ( $sub_errors as $sub_key => $sub_val ) {
                    $this->errors[$type][$sub_key] = $sub_val;
                }
            }
        }

        $this->saveErrors();
    }

    /**
     * Deletes an error from the plugin's data
     *
     * @param array|string $type Error type to delete
     * @param bool $save_flag Do we need to save data after error was deleted
     * @param string $major_type Error major type to delete
     *
     * @returns null
     */
    public function errorDelete($type, $save_flag = false, $major_type = null)
    {
        /** @noinspection DuplicatedCode */
        if (is_string($type)) {
            $type = explode(' ', $type);
        }

        foreach ($type as $val) {
            if ($major_type) {
                if (isset($this->errors[$major_type][$val])) {
                    unset($this->errors[$major_type][$val]);
                }
            } else {
                if (isset($this->errors[$val])) {
                    unset($this->errors[$val]);
                }
            }
        }

        // Save if flag is set and there are changes
        if ($save_flag) {
            $this->saveErrors();
        }
    }

    /**
     * Deletes all errors from the plugin's data
     *
     * @param bool $save_flag Do we need to save data after all errors was deleted
     *
     * @returns null
     */
    public function errorDeleteAll($save_flag = false)
    {
        $this->errors = array();
        if ($save_flag) {
            $this->saveErrors();
        }
    }

    /**
     * Set or deletes an error depends on the first bool parameter
     *
     * @param $add_error
     * @param $error
     * @param $type
     * @param null $major_type
     * @param bool $set_time
     * @param bool $save_flag
     */
    public function errorToggle($add_error, $type, $error, $major_type = null, $set_time = true, $save_flag = true)
    {
        if ( $add_error && ! $this->errorExists($type) ) {
            $this->errorAdd($type, $error, $major_type, $set_time);
        } elseif ( $this->errorExists($type) ) {
            $this->errorDelete($type, $save_flag, $major_type);
        }
    }

    public function errorExists($error_type)
    {
        return array_key_exists($error_type, (array)$this->errors);
    }

    /**
     * Checking if errors are in the setting, and they are not empty.
     *
     * @return bool
     */
    public function isHaveErrors()
    {
        if ( count((array)$this->errors) ) {
            foreach ( (array)$this->errors as $error ) {
                if ( is_array($error) ) {
                    return (bool)count($error);
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Magic.
     * Add new variables to storage[NEW_VARIABLE]
     * And duplicates it in storage['data'][NEW_VARIABLE]
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $this->storage[$name] = $value;
        if (isset($this->storage['data'][$name])) {
            $this->storage['data'][$name] = $value;
        }
    }

    /**
     * Dynamically get options in order:
     * 1. Trying to get it from the storage (options like data, settings, fw_stats and so on)
     * 2. Trying to get it from the storage['data']
     * 3. Trying to get it from the DB by name
     *
     * @param $name
     *
     * @return mixed
     */
    public function &__get($name)
    {
        // First check in storage
        if (isset($this->storage[$name])) {
            $option = $this->storage[$name];

            return $option;
            // Then in data
        } elseif (isset($this->storage['data'][$name])) {
            $this->$name = $this->storage['data'][$name];
            $option      = $this->storage['data'][$name];

            return $option;

            // Otherwise, try to get it from db settings table
            // it will be arrayObject || scalar || null
        } else {
            $option = $this->getOption($name);

            return $option;
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

    private function isServerCacheDetected()
    {
        $headers = Helper::httpGetHeaders();
        return
            isset($headers['X-Varnish']) || //Set alt cookies if varnish is installed
            defined('SiteGround_Optimizer\VERSION'); //Set alt cookies if sg optimizer is installed
    }
}
