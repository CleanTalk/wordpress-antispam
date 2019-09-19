<?php


namespace Cleantalk;


class Arr{
	
	private $array = array();
	private $found = array();
	
	public function __construct( &$array )
	{
		$this->array = is_array( $array )
			? $array
			: array();
		
		return $this;
	}
	
	/**
	 * Recursive
	 * Check if Array has keys given keys
	 * Save found keys in $this->found
	 *
	 * @param array $keys
	 * @param bool  $regexp
	 * @param array $array
	 *
	 * @return $this
	 */
	public function has_key( $keys = array(), $regexp = false, $array = array()  )
	{
		$array = $array
			? $array
			: $this->array;
		
		$keys = !is_array( $keys )
			? explode( ',', $keys )
			: $keys;
		
		if( empty( $array ) )
			return $this;
		
		foreach ( $array as $array_key => $value ){
			
			// Recursion
			if( is_array( $value ) ){
				 $this->found[$array_key] = $this->has_key( $keys, $regexp, $value );
				 
		    // Execution
			}else{
				foreach ( $keys as $key ){
					if( stripos( $array_key, $key ) !== false || ($regexp && preg_match( '/' . $key . '/', $array_key) !== false) ){
						$this->found[$array_key] = true;
					}
				}
			}
		}
		
		return $this;
	}
	
	/**
	 * Recursive
	 * Check if Array has keys given keys
	 * Save found keys in $this->found
	 *
	 * @param array $keys
	 * @param bool  $regexp
	 * @param array $array
	 *
	 * @return bool
	 */
	public function has_keys__boolean( $keys = array(), $regexp = false, $array = array() ){
		$this->has_key( func_get_args() );
		return (boolean) $this->found;
	}
	
	/**
	 * Recursive
	 * Check if Array has valuse given valuse
	 * Save found keys in $this->found
	 *
	 * @param array $values
	 * @param bool  $regexp
	 * @param array $array
	 *
	 * @return $this
	 */
	public function has_values( $values = array(), $regexp = false, $array = array()  )
	{
		$array = $array
			? $array
			: $this->array;
		
		$values = !is_array( $values )
			? explode( ',', $values )
			: $values;
		
		if( empty( $array ) )
			return $this;
		
		foreach ( $array as $key => $array_value ){
			
			// Recursion
			if( is_array( $array_value ) ){
				$this->found[$key] = $this->has_values( $values, $regexp, $array_value );
				
			// Execution
			}else{
				foreach ( $values as $value ){
					if( stripos( $array_value, $value ) !== false || ($regexp && preg_match( '/' . $value . '/', $array_value) !== false) ){
						$this->found[$key] = true;
					}
				}
			}
		}
		
		return $this;
	}
	
	/**
	 * Recursive
	 * Check if Array has valuse given valuse
	 * Save found keys in $this->found
	 *
	 * @param array $valuse
	 * @param bool  $regexp
	 * @param array $array
	 *
	 * @return bool
	 */
	public function has_values__bool( $valuse = array(), $regexp = false, $array = array() ){
		$this->has_key( func_get_args() );
		return (boolean) $this->found;
	}
	
	/**
	 * Recursive
	 * Delete elements from array with found keys ( $this->>found )
	 *
	 * @param array $array
	 * @param array $found
	 *
	 * @return array
	 */
	public function delete( $array = array(), $found =array() )
	{
		$array = $array
			? $array
			: $this->array;
		
		$found = $found
			? $found
			: $this->found;
		
		foreach($array as $key => &$value){
			
			// Recursion
			if( is_array( $value ) ){
				if(isset( $found[ $key ] ) ){
					$value = $this->delete( $array, $found );
				}
				
			// Execution
			}else{
				if(array_key_exists($key, $found)){
					unset( $array[ $key ] );
				}
			}
		}
		return $array;
	}
}