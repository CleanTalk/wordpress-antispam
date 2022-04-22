<?php

namespace Cleantalk\ApbctWP\Variables;

use Cleantalk\ApbctWP\Helper;
use Cleantalk\Variables\Post;
use Cleantalk\Variables\Server;

class AltSessions
{
    public static $sessions_already_cleaned = false;

    public static function getID()
    {
        $id = Helper::ipGet()
              . Server::get('HTTP_USER_AGENT')
              . Server::get('HTTP_ACCEPT_LANGUAGE');

        return hash('sha256', $id);
    }

    public static function set($name, $value)
    {
        self::cleanFromOld();

        // Bad incoming data
        if ( ! $name || ! $value) {
            return;
        }

        global $wpdb;

        $session_id = self::getID();

        $wpdb->query(
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
        if ( ! $request) {
            $cookies_to_set = (array)Post::get('cookies');
        } else {
            $cookies_to_set = $request->get_param('cookies');
        }

        foreach ($cookies_to_set as $cookie_to_set) {
            Cookie::set($cookie_to_set[0], $cookie_to_set[1]);
        }

        wp_send_json(array('success' => true));
    }

    public static function get($name)
    {
        self::cleanFromOld();

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
        global $wpdb;

        return $wpdb->query(
            'TRUNCATE TABLE ' . APBCT_TBL_SESSIONS . ';'
        );
    }
}
