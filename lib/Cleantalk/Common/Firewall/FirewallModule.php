<?php

namespace Cleantalk\Common\Firewall;

/*
 * The abstract class for any FireWall modules.
 * Compatible with any CMS.
 *
 * @version       1.0
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @since 2.49
 */
class FirewallModule extends FirewallModule_abstract {
	
	/**
	 * FireWall_module constructor.
	 * Use this method to prepare any data for the module working.
	 *
	 * @param $log_table
	 * @param $data_table
	 * @param array $params
	 */
	public function __construct( $log_table, $data_table, $params = array() ){
	
	}
	
	public function ip__append_additional( &$ips ){}
	
	/**
	 * Use this method to execute main logic of the module.
	 *
	 * @return array  Array of the check results
	 */
	public function check(){}
	
	public function actions_for_denied( $result ){}
	
	public function actions_for_passed( $result ){}
	
	/**
	 * @param mixed $db
	 */
	public function setDb( $db ) {
		$this->db = $db;
	}
	
	/**
	 * @param array $ip_array
	 */
	public function setIpArray( $ip_array ) {
		$this->ip_array = $ip_array;
	}
	
	public function getIpArray() {
		return $this->ip_array;
	}
	
	/**
	 * @param mixed $db__table__data
	 */
	public function setDbTableData( $db__table__data ) {
		$this->db__table__data = $db__table__data;
	}
	
	/**
	 * @param mixed $db__table__logs
	 */
	public function setDbTableLogs( $db__table__logs ) {
		$this->db__table__logs = $db__table__logs;
	}
}