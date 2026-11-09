<?php

namespace ApbctWP;

use Cleantalk\ApbctWP\AJAXService;
use Cleantalk\ApbctWP\Antispam\ForceProtection;
use Cleantalk\ApbctWP\BotDetectorService;
use Cleantalk\ApbctWP\ContactsEncoder\ContactsEncoder;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\HooksRegistrar;
use Cleantalk\ApbctWP\RequestParameters\RequestParameters;
use Cleantalk\ApbctWP\RestController;
use Cleantalk\ApbctWP\Variables\AltSessions;
use Cleantalk\ApbctWP\Variables\NoCookie;
use PHPUnit\Framework\TestCase;

class TestRestResponses extends TestCase
{
    private $original_data;
    private $original_settings;
    private $original_api_key;
    private $original_rest_server;
    private $original_errors;
    private $original_errors_option;
    private $original_no_cookies;
    private $doing_it_wrong = array();

    protected function setUp(): void
    {
        parent::setUp();
        global $apbct, $wp_rest_server;
        $this->original_data = clone $apbct->data;
        $this->original_settings = clone $apbct->settings;
        $this->original_api_key = $apbct->api_key;
        $this->original_rest_server = $wp_rest_server;
        $this->original_errors = $apbct->errors;
        $this->original_errors_option = get_option($apbct->option_prefix . '_errors', null);
        $this->original_no_cookies = NoCookie::$no_cookies_data;
        $apbct->errors = new \ArrayObject();
        NoCookie::$no_cookies_data = array();
        $apbct->settings['data__use_static_js_key'] = 1;
        $wp_rest_server = new \WP_REST_Server();
        add_action('doing_it_wrong_run', array($this, 'recordDoingItWrong'), 10, 3);
        do_action('rest_api_init', $wp_rest_server);
    }

    protected function tearDown(): void
    {
        global $apbct, $wp_rest_server;
        $apbct->data = $this->original_data;
        $apbct->settings = $this->original_settings;
        $apbct->api_key = $this->original_api_key;
        $apbct->errors = $this->original_errors;
        if ( $this->original_errors_option === null ) {
            delete_option($apbct->option_prefix . '_errors');
        } else {
            update_option($apbct->option_prefix . '_errors', $this->original_errors_option);
        }
        NoCookie::$no_cookies_data = $this->original_no_cookies;
        $wp_rest_server = $this->original_rest_server;
        remove_action('doing_it_wrong_run', array($this, 'recordDoingItWrong'), 10);
        parent::tearDown();
    }

    public function recordDoingItWrong($function, $message, $version)
    {
        $this->doing_it_wrong[] = $function;
    }

    private function getController(): RestController
    {
        $routes = rest_get_server()->get_routes();

        return $routes['/cleantalk-antispam/v1/js_keys__get'][0]['callback'][0];
    }

    public function testAllRestCallbacksBelongToController()
    {
        $routes = rest_get_server()->get_routes();
        $controller = $this->getController();
        $expected = array(
            'js_keys__get' => 'getJSKeys',
            'apbct_get_pixel_url' => 'getPixelUrl',
            'alt_sessions' => 'saveAltSessions',
            'check_email_before_post' => 'checkEmailBeforePost',
            'check_email_exist_post' => 'checkEmailExistPost',
            'apbct_decode_email' => 'decodeEmail',
            'apbct_rest_check' => 'checkRest',
            'force_protection_check_bot' => 'checkBot',
        );
        foreach ( $expected as $route => $method ) {
            $callback = $routes['/cleantalk-antispam/v1/' . $route][0]['callback'];
            $this->assertSame(array($controller, $method), $callback);
            $reflection = new \ReflectionMethod($controller, $method);
            $this->assertSame(\WP_REST_Response::class, (string)$reflection->getReturnType());
        }
    }

    /**
     * @dataProvider publicRoutesProvider
     */
    public function testPublicRestRoutesReturnResponses($route, $params, $field)
    {
        $request = new \WP_REST_Request('POST', '/cleantalk-antispam/v1/' . $route);
        $request->set_header('x_wp_nonce', wp_create_nonce('wp_rest'));
        $request->set_body_params($params);
        $response = rest_get_server()->dispatch($request);
        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        if ( $field === null ) {
            $this->assertIsString($response->get_data());
            $this->assertStringContainsString('/pixel/', $response->get_data());
        } else {
            $this->assertArrayHasKey($field, $response->get_data());
        }
        $this->assertNotContains('wp_send_json', $this->doing_it_wrong);
    }

