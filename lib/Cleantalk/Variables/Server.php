<?php

namespace Cleantalk\Variables;

/**
 * Class Server
 * Wrapper to safely get $_SERVER variables
 *
 * @usage \CleantalkSP\Variables\Server::get( $name );
 *
 * @package \CleantalkSP\Variables
 */
class Server extends ServerVariables {

	static $instance;

	/**
	 * Gets given $_SERVER variable and save it to memory
	 *
	 * @param string $name
	 *
	 * @return mixed|string
	 */
	protected function get_variable( $name ){

		// Return from memory. From $this->server
		if(isset(static::$instance->variables[$name]))
			return static::$instance->variables[$name];

		$name = strtoupper( $name );

		if( function_exists( 'filter_input' ) )
			$value = filter_input( INPUT_SERVER, $name );

		if( empty( $value ) )
			$value = isset( $_SERVER[ $name ] ) ? $_SERVER[ $name ]	: '';

		// Convert to upper case for REQUEST_METHOD
		if( in_array( $name, array( 'REQUEST_METHOD' ) ) )
			$value = strtoupper( $value );

		// Convert HTML chars for HTTP_USER_AGENT, HTTP_USER_AGENT, SERVER_NAME
		if( in_array( $name, array( 'HTTP_USER_AGENT', 'HTTP_USER_AGENT', 'SERVER_NAME' ) ) )
			$value = htmlspecialchars( $value );

		// Remember for thurther calls
		static::getInstance()->remember_variable( $name, $value );

		return $value;
	}

	/**
	 * Checks if $_SERVER['REQUEST_URI'] contains string
	 *
	 * @param string $needle
	 *
	 * @return bool
	 */
	public static function in_uri( $needle ){
		return self::has_string( 'REQUEST_URI', $needle );
	}

	public static function in_host( $needle ){
		return self::has_string( 'HTTP_HOST', $needle );
	}

	public static function get_domain(){
		preg_match( '@\.(\S+)\/?$@', self::get( 'HTTP_HOST' ), $matches );
		return isset( $matches[1] ) ? $matches[1] : false;
	}

	/**
	 * Checks if $_SERVER['REQUEST_URI'] contains string
	 *
	 * @param string $needle needle
	 *
	 * @return bool
	 */
	public static function in_referer( $needle ){
		return self::has_string( 'HTTP_REFERER', $needle );
	}

	/**
	 * Checks if $_SERVER['REQUEST_URI'] contains string
	 *
	 * @return bool
	 */
	public static function is_post(){
		return self::get( 'REQUEST_METHOD' ) === 'POST';
	}
}