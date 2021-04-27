<?php


namespace Cleantalk\ApbctWP\Variables;


class Cookie extends \Cleantalk\Variables\Cookie {
    
    public static function get( $name ){
    
        global $apbct;
        
        // Return from memory. From $this->variables
        if(isset(static::$instance->variables[$name]))
            return static::$instance->variables[$name];
        
        // Gettings by alternative way if enabled
        if( $apbct->settings['data__set_cookies__sessions'] ){
            $value = apbct_alt_session__get( $name );
            
        // The old way
        }else{
            
            if( function_exists( 'filter_input' ) )
                $value = filter_input( INPUT_COOKIE, $name );
        
            if( empty( $value ) )
                $value = isset( $_COOKIE[ $name ] ) ? $_COOKIE[ $name ]	: '';
            
        }
    
        // Remember for further calls
        static::getInstance()->remember_variable( $name, $value );
    
        return $value;
    }
    
    /**
     * Universal method to adding cookies
     * Wrapper for setcookie() Conisdering PHP version
     *
     * @see https://www.php.net/manual/ru/function.setcookie.php
     *
     * @param string $name     Cookie name
     * @param string $value    Cookie value
     * @param int    $expires  Expiration timestamp. 0 - expiration with session
     * @param string $path
     * @param null   $domain
     * @param bool   $secure
     * @param bool   $httponly
     * @param string $samesite
     *
     * @return void
     */
    public static function set ($name, $value = '', $expires = 0, $path = '', $domain = null, $secure = false, $httponly = false, $samesite = 'Lax' ) {
        
        // For PHP 7.3+ and above
        if( version_compare( phpversion(), '7.3.0', '>=' ) ){
            
            $params = array(
                'expires'  => $expires,
                'path'     => $path,
                'domain'   => $domain,
                'secure'   => $secure,
                'httponly' => $httponly,
            );
            
            if($samesite)
                $params['samesite'] = $samesite;
            
            setcookie( $name, $value, $params );
            
            // For PHP 5.6 - 7.2
        }else {
            setcookie( $name, $value, $expires, $path, $domain, $secure, $httponly );
        }
        
    }
    
}