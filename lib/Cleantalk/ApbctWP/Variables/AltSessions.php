<?php


namespace Cleantalk\ApbctWP\Variables;


use Cleantalk\ApbctWP\Helper;
use Cleantalk\Variables\Server;

class AltSessions {
    
    public static $sessions_already_cleaned = false;
    
    public static function getID(){
        $id = Helper::ip__get( 'real' )
            . Server::get( 'HTTP_USER_AGENT' )
            . Server::get( 'HTTP_ACCEPT_LANGUAGE' );
        return hash('sha256', $id);
    }
    
    public static function set($name, $value){
    
        self::cleanFromOld();
        
        // Bad incoming data
        if( ! $name || ! $value ){
            return;
        }
        
        global $wpdb;
        
        $session_id = self::getID();
        
        $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO '. APBCT_TBL_SESSIONS .'
				(id, name, value, last_update)
				VALUES (%s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE
				value = %s,
				last_update = %s',
                $session_id, $name, $value, date('Y-m-d H:i:s'), $value, date('Y-m-d H:i:s')
            )
        );
        
    }
    
    public static function get($name){
    
        self::cleanFromOld();
        
        // Bad incoming data
        if( ! $name ){
            return;
        }
        
        global $wpdb;
        
        $session_id = self::getID();
        $result = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT value 
				FROM `'. APBCT_TBL_SESSIONS .'`
				WHERE id = %s AND name = %s;',
                $session_id, $name
            ),
            ARRAY_A
        );
        
        $result = isset($result['value'])
            ? (strpos($result['value'], '{') === 0
                ? (array)json_decode($result['value'], true) // JSON
                : $result['value'])
            : false;
        
        return $result ?: null;
    }
    
    public static function cleanFromOld(){
        
        if( ! self::$sessions_already_cleaned && rand(0, 1000) < APBCT_SEESION__CHANCE_TO_CLEAN){
            
            global $wpdb;
            self::$sessions_already_cleaned = true;
            
            $wpdb->query(
                'DELETE
				FROM `'. APBCT_TBL_SESSIONS .'`
				WHERE last_update < NOW() - INTERVAL '. APBCT_SEESION__LIVE_TIME .' SECOND
				LIMIT 100000;'
            );
        }
    }
    
    public static function wipe( $full_clear = true ) {
        global $wpdb;
        return $wpdb->query(
            'TRUNCATE TABLE '. APBCT_TBL_SESSIONS .';'
        );
    }
    
}