<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\Antispam\ForceProtection;
use Cleantalk\ApbctWP\ContactsEncoder\ContactsEncoder;
use Cleantalk\ApbctWP\Variables\AltSessions;
use Cleantalk\Common\TT;
use WP_REST_Request;
use WP_REST_Response;

class RestController extends \WP_REST_Controller
{
    /**
     * @var ContactsEncoder
     */
    private $contacts_encoder;

    /**
     * @var ForceProtection
     */
    private $force_protection;

    public function __construct()
    {
        $this->namespace = 'cleantalk-antispam/v1';
    }

    public function register_routes() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        register_rest_route($this->namespace, "/js_keys__get", array(
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'getJSKeys'),
                'permission_callback' => '__return_true',
            )
        ));

        register_rest_route($this->namespace, "/apbct_get_pixel_url", array(
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'getPixelUrl'),
                'permission_callback' => '__return_true',
            )
        ));

        register_rest_route($this->namespace, "/alt_sessions", array(
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'saveAltSessions'),
                'args'                => array(
                    'cookies' => array(
                        'type'     => 'string',
                        'required' => true,
                    ),
                ),
                'permission_callback' => function (WP_REST_Request $request) {
                    return (bool) wp_verify_nonce(TT::toString($request->get_header('x_wp_nonce')), 'wp_rest');
                },
            )
        ));

        // REST route for checking email before POST
        register_rest_route($this->namespace, "/check_email_before_post", array(
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'checkEmailBeforePost'),
                'args'                => array(
                    'email' => array(
                        'type'     => 'string',
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
                'callback'            => array($this, 'checkEmailExistPost'),
                'args'                => array(
                    'email' => array(
                        'type'     => 'string',
                        'required' => true,
                    ),
                ),
                'permission_callback' => '__return_true',
            )
        ));

        // REST route for decoding email
        $this->contacts_encoder = apbctGetContactsEncoder();
        register_rest_route($this->namespace, "/apbct_decode_email", array(
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'decodeEmail'),
                'permission_callback' => function (WP_REST_Request $request) {
                    return wp_verify_nonce(TT::toString($request->get_header('x_wp_nonce')), 'wp_rest');
                },
                'args'                => array(
                    'encodedEmails' => array(
                        'type'     => array('array', 'string'),
                        'required' => true,
                    ),
                ),
            )
        ));

        // Check REST route
        register_rest_route($this->namespace, "/apbct_rest_check", array(
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'checkRest'),
                'permission_callback' => function (WP_REST_Request $request) {
                    return wp_verify_nonce(TT::toString($request->get_header('x_wp_nonce')), 'wp_rest');
                }
            )
        ));

        // REST route for force protection check bot
        $this->force_protection = ForceProtection::getInstance();
        register_rest_route($this->namespace, "/force_protection_check_bot", array(
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'checkBot'),
                'permission_callback' => function (WP_REST_Request $request) {
                    return wp_verify_nonce(TT::toString($request->get_header('x_wp_nonce')), 'wp_rest');
                },
                'args'                => array(
                    'event_javascript_data' => array(
                        'type'     => array('array', 'string'),
                        'required' => false,
                    ),
                    'event_token' => array(
                        'type'     => 'string',
                        'required' => false,
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

    public function getJSKeys(): WP_REST_Response
    {
        return new WP_REST_Response(AJAXService::getJSKeysData());
    }

    public function getPixelUrl(): WP_REST_Response
    {
        return new WP_REST_Response(apbct_get_pixel_url(true));
    }

    public function checkEmailBeforePost(WP_REST_Request $request): WP_REST_Response
    {
        $email = sanitize_textarea_field(TT::toString($request->get_param('email')));

        return new WP_REST_Response(apbct_email_check_before_post_result($email));
    }

    public function checkEmailExistPost(WP_REST_Request $request): WP_REST_Response
    {
        $email = sanitize_textarea_field(TT::toString($request->get_param('email')));

        return new WP_REST_Response(apbct_email_check_exist_post_result($email));
    }

    public function saveAltSessions(WP_REST_Request $request): WP_REST_Response
    {
        $data = AltSessions::saveRemoteCookies(
            TT::toString($request->get_header('x_wp_nonce')),
            'wp_rest',
            TT::toString($request->get_param('cookies'))
        );

        return new WP_REST_Response($data);
    }

    /**
     * Return contact-decoding data through REST without invoking the AJAX handler.
     *
     * Sanitizes encodedEmails and passes the REST parameters to the shared decoder.
     * WordPress handles JSON serialization and response output.
     *
     * @param WP_REST_Request $request Request containing encodedEmails and decoding parameters.
     * @return WP_REST_Response Response containing success and an array of contact results.
     */
    public function decodeEmail(WP_REST_Request $request): WP_REST_Response
    {
        $encoded_emails = \Cleantalk\Variables\Post::getInstance()->getAndSanitize(
            $request->get_param('encodedEmails')
        );
        $data = $this->contacts_encoder->getDecodeEmailResult($encoded_emails, $request->get_params());

        return new WP_REST_Response($data);
    }

    public function checkRest(): WP_REST_Response
    {
        return new WP_REST_Response(array('success' => true));
    }

    public function checkBot(WP_REST_Request $request): WP_REST_Response
    {
        $data = $this->force_protection->getCheckBotResult($request->get_params());

        return new WP_REST_Response($data);
    }
}
