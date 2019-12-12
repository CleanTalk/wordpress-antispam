<?php

namespace Cleantalk\Templates;

trait Singleton{
	
	static $instance;
	
	public function __construct(){}
	public function __wakeup(){}
	public function __clone(){}
	
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
	 * Alternative constructor
	 */
	private function init(){
	
	}
	
}