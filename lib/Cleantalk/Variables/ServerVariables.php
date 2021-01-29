<?php

namespace Cleantalk\Variables;

/**
 * Class ServerVariables
 * Safety handler for ${_SOMETHING}
 *
 * @usage \Cleantalk\Variables\{SOMETHING}::get( $name );
 *
 * @package Cleantalk\Variables
 */
class ServerVariables{

	use \Cleantalk\Templates\Singleton;

	/**
	 * @var array Contains saved variables
	 */
	public $variables = [];

	/**
	 * Gets variable from ${_SOMETHING}
	 *
	 * @param string $name Variable name
	 *
	 * @return string
	 */
	public static function get( $name ){
		return static::getInstance()->get_variable( $name );
	}

	/**
	 * BLUEPRINT
	 * Gets given ${_SOMETHING} variable and seva it to memory
	 * @param $name
	 *
	 * @return mixed|string
	 */
	protected function get_variable( $name ){
		return true;
	}

	/**
	 * Save variable to $this->variables[]
	 *
	 * @param string $name
	 * @param string $value
	 */
	protected function remember_variable( $name, $value ){
		static::$instance->variables[$name] = $value;
	}

	/**
	 * Checks if variable contains given string
	 *
	 * @param string $var    Haystack to search in
	 * @param string $string Needle to search
	 *
	 * @return bool|int
	 */
	static function has_string( $var, $string ){
		return stripos( self::get( $var ), $string ) !== false;
	}

	/**
	 * Checks if variable equal to $param
	 *
	 * @param string $var   Variable to compare
	 * @param string $param Param to compare
	 *
	 * @return bool|int
	 */
	static function equal( $var, $param ){
		return self::get( $var ) == $param;
	}
}