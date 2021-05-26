<?php

namespace Cleantalk\Variables;

/**
 * Class Cookie
 * Safety handler for $_COOKIE
 *
 * @usage \Cleantalk\Variables\Cookie::get( $name );
 *
 * @package Cleantalk\Variables
 */
class Cookie extends ServerVariables{
	
	static $instance;
	
	/**
	 * Constructor
	 * @return $this
	 */
	public static function getInstance(){
		if (!isset(static::$instance)) {
			static::$instance = new static;
			static::$instance->init();
		}
		return static::$instance;
	}
	
	/**
	 * Gets given $_COOKIE variable and seva it to memory
	 * @param $name
	 *
	 * @return mixed|string
	 */
	protected function get_variable( $name ){
		
		// Return from memory. From $this->variables
		if(isset(static::$instance->variables[$name]))
			return static::$instance->variables[$name];
		
		if( function_exists( 'filter_input' ) )
			$value = filter_input( INPUT_COOKIE, $name );
		
		if( empty( $value ) )
			$value = isset( $_COOKIE[ $name ] ) ? $_COOKIE[ $name ]	: '';
		
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
	public static function set ( $name, $value = '', $expires = 0, $path = '', $domain = null, $secure = null, $httponly = false, $samesite = 'Lax' ) {
        
        $secure = ! is_null( $secure ) ? $secure : Server::get('HTTPS') !== 'off' || Server::get('SERVER_PORT') == 443;

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