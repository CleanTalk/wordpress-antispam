<?php

namespace Cleantalk\ApbctWP;

class RestController extends \WP_REST_Controller {

	public function __construct()
	{
		$this->namespace = 'cleantalk-antispam/v1';
	}

	public function register_routes()
	{
		register_rest_route( $this->namespace, "/js_keys__get", array(
			array(
				'methods'             => 'POST',
				'callback'            => 'apbct_js_keys__get__ajax',
				'permission_callback' => '__return_true',
			)
		) );
	}

}