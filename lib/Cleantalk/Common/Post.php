<?php


namespace Cleantalk\Common;


class Post extends ServerVariables{
	
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
	 * Gets given $_GET variable and seva it to memory
	 * @param $name
	 *
	 * @return mixed|string
	 */
	protected function get_variable( $name ){
		
		// Return from memory. From $this->server
		if(isset(static::$instance->variable[$name]))
			return static::$instance->variable[$name];
		
		if( function_exists( 'filter_input' ) )
			$value = filter_input( INPUT_POST, $name );
		
		if( empty( $value ) )
			$value = isset( $_POST[ $name ] ) ? $_POST[ $name ]	: '';
		
		// Remember for thurther calls
		static::getInstance()->remebmer_variable( $name, $value );
		
		return $value;
	}
}