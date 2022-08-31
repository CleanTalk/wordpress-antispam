<?php

namespace Cleantalk\ApbctWP\Variables;

use Cleantalk\ApbctWP\Helper;
use Cleantalk\Variables\Post;
use Cleantalk\Variables\Server;

class NoCookie
{
    public static $sessions_already_cleaned = false;

    public static $no_cookies_data = array();

    /**
     * Get the session ID for saving data to the DB.
     * @return false|string
     */
    public static function getID()
    {
        $id = Helper::ipGet()
            . Server::get('HTTP_USER_AGENT')
            . Server::get('HTTP_ACCEPT_LANGUAGE');

        return hash('sha256', $id);
    }

    /**
     * Set value of NoCookie data. If $save_to_db flag is set then save it to NoCookie database,
     * else just save to the static prop $no_cookies_data.
     * @param $name
     * @param $value
     * @param false $save_to_db
     */
    public static function set($name, $value, $save_to_db = false)
    {

        if ( is_int($value) ) {
            $value = (string)$value;
        }

        // Bad incoming data
        if ( !$name || (empty($value) && $value !== "0") ) {
            return;
        }

        if ( !$save_to_db ) {
            self::$no_cookies_data[$name] = $value;
            return;
        }

        self::cleanFromOld();

        global $wpdb;

        $session_id = self::getID();

        $previous_value = self::get($name);

        $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO ' . APBCT_TBL_NO_COOKIE . '
				(id, name, value, last_update, prev_value)
				VALUES (%s, %s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE
				value = %s,
				last_update = %s,
				prev_value =%s',
                $session_id,
                $name,
                $value,
                date('Y-m-d H:i:s'),
                $previous_value,
                $value,
                date('Y-m-d H:i:s'),
                $previous_value
            )
        );
    }

    /**
     * Get NoCookie data from static prop $no_cookies_data,
     * if there is no such $name found try to search this in the DB.
     * @param $name
     * @return false|mixed|string
     */
    public static function get($name)
    {

        // Bad incoming data
        if ( !$name ) {
            return false;
        }

        if ( isset(self::$no_cookies_data[$name]) ) {
            return self::$no_cookies_data[$name];
        }

        self::cleanFromOld();

        global $wpdb;

        $session_id = self::getID();
        $result = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT value 
				FROM `' . APBCT_TBL_NO_COOKIE . '`
				WHERE id = %s AND name = %s;',
                $session_id,
                $name
            ),
            ARRAY_A
        );

        return isset($result['value']) ? $result['value'] : '';
    }


    /**
     * Check POST for data transferred via ct_no_cookie_hidden_field, handle them then
     * @return bool
     */
    public static function setDataFromHiddenField()
    {
        if ( Post::get('ct_no_cookie_hidden_field') ) {
            $data = Post::get('ct_no_cookie_hidden_field');
            $data = base64_decode($data);
            if ( $data ) {
                $data = json_decode($data, true);
                if ( !empty($data) && is_array($data) ){
                    self::$no_cookies_data = array_merge(self::$no_cookies_data, $data);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Clean NoCookie table if random of APBCT_SEESION__CHANCE_TO_CLEAN fired
     */
    public static function cleanFromOld()
    {
        global $wpdb;

        if ( !self::$sessions_already_cleaned && rand(0, 1000) < APBCT_SEESION__CHANCE_TO_CLEAN ) {
            self::$sessions_already_cleaned = true;

            $wpdb->query(
                'DELETE
				FROM `' . APBCT_TBL_NO_COOKIE . '`
				WHERE last_update < NOW() - INTERVAL ' . APBCT_SEESION__LIVE_TIME . ' SECOND
				LIMIT 100000;'
            );
        }
    }

    /**
     * Wipe NoCookie data
     * @return bool|int|\mysqli_result|resource|null
     */
    public static function wipe()
    {
        //clear nodb data
        self::$no_cookies_data = array();

        global $wpdb;
        //clear table
        return $wpdb->query(
            'TRUNCATE TABLE ' . APBCT_TBL_NO_COOKIE . ';'
        );
    }

    /**
     * Get NoCookie data from table to localize it in JS scripts
     * @return array
     */
    public static function preloadForScripts()
    {

        global $wpdb;

        $session_id = self::getID();
        $result = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * 
				FROM `' . APBCT_TBL_NO_COOKIE . '`
				WHERE id = %s;',
                $session_id
            ),
            ARRAY_A
        );

        //keep previous value to use them before NoCookies handler loaded
        foreach ( array_values($result) as $no_cookie_db_value ) {
            self::set($no_cookie_db_value['name'], $no_cookie_db_value['prev_value']);
        }

        return self::$no_cookies_data;
    }
}
