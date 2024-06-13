<?php

namespace Cleantalk\ApbctWP\Variables;

use Cleantalk\ApbctWP\Helper;

class AltSessions
{
    public static function getID()
    {
        $id = Helper::ipGet()
              . Server::get('HTTP_USER_AGENT')
              . Server::get('HTTP_ACCEPT_LANGUAGE');

        return hash('sha256', $id);
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

        global $wpdb;

        $session_id = self::getID();

        if ( is_array($value) ) {
            $value = json_encode($value);
            $value = $value === false ? null : $value;
        }

        return (bool) $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO ' . APBCT_TBL_SESSIONS . '
				(id, name, value, last_update)
				VALUES (%s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE
				value = %s,
				last_update = %s',
                $session_id,
                $name,
                $value,
                date('Y-m-d H:i:s'),
                $value,
                date('Y-m-d H:i:s')
            )
        );
    }

    public static function setFromRemote($request = null)
    {
        if ( !$request || Post::get('cookies')) {
            $cookies_to_set = Post::get('cookies');
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

        foreach ( $cookies_array as $cookie_to_set => $value ) {
            Cookie::set($cookie_to_set, $value);
        }

        wp_send_json(array('success' => true));
    }

    public static function get($name)
    {
        // Bad incoming data
        if ( ! $name) {
            return false;
        }

        global $wpdb;

        $session_id = self::getID();
        $result     = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT value 
				FROM `' . APBCT_TBL_SESSIONS . '`
				WHERE id = %s AND name = %s;',
                $session_id,
                $name
            ),
            ARRAY_A
        );

        return isset($result['value']) ? $result['value'] : '';
    }

    /**
     * @param $request
     *
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function getFromRemote($request = null)
    {
        $value = Cookie::get(
            $request
                ? $request->get_param('cookies')
                : Post::get('name')
        );

        wp_send_json(array('success' => true, 'value' => $value));
    }

    public static function cleanFromOld()
    {
        global $wpdb;

        $wpdb->query(
            'DELETE
				FROM `' . APBCT_TBL_SESSIONS . '`
				WHERE last_update < NOW() - INTERVAL ' . APBCT_SEESION__LIVE_TIME . ' SECOND
				LIMIT 100000;'
        );
    }

    public static function wipe()
    {
        global $wpdb;

        return $wpdb->query(
            'TRUNCATE TABLE ' . APBCT_TBL_SESSIONS . ';'
        );
    }
}
