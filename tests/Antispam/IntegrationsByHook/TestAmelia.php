<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Get;
use PHPUnit\Framework\TestCase;

class ApbctAmeliaPhpInputStub
{
    public static $data = '';
    public $context;
    private $position = 0;

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->position = 0;
        return true;
    }

    public function stream_read($count)
    {
        $chunk = substr(self::$data, $this->position, $count);
        $this->position += strlen($chunk);
        return $chunk;
    }

    public function stream_eof()
    {
        return $this->position >= strlen(self::$data);
    }

    public function stream_stat()
    {
        return array();
    }

    public function url_stat($path, $flags)
    {
        return array();
    }

    public function stream_set_option($option, $arg1, $arg2)
    {
        return true;
    }
}

class TestAmelia extends TestCase
{
    /** @var Amelia */
    private $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integration = new Amelia();
    }

    protected function tearDown(): void
    {
        $_GET = array();
        Get::getInstance()->variables = array();
        ApbctAmeliaPhpInputStub::$data = '';
        parent::tearDown();
    }

    /**
     * Runs getDataForChecking() with a mocked php://input body.
     *
     * @param string $rawBody
     * @return mixed
     */
    private function getDataWithInput($rawBody)
    {
        ApbctAmeliaPhpInputStub::$data = $rawBody;
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', ApbctAmeliaPhpInputStub::class);
        try {
            return $this->integration->getDataForChecking(null);
        } finally {
            stream_wrapper_restore('php');
        }
    }

    public function testDoPrepareActionsReturnsTrueForBookingsCall()
    {
        $_GET['call'] = '/bookings';

        $this->assertTrue($this->integration->doPrepareActions(null));
    }

    public function testDoPrepareActionsReturnsFalseForOtherCall()
    {
        $_GET['call'] = '/appointments';

        $this->assertFalse($this->integration->doPrepareActions(null));
    }

    public function testDoPrepareActionsReturnsFalseWhenCallMissing()
    {
        $this->assertFalse($this->integration->doPrepareActions(null));
    }

    public function testDoPrepareActionsReturnsFalseForArrayCall()
    {
        $_GET['call'] = array('/bookings');
        $this->assertFalse($this->integration->doPrepareActions(null));
    }

    public function testGetDataForCheckingReturnsCustomerEmail()
    {
        $result = $this->getDataWithInput('{"customer":{"email":"customer@example.com"}}');

        $this->assertSame(array('email' => 'customer@example.com'), $result);
    }

    public function testGetDataForCheckingReturnsBookingsCustomerEmail()
    {
        $result = $this->getDataWithInput('{"bookings":[{"customer":{"email":"booking@example.com"}}]}');

        $this->assertSame(array('email' => 'booking@example.com'), $result);
    }

    public function testGetDataForCheckingPrefersCustomerEmail()
    {
        $payload = '{"customer":{"email":"customer@example.com"},"email":"top@example.com"}';

        $result = $this->getDataWithInput($payload);

        $this->assertSame('customer@example.com', $result['email']);
    }

    public function testGetDataForCheckingReturnsNullForEmptyBody()
    {
        $this->assertNull($this->getDataWithInput(''));
    }

    public function testGetDataForCheckingReturnsNullForInvalidJson()
    {
        $this->assertNull($this->getDataWithInput('not a valid json'));
    }
}