    public function publicRoutesProvider()
    {
        return array(
            array('js_keys__get', array(), 'js_key'),
            array('apbct_get_pixel_url', array(), null),
            array('check_email_before_post', array('email' => ''), 'error'),
            array('check_email_exist_post', array('email' => ''), 'error'),
            array('apbct_rest_check', array(), 'success'),
            array('apbct_decode_email', array('encodedEmails' => array()), 'success'),
        );
    }

    /**
     * @dataProvider invalidCookiesProvider
     */
    public function testAltSessionsReturnsErrors($cookies)
    {
        $request = new \WP_REST_Request('POST', '/cleantalk-antispam/v1/alt_sessions');
        $request->set_header('x_wp_nonce', wp_create_nonce('wp_rest'));
        $request->set_param('cookies', $cookies);
        $response = rest_get_server()->dispatch($request);
        $this->assertSame(200, $response->get_status());
        $this->assertFalse($response->get_data()['success']);
        $this->assertArrayHasKey('error', $response->get_data());
        $this->assertNotContains('wp_send_json', $this->doing_it_wrong);
    }

    public function invalidCookiesProvider()
    {
        return array(array(''), array('{'), array('null'), array('false'), array('"value"'));
    }

    public function testAltSessionsNonceFailure()
    {
        $request = new \WP_REST_Request('POST');
        $request->set_param('cookies', '{}');
        $response = $this->getController()->saveAltSessions($request);
        $this->assertFalse($response->get_data()['success']);
        $this->assertStringContainsString('Nonce verification failed', $response->get_data()['error']);
    }

