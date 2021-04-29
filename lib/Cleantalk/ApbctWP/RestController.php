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
        
        register_rest_route( $this->namespace, "/alt_sessions", array(
            array(
                'methods'             => 'POST',
                'callback'            => array( \Cleantalk\ApbctWP\Variables\AltSessions::class, 'set_fromRemote' ),
                'args'                => array(
                    'cookies' => array(
                        'type'     => 'array',
                        'required' => true,
                    ),
                ),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods'             => 'GET',
                'callback'            => array( \Cleantalk\ApbctWP\Variables\AltSessions::class, 'get_fromRemote' ),
                'args'                => array(
                    'name' => array(
                        'type'     => 'string',
                        'required' => true,
                    ),
                ),
                'permission_callback' => '__return_true',
            )
        ) );
	}

}