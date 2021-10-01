<?php

namespace Cleantalk\ApbctWP;

use ArrayObject;

/**
 * CleanTalk Antispam State class
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
     * @var int
     */
    public $use_rest_api = 0;

    /**
     * @var array
     */
    public $storage = array();

    protected function setDefinitions()
    {
        global $wpdb;

        $db_prefix = is_multisite() && is_main_site() ? $wpdb->base_prefix : $wpdb->prefix;

        if ( ! defined('APBCT_SEESION__LIVE_TIME')) {
            define('APBCT_SEESION__LIVE_TIME', 86400);
        }
        if ( ! defined('APBCT_SEESION__CHANCE_TO_CLEAN')) {
            define('APBCT_SEESION__CHANCE_TO_CLEAN', 100);
        }

        // Database constants
        if ( ! defined('APBCT_TBL_FIREWALL_DATA')) {
            // Table with firewall data.
            define('APBCT_TBL_FIREWALL_DATA', $db_prefix . 'cleantalk_sfw');
        }
        if ( ! defined('APBCT_TBL_FIREWALL_LOG')) {
            // Table with firewall logs.
            define('APBCT_TBL_FIREWALL_LOG', $db_prefix . 'cleantalk_sfw_logs');
        }
        if ( ! defined('APBCT_TBL_AC_LOG')) {
            // Table with firewall logs.
            define('APBCT_TBL_AC_LOG', $db_prefix . 'cleantalk_ac_log');
        }
        if ( ! defined('APBCT_TBL_AC_UA_BL')) {
            // Table with User-Agents blacklist.
            define('APBCT_TBL_AC_UA_BL', $db_prefix . 'cleantalk_ua_bl');
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
        foreach ($this->options as $option_obj) {

            $reflect_option_obj = new \ReflectionClass($option_obj);
            $option_name = $reflect_option_obj->getShortName();
            $option_name = mb_strtolower($option_name);

            if ( strpos( $option_name, 'network' ) !== false ) {
                $option = get_site_option($this->option_prefix . '_' . $option_name);
            } else {
                $option = get_option($this->option_prefix . '_' . $option_name);
            }

            $option = is_array($option)&& isset($option_obj->defaults)
                ? array_merge($option_obj->defaults, $option)
                : $option_obj->defaults;

            if ($this->option_prefix . '_' . $option_name === 'cleantalk_data') {
                // Generate salt
                $option['salt'] = empty($option['salt'])
                    ? str_pad((string)rand(0, getrandmax()), 6, '0') . str_pad((string)rand(0, getrandmax()), 6, '0')
                    : $option['salt'];
            }

            $this->$option_name = is_array( $option ) ? new \ArrayObject( $option ) : $option;
        }
    }

    protected function init()
    {
        //Set alt cookies if sg optimizer is installed
        $this->settings['data__set_cookies'] = defined('SiteGround_Optimizer\VERSION') ? 2 : 1;

        // Standalone or main site
        $this->api_key        = $this->settings['apikey'];
        $this->dashboard_link = 'https://cleantalk.org/my/' . ($this->user_token ? '?user_token=' . $this->user_token : '');
        $this->notice_show    = $this->data['notice_trial'] || $this->data['notice_renew'] || $this->isHaveErrors();

        // Network with Mutual key
        if ( ! is_main_site() && $this->network_settings['multisite__work_mode'] == 2 ) {
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

        if ( ! empty($major_type)) {
            $this->errors[$major_type][$type] = $error;
        } else {
            $this->errors[$type] = $error;
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
    public function __get($name)
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
}
