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

    public static function set($name, $value)
    {
        //self::cleanFromOld();

        // Bad incoming data
        if ( ! $name || ! $value) {
            return;
        }

//        global $wpdb;
//
//        $session_id = self::getID();
//
//        $wpdb->query(
//            $wpdb->prepare(
//                'INSERT INTO ' . APBCT_TBL_SESSIONS . '
//				(id, name, value, last_update)
//				VALUES (%s, %s, %s, %s)
//			ON DUPLICATE KEY UPDATE
//				value = %s,
//				last_update = %s',
//                $session_id,
//                $name,
//                $value,
//                date('Y-m-d H:i:s'),
//                $value,
//                date('Y-m-d H:i:s')
//            )
//        );

        self::$no_cookies_data[$name] = $value;
    }

    public static function get($name)
    {
        //self::cleanFromOld();

        // Bad incoming data
        if ( ! $name) {
            return false;
        }

        return isset(self::$no_cookies_data[$name]) ? self::$no_cookies_data[$name] : '';
    }


    public static function setDataFromHiddenField()
    {
        if ( Post::get('ct_no_cookie_hidden_field') ) {
            $data = Post::get('ct_no_cookie_hidden_field');
            $data = base64_decode($data);
            //need to handle errors
            $data = json_decode($data, true);
            error_log('CTDEBUG: before setDataFromHiddenField ' . var_export(self::$no_cookies_data,true));
            self::$no_cookies_data = array_merge(self::$no_cookies_data,$data);
            return true;
        }

//        if ( !empty($data) ) {
//            //implement other keys below
//            $dictionary = array(
//                'mouse_cursor_positions' => 'ct_pointer_data',
//                'mouse_moved' => 'ct_mouse_moved',
//                'email_check' => 'ct_checked_emails',
//                'has_scrolled' => 'ct_has_scrolled',
//                'pixel_url' => 'apbct_pixel_url',
//                'page_set_timestamp' => 'ct_ps_timestamp',
//                // 'pixel_url' => 'ct_cookies_type',
//                'emulations_headless_mode' => 'apbct_headless',
//                'js_timezone' => 'ct_timezone',
//                'screen_info' => 'ct_screen_info',
//                'key_press_timestamp' => 'ct_fkp_timestamp',
//                'checkjs_data_cookies' => 'ct_checkjs',
//
//            );
//
//            foreach ( $dictionary as $data_key => $nc_key ) {
//                    $data[$data_key] = $data[$nc_key];
//                    unset ($data[$nc_key]);
//            }
//            self::$no_cookies_data = $data;
//        }

        return false;
    }

    public static function cleanFromOld()
    {
        global $wpdb;

        if ( ! self::$sessions_already_cleaned && rand(0, 1000) < APBCT_SEESION__CHANCE_TO_CLEAN) {
            self::$sessions_already_cleaned = true;

            $wpdb->query(
                'DELETE
				FROM `' . APBCT_TBL_SESSIONS . '`
				WHERE last_update < NOW() - INTERVAL ' . APBCT_SEESION__LIVE_TIME . ' SECOND
				LIMIT 100000;'
            );
        }
    }

    public static function wipe()
    {
        self::$no_cookies_data = array();
    }

    public static function prepareToLoad(){
        return self::$no_cookies_data;
    }
}
