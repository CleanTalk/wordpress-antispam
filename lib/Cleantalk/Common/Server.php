<?php

namespace Cleantalk\Common;

/**
 * Class Server
 * Wrapper to safely get $_SERVER variables
 *
 * @package Cleantalk\Common
 */
class Server extends ServerVariables{
	
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
	 * Gets given $_SERVER variable and seva it to memory
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
		static::getInstance()->remebmer_variable( $name, $value );
		
		return $value;
	}
	
	/**
	 * Checks if $_SERVER['REQUEST_URI'] contains string
	 *
	 * @param string $string needle
	 *
	 * @return bool|int
	 */
	public function in_uri( $string ){
		return self::has_string( 'REQUEST_URI', $string );
	}
	
	/**
	 * Checks if $_SERVER['REQUEST_URI'] contains string
	 *
	 * @param string $string needle
	 *
	 * @return bool|int
	 */
	public function in_referer( $string ){
		return self::has_string( 'HTTP_REFERER', $string );
	}
}