<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use PHPUnit\Framework\TestCase;

class TestEasyDigitalDownloads extends TestCase
{
    private $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integration = new EasyDigitalDownloads();
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

    public function testGetDataForCheckingRegisterPage()
    {
        $_POST['edd_action'] = 'user_register';
        $_POST['edd_user_login'] = 'John';
        $_POST['edd_user_email'] = 'john.doe@example.com';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('john.doe@example.com', $result['email']);
        $this->assertEquals('', $result['nickname']); // NickName not detected at that integration
        $this->assertTrue($result['register']);
    }

    public function testGetDataForCheckingRegisterDuringCheckout()
    {
        $_POST['edd-process-checkout-nonce'] = 'user_register';
        $_POST['edd_first'] = 'John';
        $_POST['edd_last'] = 'Doe';
        $_POST['edd_email'] = 'john.doe@example.com';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('john.doe@example.com', $result['email']);
        $this->assertEquals('', $result['nickname']); // NickName not detected at that integration
        $this->assertTrue($result['register']);
    }

    public function testGetDataForCheckingRegisterCommon()
    {
        $_POST['edd_user_login'] = 'John';
        $_POST['edd_user_email'] = 'john.doe@example.com';

        $result = $this->integration->getDataForChecking(['user_email' => 'any_email_no_sense']);

        $this->assertIsArray($result);
        $this->assertEquals('john.doe@example.com', $result['email']);
        $this->assertEquals('', $result['nickname']); // NickName not detected at that integration
        $this->assertTrue($result['register']);
    }
}
