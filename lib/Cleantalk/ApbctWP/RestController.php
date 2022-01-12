<?php

namespace Cleantalk\ApbctWP;

class RestController extends \WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace = 'cleantalk-antispam/v1';
    }

    public function register_routes() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        register_rest_route($this->namespace, "/js_keys__get", array(
            array(
                'methods'             => 'POST',
                'callback'            => 'apbct_js_keys__get__ajax',
                'permission_callback' => '__return_true',
            )
        ));

        register_rest_route($this->namespace, "/apbct_get_pixel_url", array(
            array(
                'methods'             => 'POST',
                'callback'            => 'apbct_get_pixel_url__ajax',
                'permission_callback' => '__return_true',
            )
        ));

        register_rest_route($this->namespace, "/alt_sessions", array(
            array(
                'methods'             => 'POST',
                'callback'            => array(\Cleantalk\ApbctWP\Variables\AltSessions::class, 'setFromRemote'),
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
                'callback'            => array(\Cleantalk\ApbctWP\Variables\AltSessions::class, 'getFromRemote'),
                'args'                => array(
                    'name' => array(
                        'type'     => 'string',
                        'required' => true,
                    ),
                ),
                'permission_callback' => '__return_true',
            )
        ));

        // REST route for checking email before POST
        register_rest_route($this->namespace, "/check_email_before_post", array(
            array(
                'methods'             => 'POST',
                'callback'            => 'apbct_email_check_before_post',
                'args'                => array(
                    'email' => array(
                        'type'     => 'email',
                        'required' => true,
                    ),
                ),
                'permission_callback' => '__return_true',
            )
        ));
    }
}
