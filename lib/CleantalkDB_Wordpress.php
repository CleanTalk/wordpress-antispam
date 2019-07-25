<?php

/*
 * CleanTalk Wordpress Data Base driver
 * Compatible only with Wordpress.
 * Version 2.0
 * author Cleantalk team (welcome@cleantalk.org)
 * copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * see https://github.com/CleanTalk/php-antispam
*/

class CleantalkDB_Wordpress
{
	/**
	 * @var string tables prefix
	 */
	public $prefix;
	
	/**
	 * @var wpdb instance of WPDB
	 */
	private $db;
	
	/**
	 * @var string Query string
	 */
	private $query;
	
	/**
	 * @var Raw DB result
	 */
	private $db_result;
	
	/**
	 * @var array Processed result
	 */
	public $result = array();
	
	/**
	 * CleantalkDB_Wordpress constructor.
	 */
	public function __construct()
	{
		global $wpdb, $apbct;
		$this->db = $wpdb;
		$this->prefix = $apbct->db_prefix;
	}
	
	/**
	 * Set $this->query string for next uses
	 *
	 * @param $query
	 * @return $this
	 */
	public function set_query($query)
	{
		$this->query = $query;
		return $this;
	}
	
	/**
	 * @todo finish in two weaks! (09.07.2019)
	 * Safely replace place holders
	 *
	 * @param string $query
	 * @param array  $vars
	 *
	 * @return $this
	 */
	public function prepare__incomplete__($query, $vars = array())
	{
		$query = $query ? $query : $this->query;
		$vars  = $vars  ? $vars  : array();
		array_unshift($vars, $query);
		
		$this->query = call_user_func_array('$this->db->prepare', $vars);
		
		return $this;
	}
	
	/**
	 * Run any raw request
	 *
	 * @param $query
	 *
	 * @return bool|int|Raw
	 */
	public function execute($query)
	{
		$this->db_result = $this->db->query($query);
		return $this->db_result;
	}
	
	/**
	 * Fetchs first column from query.
	 * May receive raw or prepared query.
	 *
	 * @param bool $query
	 * @param bool $response_type
	 *
	 * @return array|object|void|null
	 */
	public function fetch($query = false, $response_type = false)
	{
		$query       = $query       ? $query       : $this->query;
		$response_type = $response_type ? $response_type : ARRAY_A;
		
		$this->result = $this->db->get_row($query, $response_type);
		return $this->result;
	}
	
	/**
	 * Fetchs all result from query.
	 * May receive raw or prepared query.
	 *
	 * @param bool $query
	 * @param bool $response_type
	 *
	 * @return array|object|null
	 */
	public function fetch_all($query = false, $response_type = false)
	{
		$query       = $query       ? $query       : $this->query;
		$response_type = $response_type ? $response_type : ARRAY_A;
		
		$this->result = $this->db->get_results($query, $response_type);
		return $this->result;
	}
}