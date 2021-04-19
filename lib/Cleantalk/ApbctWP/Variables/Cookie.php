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
    
}