    /**
     * @dataProvider contactsPayloadProvider
     */
    public function testContactsAcceptsArraysAndJsonStrings($payload)
    {
        $encoder = $this->getMockBuilder(ContactsEncoder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(array('decodeContactData'))
            ->getMock();
        $property = new \ReflectionProperty($encoder, 'is_logged_in');
        $property->setAccessible(true);
        $property->setValue($encoder, true);
        $encoder->has_connection_error = false;
        $encoder->expects($this->once())->method('decodeContactData')
            ->with(array('encoded-contact', 'second-contact'))->willReturn(array());
        $controller = $this->getController();
        $property = new \ReflectionProperty($controller, 'contacts_encoder');
        $property->setAccessible(true);
        $property->setValue($controller, $encoder);
        $request = new \WP_REST_Request('POST', '/cleantalk-antispam/v1/apbct_decode_email');
        $request->set_header('x_wp_nonce', wp_create_nonce('wp_rest'));
        $request->set_body_params(array('encodedEmails' => $payload));
        $response = rest_get_server()->dispatch($request);
        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $this->assertSame(
            array('success' => false, 'data' => array(), 'set_passed_cookie' => false),
            $response->get_data()
        );
    }

    public function contactsPayloadProvider()
    {
        return array(
            'REST array' => array(array('encoded-contact', 'second-contact')),
            'REST JSON array' => array('["encoded-contact","second-contact"]'),
            'REST JSON object' => array('{"0":"encoded-contact","1":"second-contact"}'),
        );
    }

    /**
     * @dataProvider emptyContactsProvider
     */
    public function testContactsCallbackReturnsFailureForEmptyResult(
        $payload,
        $has_connection_error = false
    )
    {
        global $apbct;
        $encoder = $this->getMockBuilder(ContactsEncoder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(array('decodeContactData'))
            ->getMock();
        $encoder->has_connection_error = $has_connection_error;
        $encoder->expects($this->never())->method('decodeContactData');
        $controller = $this->getController();
        $property = new \ReflectionProperty($controller, 'contacts_encoder');
        $property->setAccessible(true);
        $property->setValue($controller, $encoder);
        $request = new \WP_REST_Request('POST', '/cleantalk-antispam/v1/apbct_decode_email');
        $request->set_header('x_wp_nonce', wp_create_nonce('wp_rest'));
        $request->set_body_params(array('encodedEmails' => $payload));
        $response = $controller->decodeEmail($request);
        $this->assertSame(200, $response->get_status());
        $this->assertSame(
            array('success' => false, 'data' => array(), 'set_passed_cookie' => false),
            $response->get_data()
        );
        $this->assertSame($has_connection_error, $encoder->has_connection_error);
        if ( $payload === '{' ) {
            $this->assertStringContainsString(
                'JSON decoding failed: ',
                $apbct->errors['email_encoder'][0]['error']
            );
        } else {
            $this->assertSame(
                'Invalid contact-decoding input.',
                $apbct->errors['email_encoder'][0]['error']
            );
        }
        $this->assertNotContains('wp_send_json', $this->doing_it_wrong);
    }

    public function emptyContactsProvider()
    {
        return array(
            array('{'),
            array('null'),
            array('false'),
            array('42'),
            array('"value"'),
            array('{}'),
            array('[]'),
            array(''),
            array(array()),
            array(array(), true),
            array(array('')),
            array(array(42)),
            array(array(null)),
            array(array(array())),
            array(array('encoded-contact', array())),
        );
    }

    /**
     * @dataProvider nonEmptyContactsProvider
     */
    public function testContactsPreservesNonEmptyResponses($is_allowed, $has_connection_error, $comment, $success)
    {
        $encoder = $this->getMockBuilder(ContactsEncoder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(array('decodeContactData', 'getCheckRequestComment'))
            ->getMock();
        $encoder->has_connection_error = $has_connection_error;
        foreach ( array('is_logged_in' => true, 'is_encode_allowed' => $is_allowed) as $name => $value ) {
            $property = new \ReflectionProperty($encoder, $name);
            $property->setAccessible(true);
            $property->setValue($encoder, $value);
        }
        $encoder->expects($this->once())->method('decodeContactData')
            ->with(array('encoded-contact'))
            ->willReturn(array('encoded-contact' => 'user@example.com'));
        $encoder->method('getCheckRequestComment')->willReturn($comment);

        $response = $encoder->getDecodeEmailResult(array('encoded-contact'));
        $expected_allowed = $is_allowed && ! $has_connection_error;

        $this->assertSame(array(
            'success' => $success,
            'data' => array(array(
                'is_allowed' => $expected_allowed,
                'show_comment' => ! $expected_allowed,
                'comment' => $comment,
                'encoded_email' => 'encoded-contact',
                'decoded_email' => $expected_allowed ? 'user@example.com' : '',
            )),
            'set_passed_cookie' => false,
        ), $response);
    }

    public function nonEmptyContactsProvider()
    {
        return array(
            'allowed' => array(true, false, 'Allowed', true),
            'blocked' => array(false, false, 'Blocked', true),
            'api error' => array(true, true, 'API unavailable', false),
        );
    }

    /**
     * @dataProvider contactsCookieDecisionProvider
     */
    public function testContactsCookieDecision($is_valid_input, $is_logged_in, $is_allowed, $expected)
    {
        $encoder = new ContactsEncoder();
        foreach ( array('is_logged_in' => $is_logged_in, 'is_encode_allowed' => $is_allowed) as $name => $value ) {
            $property = new \ReflectionProperty($encoder, $name);
            $property->setAccessible(true);
            $property->setValue($encoder, $value);
        }
        $method = new \ReflectionMethod($encoder, 'shouldSetPassedCookie');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke($encoder, $is_valid_input));
    }

    public function contactsCookieDecisionProvider()
    {
        return array(
            'allowed guest' => array(true, false, true, true),
            'blocked guest' => array(true, false, false, false),
            'invalid input' => array(false, false, true, false),
            'invalid and blocked' => array(false, false, false, false),
            'logged-in visitor' => array(true, true, true, false),
            'logged-in and blocked' => array(true, true, false, false),
        );
    }

    /**
     * @dataProvider contactsPassMarkProvider
     */
    public function testContactsCookiePermissionRequiresSavedServerMark(
        $is_allowed,
        $has_connection_error,
        $decoded_contacts,
        $storage_result,
        $expected_permission
    )
    {
        global $apbct, $wpdb;
        $original_wpdb = $wpdb;
        $cache = new \ReflectionProperty(AltSessions::class, 'values_cache');
        $cache->setAccessible(true);
        $cache_id = new \ReflectionProperty(AltSessions::class, 'values_cache_id');
        $cache_id->setAccessible(true);
        $original_cache = $cache->getValue();
        $original_cache_id = $cache_id->getValue();

        $encoder = $this->getMockBuilder(ContactsEncoder::class)
            ->onlyMethods(array('decodeContactData', 'getCheckRequestComment'))
            ->getMock();
        $encoder->has_connection_error = $has_connection_error;
        $property = new \ReflectionProperty($encoder, 'is_encode_allowed');
        $property->setAccessible(true);
        $property->setValue($encoder, $is_allowed);
        $encoder->expects($this->once())->method('decodeContactData')
            ->with(array('encoded-contact'))->willReturn($decoded_contacts);
        $encoder->method('getCheckRequestComment')->willReturn('Checked');

        $database = $this->getMockBuilder(\wpdb::class)
            ->disableOriginalConstructor()
            ->onlyMethods(array('get_var', 'prepare', 'query'))
            ->getMock();
        $database->method('get_var')->willReturn(serialize(array('existing' => 'value')));
        $database->method('prepare')->willReturn('pass-mark-write');
        $database->expects($is_allowed ? $this->once() : $this->never())->method('query')
            ->with('pass-mark-write')
            ->willReturnCallback(function () use ($original_wpdb, $storage_result) {
                // Restore WordPress storage before any failure is logged.
                $GLOBALS['wpdb'] = $original_wpdb;
                return $storage_result;
            });

        try {
            $cache->setValue(null, null);
            $cache_id->setValue(null, null);
            $wpdb = $database;
            $response = $encoder->getDecodeEmailResult(array('encoded-contact'));

            $this->assertSame($expected_permission, $response['set_passed_cookie']);
            if ( $is_allowed ) {
                $values = $cache->getValue();
                $this->assertSame('value', $values['existing']);
                if ( $expected_permission ) {
                    $this->assertSame('1', $values['apbct_email_encoder_verified']);
                } else {
                    $this->assertArrayNotHasKey('apbct_email_encoder_verified', $values);
                    $this->assertSame(
                        'Failed to save the contact-decoding pass mark.',
                        $apbct->errors['email_encoder'][0]['error']
                    );
                }
            }
        } finally {
            $wpdb = $original_wpdb;
            $cache->setValue(null, $original_cache);
            $cache_id->setValue(null, $original_cache_id);
        }
    }

    public function contactsPassMarkProvider()
    {
        $decoded = array('encoded-contact' => 'user@example.com');

        return array(
            'allowed' => array(true, false, $decoded, 1, true),
            'blocked' => array(false, false, $decoded, 1, false),
            'API unavailable' => array(true, true, $decoded, 1, true),
            'empty decoding result' => array(true, false, array(), 1, true),
            'failed contact decryption' => array(true, false, array('encoded-contact' => ''), 1, true),
            'unchanged database row' => array(true, false, $decoded, 0, true),
            'storage failure' => array(true, false, $decoded, false, false),
        );
    }

    /**
     * @dataProvider forceProtectionResponsesProvider
     */
    public function testForceProtectionDoesNotDoubleEncodeRestData($data)
    {
        $protection = $this->getMockBuilder(ForceProtection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(array('getCheckBotResult'))
            ->getMock();
        $protection->expects($this->exactly(2))->method('getCheckBotResult')->willReturn($data);
        $controller = $this->getController();
        $property = new \ReflectionProperty($controller, 'force_protection');
        $property->setAccessible(true);
        $property->setValue($controller, $protection);
        $request = new \WP_REST_Request('POST', '/cleantalk-antispam/v1/force_protection_check_bot');
        $request->set_header('x_wp_nonce', wp_create_nonce('wp_rest'));
        $request->set_body_params(array('post_url' => 'https://example.org/form', 'referrer' => ''));
        $response = rest_get_server()->dispatch($request);
        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $this->assertSame($data, $response->get_data());
        $ajax_response = $protection->checkBotAJAX();
        $decoded = Helper::jsonDecode($ajax_response, true);
        $this->assertSame($data, $decoded);
        $this->assertNotContains('wp_send_json', $this->doing_it_wrong);
    }

    public function forceProtectionResponsesProvider()
    {
        return array(
            'allowed' => array(array('allow' => 1, 'message' => 'Allowed', 'success' => true)),
            'blocked' => array(array('allow' => 0, 'message' => 'Blocked', 'success' => true)),
            'api error' => array(array(
                'allow' => 1,
                'success' => false,
                'error' => array('comment' => 'API unavailable'),
            )),
        );
    }

    /**
     * @dataProvider forceProtectionEventDataProvider
     */
    public function testForceProtectionPreservesRestEventData($payload)
    {
        $received_request_data = null;
        $result = array('allow' => 1, 'message' => 'Allowed', 'success' => true);
        $protection = $this->getMockBuilder(ForceProtection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(array('getCheckBotResult'))
            ->getMock();
        $protection->expects($this->once())->method('getCheckBotResult')
            ->willReturnCallback(function ($request_data) use (&$received_request_data, $result) {
                $received_request_data = $request_data;
                return $result;
            });
        $controller = $this->getController();
        $property = new \ReflectionProperty($controller, 'force_protection');
        $property->setAccessible(true);
        $property->setValue($controller, $protection);
        $request = new \WP_REST_Request('POST', '/cleantalk-antispam/v1/force_protection_check_bot');
        $request->set_header('x_wp_nonce', wp_create_nonce('wp_rest'));
        $request->set_body_params(array(
            'post_url' => 'https://example.org/form',
            'referrer' => '',
            'event_javascript_data' => $payload,
        ));

        $response = rest_get_server()->dispatch($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $this->assertSame($result, $response->get_data());
        $this->assertIsArray($received_request_data);
        $this->assertArrayHasKey('event_javascript_data', $received_request_data);
        $this->assertSame($payload, $received_request_data['event_javascript_data']);
        $this->assertNotContains('wp_send_json', $this->doing_it_wrong);
    }

    public function forceProtectionEventDataProvider()
    {
        return array(
            'JSON object' => array(
                '{"screen":{"width":1920,"height":1080},"events":["focus","key,up"],"label":"two words"}',
            ),
            'JSON array' => array('[{"type":"focus"},{"type":"key,up"}]'),
            'array' => array(array(
                array('type' => 'focus'),
                array('type' => 'key,up', 'screen' => array('width' => 1920, 'height' => 1080)),
            )),
        );
    }

    public function testForceProtectionReportsRequestEncodingFailure()
    {
        global $apbct;
        $apbct->data['bot_detector_enabled'] = 0;
        if ( BotDetectorService::isEnabled() ) {
            $this->markTestSkipped('BotDetector is forced on by a constant.');
        }
        $apbct->data['cookies_type'] = 'none';
        $request = new \WP_REST_Request('POST');
        $request->set_param('event_javascript_data', array('value' => INF));
        $response = $this->getController()->checkBot($request);
        $this->assertSame(1, $response->get_data()['allow']);
        $this->assertFalse($response->get_data()['success']);
        $this->assertArrayHasKey('comment', $response->get_data()['error']);
        $this->assertStringContainsString('JSON encoding failed: ', $response->get_data()['error']['comment']);
        $this->assertSame(200, $response->get_status());
        $this->assertFalse(RequestParameters::get('apbct_force_protection_check'));
        $this->assertStringContainsString(
            'JSON encoding failed: ',
            $apbct->errors['force_protection'][0]['error']
        );
    }

    /**
     * @dataProvider forceProtectionAllowProvider
     */
    public function testForceProtectionAjaxEncodingFailureReturnsJsonAndResetsPassToken($allow)
    {
        global $apbct;
        $apbct->data['cookies_type'] = 'none';
        $protection = $this->getMockBuilder(ForceProtection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(array('getCheckBotResult'))
            ->getMock();
        $protection->expects($this->once())->method('getCheckBotResult')
            ->willReturnCallback(function () use ($allow) {
                $this->assertTrue(RequestParameters::set(
                    'apbct_force_protection_check',
                    $allow === 1 ? 'current-pass-token' : '0'
                ));

                return array('allow' => $allow, 'message' => "\xB1", 'success' => true);
            });

        $response = Helper::jsonDecode($protection->checkBotAJAX(), true);

        $this->assertSame(
            array('allow' => 1, 'success' => false, 'error' => array('comment' => 'JSON encoding failed.')),
            $response
        );
        $this->assertSame('0', RequestParameters::get('apbct_force_protection_check'));
        $this->assertStringContainsString(
            'JSON encoding failed: ',
            $apbct->errors['force_protection'][0]['error']
        );
    }

    public function forceProtectionAllowProvider()
    {
        return array(array(0), array(1));
    }

    public function testAjaxRoutingRemainsUnchanged()
    {
        $service = $this->getMockBuilder(AJAXService::class)
            ->onlyMethods(array('addPublicAction'))
            ->getMock();
        $service->expects($this->exactly(6))->method('addPublicAction')->withConsecutive(
            array('apbct_alt_session__save__AJAX', array(AltSessions::class, 'setFromAJAX')),
            array('apbct_js_keys__get', array($service, 'getJSKeys')),
            array('apbct_get_pixel_url', 'apbct_get_pixel_url'),
            array('apbct_email_check_before_post', 'apbct_email_check_before_post'),
            array('apbct_email_check_exist_post', 'apbct_email_check_exist_post'),
            array('apbct_force_protection_check_bot', 'apbct_force_protection_check_bot')
        );
        HooksRegistrar::registerAjaxHooks($service);
    }
}
