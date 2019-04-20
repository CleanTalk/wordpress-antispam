<?php

class CleantalkIntegration {
	
	/**
	 * @var string Integration name
	 */
	public $name;
	
	/**
	 * @var string Integration type (form || comment || registration)
	 */
	public $type = 'form';
	
	/**
	 * @var boolean Ajax or not
	 */
	public $ajax = false;
	
	/**
	 * @var mixed array|null Request param for identify integration. For example: array('action' => 'myform')
	 */
	public $identify = null;
	
	/**
	 * @var mixed null|string|array special JSON string for form response
	 */
	public $response;
	
	/**
	 * @var array Array with hooks. 
	 * Example:
	 * array(
	 *	'spam_check' => array(
	 *		'hook_function' => 'add_filter|do_action',
	 *		'hook' => 'myform_test_spam',
	 *		'function' => 'apbct_test_spam'
	 *	)
	 * )
	 */
	public $actions = array();
	
    function __construct($name, $type, $params = array()) {
        
		$this->name		 = $name;
		$this->type		 = $type;
		$this->ajax		 = isset($params['ajax'])     ? true                : false;
		$this->identify	 = isset($params['idetify'])  ? $params['idetify']  : null;
		$this->response	 = isset($params['response']) ? $params['response'] : null;
		$this->actions	 = isset($params['actions'])  ? $params['actions']  : null;

	}
	
	
}