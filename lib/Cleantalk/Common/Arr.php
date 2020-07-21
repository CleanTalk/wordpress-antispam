<?php


namespace Cleantalk\Common;

/**
 * Class Arr
 * Fluent Interface
 * Allows to work with multi dimensional arrays
 *
 * @package Cleantalk
 */
class Arr
{
	
	private $array  = array();
	private $found  = array();
	private $result = array();
	
	public function __construct( $array )
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
	 * @param array|string $keys
	 * @param bool         $regexp
	 * @param array        $array
	 *
	 * @return Arr
	 */
	public function get_keys( $keys = array(), $regexp = false, $array = array() )
	{
		$array = $array            ? $array : $this->array;
		$keys  = is_array( $keys ) ? $keys  : explode( ',', $keys );
		
		if( empty( $array ) || empty( $keys ) )
			return $this;
		
		$this->found = $keys === array('all')
			? $this->array
			: $this->search(
				'key',
				$array,
				$keys,
				$regexp
			);
		
		return $this;
	}
	
	/**
	 * Recursive
	 * Check if Array has valuse given valuse
	 * Save found keys in $this->found
	 *
	 * @param array|string $values
	 * @param bool         $regexp
	 * @param array        $array
	 *
	 * @return $this
	 */
	public function get_values( $values = array(), $regexp = false, $array = array()  )
	{
		$array = $array              ? $array   : $this->array;
		$keys  = is_array( $values ) ? $values  : explode( ',', $values );
		
		if( empty( $array ) || empty( $values ) )
			return $this;
		
		$this->found = $values === array('all')
			? $this->array
			: $this->search(
				'value',
				$array,
				$keys,
				$regexp
			);
		
		return $this;
	}
	
	public function get_array( $searched = array(), $regexp = false, $array = array() ){
		
		$array = $array ? $array   : $this->array;
		
		
		if( empty( $array ) || empty( $searched ) )
			return $this;
		
		$this->found = $searched === array('all')
			? $this->array
			: $this->search(
				'array',
				$array,
				$searched,
				$regexp
			);
		
		$this->found = $this->found === $searched ? $this->found : array();
		
		return $this;
	}
	
	/**
	 * Recursive
	 * Check if array contains wanted data type
	 *
	 * @param string $type
	 * @param array  $array
	 * @param array  $found
	 *
	 * @return bool|void
	 */
	public function is( $type, $array = array(), $found = array() )
	{
		$array = $array ? $array : $this->array;
		$found = $found ? $found : $this->found;
		
		foreach ( $array as $key => $value ){
			
			if( array_key_exists( $key, $found ) ){
				if( is_array( $found[ $key ] ) ){
					if( ! $this->is( $type, $value, $found[ $key ] ) ){
						return false;
					}
				}else{
					switch ( $type ){
						case 'regexp':
							$value = preg_match( '/\/.*\//', $value ) === 1 ? $value : '/' . $value . '/';
							if( @preg_match( $value, null ) === false ){
								return false;
							}
							break;
					}
				}
			}
			
		}
		
		return true;
	}
	
	/**
	 * @param string $type
	 * @param array  $array
	 * @param array  $searched
	 * @param bool   $regexp
	 * @param array  $found
	 *
	 * @return array
	 */
	private function search( $type, $array = array(), $searched = array(), $regexp = false, $found = array() )
	{
		foreach ( $array as $key => $value ){
			
			// Recursion
			if( is_array( $value ) ){
				$result = $this->search( $type, $value, $searched, $regexp, array() );
				if($result)
					$found[$key] = $result;
				
				// Execution
			}else{
				foreach ( $searched as $searched_key => $searched_val ){
					switch ($type){
						case 'key':
							if( $key === $searched_val || ($regexp && preg_match( '/' . $searched_val . '/', $key) === 1) )
								$found[$key] = true;
							break;
						case 'value':
							if( stripos($value, $searched_val) !== false || ($regexp && preg_match( '/' . $searched_val . '/', $value) === 1) )
								$found[$key] = true;
							break;
						case 'array':
							if( stripos($key, $searched_key) !== false || ($regexp && preg_match( '/' . $searched_key . '/', $key) === 1) )
								if( is_array( $value ) && is_array( $value )){
									$result = $this->search( 'array', $value, $searched_key, $regexp, array() );
									if( $result ){
										$found[ $key ] = $result;
									}
								}else{
									$found[$key] = $value;
								}
							break;
					}
				}
			}
		}
		
		return $found;
	}
	
	public function compare( $arr1, $arr2 ){
		// $arr1 = is_array( $arr1 ) ? $arr1 : array();
		// $arr2 = is_array( $arr2 ) ? $arr2 : array();
		foreach ( $arr1 as $key1 => $val1 ){
			if( $arr1 === $arr2 ){
				if(is_array($arr1) && is_array($arr2)){
					$result = $this->compare( $arr1, $arr2 );
				}
			}
		}
	}
	
	/**
	 * Recursive
	 * Delete elements from array with found keys ( $this->found )
	 * If $searched param is differ from 'arr_special_param'
	 *
	 * @param mixed $searched
	 * @param array $array
	 * @param array $found
	 *
	 * @return array
	 */
	public function delete( $searched = 'arr_special_param', $array = array(), $found =array() )
	{
		$array = $array ? $array : $this->array;
		$found = $found	? $found : $this->found;
		
		foreach($array as $key => $value){
			
			if(array_key_exists($key, $found)){
				if( is_array( $found[ $key ] ) ){
					$array[ $key ] = $this->delete( $searched, $value, $found[ $key ] );
					if( empty( $array[ $key ] ) )
						unset( $array[ $key ] );
				}else{
					if( $searched === 'arr_special_param' || $searched === $value ){
							unset( $array[ $key ] );
					}
				}
			}
			
		}
		
		$this->result = $array;
		return $array;
	}
	
	public function result(){
		return (boolean) $this->found;
	}
}