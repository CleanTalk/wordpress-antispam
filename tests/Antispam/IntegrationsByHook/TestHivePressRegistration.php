<?php

namespace Antispam\IntegrationsByHook;

use Cleantalk\Antispam\Integrations\HivePressRegistration;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use PHPUnit\Framework\TestCase;

class TestHivePressRegistration extends TestCase
{
    protected function tearDown(): void
    {
        // Clean up global state
        $_POST = [];
        $_GET = [];
        Post::getInstance()->variables = [];
        Get::getInstance()->variables = [];
        parent::tearDown();
    }

    public function testGetDataWithoutArgument()
    {
        $integration = $this->getMockBuilder(HivePressRegistration::class)
                            ->onlyMethods(['isThePluginActive'])
                            ->getMock();

        $integration->method('isThePluginActive')->willReturn(true);

        $result = $integration->getDataForChecking(null);

        $this->assertNull($result);
    }

    public function testGetDataForCheckingRegister()
    {
        $incoming_data = ['email' => 's@cleantalk.org'];
        $integration = $this->getMockBuilder(HivePressRegistration::class)
                            ->onlyMethods(['isThePluginActive'])
                            ->getMock();

        $integration->method('isThePluginActive')->willReturn(true);

        $result = $integration->getDataForChecking($incoming_data);

        $this->assertIsArray($result);
        $this->assertEquals($result['email'], $incoming_data['email']);
        $this->assertEquals($result['register'], true);
    }
}
