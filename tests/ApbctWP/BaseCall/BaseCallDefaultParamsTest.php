<?php

namespace tests\Cleantalk\ApbctWP\BaseCall;

use PHPUnit\Framework\TestCase;
use Cleantalk\ApbctWP\BaseCall\DefaultParams;

class BaseCallDefaultParamsTest extends TestCase
{
    /**
     * Базовый helper для создания мока
     */
    private function createMockedInstance($authKey = 'test_key', $senderInfo = [])
    {
        return $this->getMockBuilder(DefaultParams::class)
            ->setConstructorArgs([$authKey, $senderInfo])
            ->onlyMethods([
                'getSenderIP',
                'getXForwardedForIP',
                'getXRealIP',
                'getJsOn',
                'getSubmitTime',
                'getAgent',
                'getTestIp',
                'ipGet',
            ])
            ->getMock();
    }

    public function testGetSenderIPWithTestIp()
    {
        $obj = $this->getMockBuilder(DefaultParams::class)
            ->setConstructorArgs(['test_key', []])
            ->onlyMethods([
                'getTestIp',
                'getSenderIP',
            ])
            ->getMock();

        $obj->method('getTestIp')
            ->willReturn(null);

        $obj->method('getSenderIP')
            ->willReturn('1.1.1.1');

        $this->assertEquals('1.1.1.1', $obj->get()['sender_ip']);
    }

    public function testGetSenderIPWithoutTestIp()
    {
        $obj = $this->getMockBuilder(DefaultParams::class)
            ->setConstructorArgs(['test_key', []])
            ->onlyMethods([
                'getTestIp',
            ])
            ->getMock();

        $obj->method('getTestIp')
            ->willReturn('1.2.3.4');

        $this->assertEquals('1.2.3.4', $obj->get()['sender_ip']);
    }

    public function testGetXForwardedForIP()
    {
        $obj = $this->getMockBuilder(DefaultParams::class)
            ->setConstructorArgs(['test_key', []])
            ->onlyMethods([
                'getXForwardedForIP',
            ])
            ->getMock();

        $obj->method('getXForwardedForIP')
            ->willReturn('10.0.0.1');

        $this->assertEquals('10.0.0.1', $obj->get()['x_forwarded_for']);
    }

    public function testGetXRealIP()
    {
        $obj = $this->getMockBuilder(DefaultParams::class)
            ->setConstructorArgs(['test_key', []])
            ->onlyMethods([
                'getXRealIP',
            ])
            ->getMock();

        $obj->method('getXRealIP')
            ->willReturn('10.0.0.2');

        $this->assertEquals('10.0.0.2', $obj->get()['x_real_ip']);
    }

    public function testGetJsOn()
    {
        $obj = $this->getMockBuilder(DefaultParams::class)
            ->setConstructorArgs(['test_key', []])
            ->onlyMethods([
                'getJsOn',
            ])
            ->getMock();

        $obj->method('getJsOn')->willReturn(1);

        $this->assertEquals(1, $obj->get()['js_on']);
    }

    public function testGetJsOnFail()
    {
        $obj = $this->getMockBuilder(DefaultParams::class)
            ->setConstructorArgs(['test_key', []])
            ->onlyMethods([
                'getJsOn',
            ])
            ->getMock();

        $obj->method('getJsOn')->willReturn(0);

        $this->assertEquals(0, $obj->get()['js_on']);

        $obj->method('getJsOn')->willReturn(null);

        $this->assertEquals(null, $obj->get()['js_on']);
    }

    public function testGetSubmittime()
    {
        $obj = $this->getMockBuilder(DefaultParams::class)
            ->setConstructorArgs(['test_key', []])
            ->onlyMethods([
                'getSubmitTime',
            ])
            ->getMock();

        $obj->method('getSubmitTime')
            ->willReturn(123);

        $this->assertEquals(123, $obj->get()['submit_time']);
    }

    public function testGetIP()
    {
        $obj = $this->getMockBuilder(DefaultParams::class)
            ->setConstructorArgs(['test_key', []])
            ->onlyMethods([
                'ipGet',
            ])
            ->getMock();

        $obj->method('ipGet')->with('remote_addr')->willReturn('1.2.3.4');

        $this->assertEquals('1.2.3.4', $obj->getSenderIP());
    }

    public function testGetFullData()
    {
        $senderInfo = ['foo' => 'bar'];

        $obj = $this->getMockBuilder(DefaultParams::class)
            ->setConstructorArgs(['test_key', $senderInfo])
            ->onlyMethods([
                'getSenderIP',
                'getXForwardedForIP',
                'getXRealIP',
                'getJsOn',
                'getSubmitTime',
                'getAgent',
                'getTestIp',
                'ipGet',
            ])
            ->getMock();

        $obj->method('getTestIp')->willReturn(null);
        $obj->method('getSenderIP')->willReturn('1.2.3.4');
        $obj->method('getAgent')->willReturn('test-agent');
        $obj->method('getSubmitTime')->willReturn(999);
        $obj->method('getXRealIP')->willReturn('10.0.0.2');
        $obj->method('getXForwardedForIP')->willReturn('10.0.0.1');
        $obj->method('getJsOn')->willReturn(1);
        $obj->method('getSubmitTime')->willReturn(true);

        $result = $obj->get();

        $this->assertEquals([
            'sender_ip'       => '1.2.3.4',
            'x_forwarded_for' => '10.0.0.1',
            'x_real_ip'       => '10.0.0.2',
            'auth_key'        => 'test_key',
            'js_on'           => 1,
            'agent'           => 'test-agent',
            'sender_info'     => $senderInfo,
            'submit_time'     => 999,
        ], $result);
    }
}
