<?php

namespace Cleantalk\ApbctWP\Variables;

use Cleantalk\ApbctWP\Helper;
use Cleantalk\Common\TT;
use WP_REST_Request;

class AltSessions
{
    /**
     * @var string[]
     */
    private static $allowed_alt_cookies = [
        'apbct_email_encoder_passed' => 'hash',
        'apbct_bot_detector_exist' => 'bool',
        'ct_ps_timestamp' => 'int',
        'ct_fkp_timestamp' => 'int',
        'ct_timezone' => 'int',
        'ct_screen_info' => 'json',
        'apbct_headless' => 'bool',
        'apbct_visible_fields' => 'json',
        'apbct_pixel_url' => 'url',
        'ct_checked_emails' => 'json',
        'ct_checked_emails_exist' => 'json',
        'ct_checkjs' => 'string',
        'ct_bot_detector_event_token' => 'hash',
        'ct_has_input_focused' => 'bool',
        'ct_has_key_up' => 'bool',
        'ct_has_scrolled' => 'bool',
        'ct_mouse_moved' => 'bool',
        'wordpress_apbct_antibot' => 'hash',
        'apbct_anticrawler_passed' => 'int',
        'apbct_antiflood_passed' => 'int',
        'ct_sfw_pass_key' => 'string',
        'ct_sfw_passed' => 'int',
        'ct_gathering_loaded' => 'bool',
    ];

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

    private static function setValues($cookies_array)
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

    private static function getValues()
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
     * Sets session values from a remote request (ajax request).
     *
     * This method processes incoming data from a remote request or POST data,
     * validates the cookies against allowed types, and updates the session values.
     * It also handles JSON decoding errors and ensures proper data sanitization.
     *
     * @param WP_REST_Request|null $request
     *
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function setFromRemote($request = null)
    {
        if ( $request instanceof WP_REST_Request ) {
            $nonce = TT::toString($request->get_header('x_wp_nonce'));
            $action = 'wp_rest';
            $cookies_to_set = $request->get_param('cookies');
        } else {
            $nonce = TT::toString(Post::getString('_ajax_nonce'));
            $action = 'ct_secret_stuff';
            $cookies_to_set = Post::getString('cookies');
        }

        if ( ! $cookies_to_set ) {
            wp_send_json(
                array(
                    'success' => false,
                    'error' => 'AltSessions: No cookies data provided.'
                )
            );
            die(); // Need to prevent psalm further processing checking
        }

        if ( ! wp_verify_nonce($nonce, $action) ) {
            wp_send_json(
                array(
                    'success' => false,
                    'error' => 'AltSessions: Nonce verification failed. Please reload the page and try again.'
                )
            );
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

        // if cookies_array is array of arrays, then convert it to object
        if (is_array($cookies_array) &&
            isset($cookies_array[0]) &&
            is_array($cookies_array[0])
        ) {
            $prepared_cookies_array = array();
            foreach ($cookies_array as $cookie) {
                if (is_array($cookie) && isset($cookie[0]) && isset($cookie[1])) {
                    $prepared_cookies_array[$cookie[0]] = $cookie[1];
                }
            }
            $cookies_array = $prepared_cookies_array;
        }

        //other versions json errors if json_decode returns null
        if ( is_null($cookies_array) ) {
            $cookies_array = array();
            wp_send_json(array(
                'success' => false,
                'error' => 'AltSessions: Internal JSON error: $cookies_array is null.'));
        }

        // Incoming data validation against allowed alt cookies
        foreach ($cookies_array as $name => $value) {
            if ( ! array_key_exists($name, self::$allowed_alt_cookies) ) {
                unset($cookies_array[$name]);
                continue;
            }

            // Validate value type
            switch (self::$allowed_alt_cookies[$name]) {
                case 'int':
                    $cookies_array[$name] = (int)$value;
                    break;
                case 'bool':
                    $cookies_array[$name] = (bool)$value;
                    break;
                case 'string':
                    $cookies_array[$name] = (string)$value;
                    break;
                case 'json':
                    if ( ! is_string($value) || json_decode($value) === null ) {
                        unset($cookies_array[$name]);
                    }
                    break;
                case 'url':
                    if ( ! filter_var($value, FILTER_VALIDATE_URL) ) {
                        unset($cookies_array[$name]);
                    }
                    break;
                case 'hash':
                    if ( ! preg_match('/^[a-f0-9]{32,128}$/', $value) ) {
                        unset($cookies_array[$name]);
                    }
                    break;
                default:
                    // If the type is not recognized, remove the cookie
                    unset($cookies_array[$name]);
            }
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
