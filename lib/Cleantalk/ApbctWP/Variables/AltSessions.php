<?php

namespace Cleantalk\ApbctWP\Variables;

use Cleantalk\ApbctWP\Helper;

class AltSessions
{
    public static function getID()
    {
        $id = Helper::ipGet()
              . Server::getString('HTTP_USER_AGENT')
              . Server::getString('HTTP_ACCEPT_LANGUAGE');

        return substr(hash('sha256', $id), -16);
    }

    /**
     * @param $name
     * @param $value
     *
     * @return bool
     */
    public static function set($name, $value)
    {
        if ( is_int($value) ) {
            $value = (string)$value;
        }

        // Bad incoming data
        if ( ! $name || (empty($value) && $value !== false && $value !== "0") ) {
            return false;
        }

        //fix if value is strictly false
        $value = $value === false ? 0 : $value;

        if ( is_array($value) ) {
            $value = json_encode($value);
            $value = $value === false ? null : $value;
        }

        $session_value = self::getValues();

        $session_value[$name] = $value;

        return self::setValues($session_value);
    }

    public static function setValues($cookies_array)
    {
        global $wpdb;

        $data = array(
            'id' => self::getID(),
            'value' => serialize($cookies_array),
        );

        return $wpdb->query($wpdb->prepare(
            "INSERT INTO " . APBCT_TBL_SESSIONS . " (id, value, last_update)
            VALUES (%s, %s, %s) 
            ON DUPLICATE KEY UPDATE 
                value = VALUES(value),
                last_update = %s",
            $data['id'],
            $data['value'],
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ));
    }

    public static function getValues()
    {
        global $wpdb;

        $session_value = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT value FROM ' . APBCT_TBL_SESSIONS . ' WHERE id = %s',
                self::getID()
            )
        );

        if ( $session_value ) {
            try {
                $session_value = @unserialize($session_value);
                if ($session_value === false) {
                    $session_value = array();
                }
            } catch (\Exception $e) {
                $session_value = array();
            }
        }

        if ( ! is_array($session_value) ) {
            $session_value = array();
        }

        return $session_value;
    }

    /**
     * @param $request
     *
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function setFromRemote($request = null)
    {
        if ( !$request || !empty(Post::getString('cookies'))) {
            $cookies_to_set = Post::getString('cookies');
        } else {
            $cookies_to_set = $request->get_param('cookies');
        }

        //clear from double slashes
        $cookies_to_set = str_replace('\\', '', $cookies_to_set);

        //handle php8+ JSON throws
        try {
            $cookies_array = json_decode($cookies_to_set, true);
        } catch ( \Exception $e ) {
            $cookies_array = array();
            unset($e);
            wp_send_json(array(
                'success' => false,
                'error' => 'AltSessions: Internal JSON error:' . json_last_error_msg()));
        }

        //other versions json errors if json_decode returns null
        if ( is_null($cookies_array) ) {
            $cookies_array = array();
            wp_send_json(array(
                'success' => false,
                'error' => 'AltSessions: Internal JSON error:' . json_last_error_msg()));
        }

        if ( array_key_exists('apbct_force_alt_cookies', $cookies_array) ) {
            Cookie::$force_alt_cookies_global = true;
        }

        $old_value = self::getValues();

        $cookies_array = array_merge($old_value, $cookies_array);

        self::setValues($cookies_array);

        wp_send_json(array('success' => true));
    }

    public static function get($name)
    {
        if ( ! $name) {
            return false;
        }

        $session_value = self::getValues();

        return isset($session_value[$name]) ? $session_value[$name] : '';
    }

    /**
     * @param $request
     *
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function getFromRemote($request = null)
    {
        $value = Cookie::getString(
            $request
                ? $request->get_param('cookies')
                : Post::getString('name')
        );

        wp_send_json(array('success' => true, 'value' => $value));
    }

    public static function cleanFromOld()
    {
        global $wpdb;

        $results = [];

        // Get all cleantalk_sessions tables across all sites
        $tables = $wpdb->get_col(
            "SHOW TABLES LIKE '{$wpdb->base_prefix}%cleantalk_sessions'"
        );

        foreach ($tables as $table) {
            $results[$table] = $wpdb->query(
                'DELETE FROM `' . $table . '`
                WHERE last_update < NOW() - INTERVAL ' . APBCT_SEESION__LIVE_TIME . ' SECOND
                OR last_update IS NULL
                LIMIT 100000;'
            );
        }

        return $results;
    }

    public static function checkHasUndeletedOldSessions()
    {
        global $wpdb;

        $tables = $wpdb->get_col(
            "SHOW TABLES LIKE '{$wpdb->base_prefix}%cleantalk_sessions'"
        );

        foreach ($tables as $table) {
            $query = $wpdb->prepare(
                'SELECT COUNT(id) FROM `' . $table . '` WHERE last_update < NOW() - INTERVAL %d SECOND;',
                APBCT_SEESION__LIVE_TIME
            );

            if ((int)$wpdb->get_var($query) > 0) {
                return true;
            }
        }

        return false;
    }

    public static function wipe()
    {
        global $wpdb;

        return $wpdb->query(
            'TRUNCATE TABLE ' . APBCT_TBL_SESSIONS . ';'
        );
    }

    /**
     * Register hooks for AJAX service.
     *
     * @param $ajax_service
     *
     * @return void
     */
    public static function registerHooks($ajax_service)
    {
        // AJAX handler for saving alt cookies
        $ajax_service->addPublicAction(
            'apbct_alt_session__save__AJAX',
            [self::class, 'setFromRemote']
        );
    }
}
