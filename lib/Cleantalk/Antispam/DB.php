<?php

namespace Cleantalk\Antispam;

/**
 * CleanTalk abstract Data Base driver.
 * Shows what should be inside.
 * Uses singleton pattern.
 *
 * @version 1.0
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see https://github.com/CleanTalk/php-antispam
*/

class DB
{
	
	private static $instance;
	
	/**
	 * @var string Query string
	 */
	private $query;
	
	/**
	 * @var wpdb result
	 */
	private $db_result;
	
	/**
	 * @var array Processed result
	 */
	public $result = array();
	
	/**
	 * @var string Database prefix
	 */
	public $prefix = '';
	
	public function __construct() {	}
	public function __clone() { }
	public function __wakeup() 	{ }
	
	public static function getInstance()
	{
		if (!isset(static::$instance)) {
			static::$instance = new static;
			static::$instance->init();
		}
		
		return static::$instance;
	}
	
	/**
	 * Alternative constructor.
	 * Initilize Database object and write it to property.
	 * Set tables prefix.
	 */
	private function init(){ }
	
	/**
	 * Set $this->query string for next uses
	 *
	 * @param $query
	 * @return $this
	 */
	public function set_query($query){ }
	
	/**
	 * Safely replace place holders
	 *
	 * @param string $query
	 * @param array  $vars
	 *
	 * @return $this
	 */
	public function prepare($query, $vars = array()){ }
	
	/**
	 * Run any raw request
	 *
	 * @param $query
	 *
	 * @return bool|int Raw result
	 */
	public function execute($query){ }
	
	/**
	 * Fetchs first column from query.
	 * May receive raw or prepared query.
	 *
	 * @param bool $query
	 * @param bool $response_type
	 *
	 * @return array|object|void|null
	 */
	public function fetch($query = false, $response_type = false){ }
	
	/**
	 * Fetchs all result from query.
	 * May receive raw or prepared query.
	 *
	 * @param bool $query
	 * @param bool $response_type
	 *
	 * @return array|object|null
	 */
	public function fetch_all($query = false, $response_type = false){ }
}