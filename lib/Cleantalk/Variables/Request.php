<?php

namespace Cleantalk\Variables;

/**
 * Class Request
 * Safety handler for $_REQUEST
 *
 * @usage \Cleantalk\Variables\Request::get( $name );
 *
 * @package Cleantalk\Variables
 */
class Request extends ServerVariables{
	
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
	 * Gets given $_REQUEST variable and seva it to memory
	 * @param $name
	 *
	 * @return mixed|string
	 */
	protected function get_variable( $name ){
		
		// Return from memory. From $this->variables
		if(isset(static::$instance->variables[$name]))
			return static::$instance->variables[$name];
		
		$value = isset( $_REQUEST[ $name ] ) ? $_REQUEST[ $name ]	: '';
		
		// Remember for thurther calls
		static::getInstance()->remember_variable( $name, $value );
		
		return $value;
	}
}