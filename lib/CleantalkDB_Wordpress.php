<?php

/*
 * CleanTalk Wordpress Data Base class
 * Compatible only with Wordpress.
 * Version 1.0
 * author Cleantalk team (welcome@cleantalk.org)
 * copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * see https://github.com/CleanTalk/php-antispam
*/

class CleantalkDB_Wordpress
{	
	
	public $prefix;
	public $result = array();
	
	private $db;
	private $query;
	private $db_result;
	
	public function __construct()
	{
		global $wpdb, $apbct;
		$this->db = $wpdb;
		$this->prefix = $apbct->db_prefix;
	}
	
	public function query($query, $straight_query = false)
	{
		if($straight_query)
			$this->db_result = $this->db->query($query);
		else
			$this->query = $query;
		
		return $this;
	}
	
	public function fetch()
	{
		$this->result = $this->db->get_row($this->query, ARRAY_A);
		return $this->result;
	}
	
	public function fetch_all()
	{
		$this->result = $this->db->get_results($this->query, ARRAY_A);
		return $this->result;
	}
}
