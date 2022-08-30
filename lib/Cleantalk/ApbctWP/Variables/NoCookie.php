<?php

namespace Cleantalk\ApbctWP\Variables;

use Cleantalk\ApbctWP\Helper;
use Cleantalk\Variables\Post;
use Cleantalk\Variables\Server;

class NoCookie
{
    public static $sessions_already_cleaned = false;

    public static $no_cookies_data = array();

    public static function getID()
    {
        $id = Helper::ipGet()
            . Server::get('HTTP_USER_AGENT')
            . Server::get('HTTP_ACCEPT_LANGUAGE');

        return hash('sha256', $id);
    }

    public static function set($name, $value, $save_to_db = false)
    {
        //self::cleanFromOld();

        // Bad incoming data
        if ( ! $name || ! $value) {
            return;
        }

        if (!$save_to_db) {
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

    public static function get($name)
    {

        // Bad incoming data
        if ( ! $name) {
            return false;
        }

        if ($name === 'apbct_visible_fields'){
            error_log('CTDEBUG: $no_cookies_data WHILE REQUESTING  apbct_visible_fields ' . var_export($no_cookies_data,true));
        }

        if (isset(self::$no_cookies_data[$name])){
            error_log('CTDEBUG: $name ' . var_export($name,true));
            error_log('CTDEBUG: $no_cookies_data[$name] ' . var_export(self::$no_cookies_data[$name],true));
            return self::$no_cookies_data[$name];
        }

        self::cleanFromOld();

        global $wpdb;

        $session_id = self::getID();
        $result     = $wpdb->get_row(
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


    public static function setDataFromHiddenField()
    {
        if ( Post::get('ct_no_cookie_hidden_field') ) {
            $data = Post::get('ct_no_cookie_hidden_field');
            $data = base64_decode($data);
            //need to handle errors
            $data = json_decode($data, true);
            self::$no_cookies_data = array_merge(self::$no_cookies_data,$data);
            error_log('CTDEBUG: setDataFromHiddenField ' . var_export(self::$no_cookies_data,true));
            return true;
        }

        return false;
    }

    public static function cleanFromOld()
    {
        global $wpdb;

        if ( ! self::$sessions_already_cleaned && rand(0, 1000) < APBCT_SEESION__CHANCE_TO_CLEAN) {
            self::$sessions_already_cleaned = true;

            $wpdb->query(
                'DELETE
				FROM `' . APBCT_TBL_NO_COOKIE . '`
				WHERE last_update < NOW() - INTERVAL ' . APBCT_SEESION__LIVE_TIME . ' SECOND
				LIMIT 100000;'
            );
        }
    }

    public static function wipe()
    {
        self::$no_cookies_data = array();
    }

    public static function preloadForScripts(){

        global $wpdb;

        $session_id = self::getID();
        $result     = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * 
				FROM `' . APBCT_TBL_NO_COOKIE . '`
				WHERE id = %s;',
                $session_id
            ),
            ARRAY_A
        );

        foreach ($result as $row=>$data){
            self::set($data['name'],$data['prev_value']);
        }

        return self::$no_cookies_data;
    }
}
