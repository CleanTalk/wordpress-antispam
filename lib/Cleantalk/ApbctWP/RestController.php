<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\Variables\AltSessions;
use Cleantalk\Common\TT;
use WP_REST_Request;

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
                'callback'            => array(AJAXService::class, 'getJSKeys'),
                'permission_callback' => '__return_true',
            )
        ));

        register_rest_route($this->namespace, "/apbct_get_pixel_url", array(
            array(
                'methods'             => 'POST',
                'callback'            => 'apbct_get_pixel_url',
                'permission_callback' => '__return_true',
            )
        ));

        register_rest_route($this->namespace, "/alt_sessions", array(
            array(
                'methods'             => 'POST',
                'callback'            => array(AltSessions::class, 'setFromRemote'),
                'args'                => array(
                    'cookies' => array(
                        'type'     => 'string',
                        'required' => true,
                    ),
                ),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods'             => 'GET',
                'callback'            => array(AltSessions::class, 'getFromRemote'),
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

        // REST route for checking email exist before POST
        register_rest_route($this->namespace, "/check_email_exist_post", array(
            array(
                'methods'             => 'POST',
                'callback'            => 'apbct_email_check_exist_post',
                'args'                => array(
                    'email' => array(
                        'type'     => 'email',
                        'required' => true,
                    ),
                ),
                'permission_callback' => '__return_true',
            )
        ));

        // REST route for decoding email
        register_rest_route($this->namespace, "/apbct_decode_email", array(
            array(
                'methods'             => 'POST',
                'callback'            => array(\Cleantalk\ApbctWP\Antispam\EmailEncoder::getInstance(),
                    'ajaxDecodeEmailHandler'
                ),
                'permission_callback' => function (WP_REST_Request $request) {
                    return wp_verify_nonce(TT::toString($request->get_header('x_wp_nonce')), 'wp_rest');
                },
                'args'                => array(
                    'encodedEmails' => array(
                        'type'     => 'array',
                        'required' => true,
                    ),
                ),
            )
        ));

        // Check REST route
        register_rest_route($this->namespace, "/apbct_rest_check", array(
            array(
                'methods'             => 'POST',
                'callback'            => function () {
                    return ['success' => true];
                },
                'permission_callback' => function (WP_REST_Request $request) {
                    return wp_verify_nonce(TT::toString($request->get_header('x_wp_nonce')), 'wp_rest');
                }
            )
        ));

        // REST route for force protection check bot
        register_rest_route($this->namespace, "/force_protection_check_bot", array(
            array(
                'methods'             => 'POST',
                'callback'            => array(\Cleantalk\ApbctWP\Antispam\ForceProtection::getInstance(), 'checkBot'),
                'permission_callback' => function (WP_REST_Request $request) {
                    return wp_verify_nonce(TT::toString($request->get_header('x_wp_nonce')), 'wp_rest');
                },
                'args'                => array(
                    'event_javascript_data' => array(
                        'type'     => 'array',
                        'required' => true,
                    ),
                    'post_url' => array(
                        'type'     => 'string',
                        'required' => true,
                    ),
                    'referrer' => array(
                        'type'     => 'string',
                        'required' => true,
                    ),
                ),
            )
        ));
    }
}
