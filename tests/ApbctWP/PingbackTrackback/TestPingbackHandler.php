<?php

namespace ApbctWP\PingbackTrackback;

use Cleantalk\ApbctWP\PingbackTrackback\PingbackHandler;
use PHPUnit\Framework\TestCase;

class TestPingbackHandler extends TestCase
{
    public function testRegisterPingbackBlockReplacesPingbackMethod()
    {
        $handler = new PingbackHandler();

        $methods = [
            'pingback.ping' => 'old_callback',
        ];

        $result = $handler->registerPingbackBlock($methods);

        $this->assertSame(
            [$handler, 'blockPingback'],
            $result['pingback.ping']
        );
    }

    public function testRegisterPingbackBlockReplacesGetPingbacksMethod()
    {
        $handler = new PingbackHandler();

        $methods = [
            'pingback.extensions.getPingbacks' => 'old_callback',
        ];

        $result = $handler->registerPingbackBlock($methods);

        $this->assertSame(
            [$handler, 'blockPingback'],
            $result['pingback.extensions.getPingbacks']
        );
    }

    public function testRegisterPingbackBlockLeavesOtherMethodsUntouched()
    {
        $handler = new PingbackHandler();

        $methods = [
            'wp.getUsersBlogs' => 'callback',
        ];

        $result = $handler->registerPingbackBlock($methods);

        $this->assertSame(
            'callback',
            $result['wp.getUsersBlogs']
        );
    }

    public function testBlockPingbackReturnsIXRError()
    {
        $handler = new PingbackHandler();

        $result = $handler->blockPingback();

        $this->assertInstanceOf(\IXR_Error::class, $result);
        $this->assertEquals(19, $result->code);
        $this->assertEquals(
            'Pingbacks are disabled',
            $result->message
        );
    }
}
