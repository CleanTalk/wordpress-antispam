<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

/**
 * doBlock() relies on wp_send_json(), which terminates the script with a bare die()
 * when not handling an AJAX request. PHP resolves unqualified function calls in the
 * current namespace first, so overriding wp_send_json() here intercepts the call made
 * inside BuddyNextRegistration (declared in this same namespace) without ever reaching
 * the real WordPress function and its die().
 */
if (! function_exists(__NAMESPACE__ . '\\wp_send_json')) {
    function wp_send_json($response, $status_code = null, $flags = 0)
    {
        $GLOBALS['apbct_test_buddynext_wp_send_json'] = $response;
    }
}

class TestBuddyNextRegistration extends \ApbctTestCase
{
    /**
     * @var BuddyNextRegistration
     */
    private $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integration = new BuddyNextRegistration();
        Post::getInstance()->variables = array();
        unset($GLOBALS['apbct_test_buddynext_wp_send_json']);
    }

    protected function tearDown(): void
    {
        Post::getInstance()->variables = array();
        unset($GLOBALS['apbct_test_buddynext_wp_send_json']);
        parent::tearDown();
    }

    /**
     * BuddyNext must be reported active via the "active_plugins" option for the integration to work.
     */
    private function withBuddyNextActive(callable $callback)
    {
        $active_plugins = static function () {
            return array('buddynext/buddynext.php');
        };

        add_filter('pre_option_active_plugins', $active_plugins);
        try {
            return $callback();
        } finally {
            remove_filter('pre_option_active_plugins', $active_plugins);
        }
    }

    private function prepareDefaultArgument()
    {
        return array(
            'name'                        => 'Test Name',
            'email'                       => 'test@example.com',
            'apbct_visible_fields'        => 'test_vfields',
            'ct_bot_detector_event_token' => 'test_token',
        );
    }

    public function testGetDataForCheckingReturnsNullWhenPluginIsNotActive()
    {
        $this->assertNull(
            $this->integration->getDataForChecking($this->prepareDefaultArgument())
        );
    }

    public function testGetDataForCheckingReturnsNullWhenEmailIsMissing()
    {
        $argument = $this->prepareDefaultArgument();
        unset($argument['email']);

        $result = $this->withBuddyNextActive(function () use ($argument) {
            return $this->integration->getDataForChecking($argument);
        });

        $this->assertNull($result);
    }

    public function testGetDataForCheckingReturnsDtoArrayWhenActiveAndEmailProvided()
    {
        $argument = $this->prepareDefaultArgument();

        $result = $this->withBuddyNextActive(function () use ($argument) {
            return $this->integration->getDataForChecking($argument);
        });

        $this->assertIsArray($result);
        $this->assertSame('test@example.com', $result['email']);
        $this->assertSame('Test Name', $result['nickname']);
        $this->assertTrue($result['register']);
        $this->assertSame('test_token', $result['event_token']);
    }

    public function testGetDataForCheckingHandlesMissingEventToken()
    {
        $argument = $this->prepareDefaultArgument();
        unset($argument['ct_bot_detector_event_token']);

        $result = $this->withBuddyNextActive(function () use ($argument) {
            return $this->integration->getDataForChecking($argument);
        });

        $this->assertIsArray($result);
        $this->assertNull($result['event_token']);
    }

    public function testGetDataForCheckingHandlesMissingName()
    {
        $argument = $this->prepareDefaultArgument();
        unset($argument['name']);

        $result = $this->withBuddyNextActive(function () use ($argument) {
            return $this->integration->getDataForChecking($argument);
        });

        $this->assertIsArray($result);
        $this->assertSame('', $result['nickname']);
    }

    public function testDoBlockSendsExpectedJsonStructure()
    {
        $message = 'This email is already registered';

        $this->integration->doBlock($message);

        $this->assertSame(
            array(
                'code' => 'rest_registration_failed',
                'message' => $message,
                'data' => array(
                    'status' => 422,
                    'fields' => array(
                        'email' => $message,
                    ),
                ),
            ),
            $GLOBALS['apbct_test_buddynext_wp_send_json']
        );
    }

    /**
     * Ensures the REST route registry wires BuddyNextRegistration to the correct endpoint/setting.
     */
    public function testIntegrationIsRegisteredForRestRoute()
    {
        $integrations = $this->loadRestIntegrationsRegistry();

        $this->assertArrayHasKey('BuddyNextRegistration', $integrations);
        $this->assertSame('/buddynext/v1/auth/register', $integrations['BuddyNextRegistration']['rest_route']);
        $this->assertSame('forms__registrations_test', $integrations['BuddyNextRegistration']['setting']);
        $this->assertTrue($integrations['BuddyNextRegistration']['rest']);
    }

    /**
     * @return array
     */
    private function loadRestIntegrationsRegistry()
    {
        include CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-integrations-by-route.php';

        return $apbct_active_rest_integrations ?? [];
    }
}
