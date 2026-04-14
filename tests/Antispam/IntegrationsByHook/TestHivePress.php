<?php

namespace Antispam\IntegrationsByHook;

use Cleantalk\Antispam\Integrations\HivePress;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use PHPUnit\Framework\TestCase;

class TestHivePress extends TestCase
{
    private $request_payload;

    protected function setUp(): void
    {
        $this->request_payload = [
            'title' => 'Test title',
            'description' => 'Test description',
        ];
    }

    protected function tearDown(): void
    {
        // Clean up global state
        $_POST = [];
        $_GET = [];
        Post::getInstance()->variables = [];
        Get::getInstance()->variables = [];
        parent::tearDown();
    }

    public function testGetDataPluginNotActive()
    {
        $integration = $this->getMockBuilder(HivePress::class)
                            ->onlyMethods(['isThePluginActive', 'isUserLoggedIn'])
                            ->getMock();

        $integration->method('isThePluginActive')->willReturn(false);
        $integration->method('isUserLoggedIn')->willReturn(true);

        $result = $integration->getDataForChecking($this->request_payload);

        $this->assertNull($result);
    }

    public function testGetDataUserNotLoggedIn()
    {
        $integration = $this->getMockBuilder(HivePress::class)
                            ->onlyMethods(['isThePluginActive', 'isUserLoggedIn'])
                            ->getMock();

        $integration->method('isThePluginActive')->willReturn(true);
        $integration->method('isUserLoggedIn')->willReturn(false);

        $result = $integration->getDataForChecking($this->request_payload);

        $this->assertNull($result);
    }

    public function testGetDataProtectionIsNotActive()
    {
        global $apbct;
        $integration = $this->getMockBuilder(HivePress::class)
                            ->onlyMethods(['isThePluginActive', 'isUserLoggedIn'])
                            ->getMock();

        $integration->method('isThePluginActive')->willReturn(true);
        $integration->method('isUserLoggedIn')->willReturn(true);
        $initial_setting_state = $apbct->settings['data__protect_logged_in'];
        $apbct->settings['data__protect_logged_in'] = 0;

        $result = $integration->getDataForChecking($this->request_payload);
        $apbct->settings['data__protect_logged_in'] = $initial_setting_state;

        $this->assertNull($result);
    }

    public function testGetDataUserWithoutEmail()
    {
        $integration = $this->getMockBuilder(HivePress::class)
                            ->onlyMethods(['isThePluginActive', 'isUserLoggedIn', 'getCurrentUser'])
                            ->getMock();

        $integration->method('isThePluginActive')->willReturn(true);
        $integration->method('isUserLoggedIn')->willReturn(true);
        $mock_user = new \stdClass();
        $mock_user->data = new \stdClass();
        $mock_user->data->user_email = '';
        $integration->method('getCurrentUser')->willReturn($mock_user);

        $result = $integration->getDataForChecking($this->request_payload);

        $this->assertIsArray($result);
        $this->assertEquals($result['email'], '');
    }

    public function testGetDataUserWithEmail()
    {
        $integration = $this->getMockBuilder(HivePress::class)
                            ->onlyMethods(['isThePluginActive', 'isUserLoggedIn', 'getCurrentUser'])
                            ->getMock();

        $integration->method('isThePluginActive')->willReturn(true);
        $integration->method('isUserLoggedIn')->willReturn(true);
        $mock_user = new \stdClass();
        $mock_user->data = new \stdClass();
        $mock_user->data->user_email = 's@cleantalk.org';
        $integration->method('getCurrentUser')->willReturn($mock_user);

        $result = $integration->getDataForChecking($this->request_payload);

        $this->assertIsArray($result);
        $this->assertEquals($result['email'], 's@cleantalk.org');
    }

    public function testAllow()
    {
        $integration = new HivePress();

        $allow_result = $integration->allow();

        $this->assertNull($allow_result);
    }

}
