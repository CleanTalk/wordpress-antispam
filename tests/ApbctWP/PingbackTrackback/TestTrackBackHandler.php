<?php

namespace ApbctWP\PingbackTrackback;

use Cleantalk\ApbctWP\PingbackTrackback\TrackbackHandler;
use PHPUnit\Framework\TestCase;

class TestTrackBackHandler extends TestCase
{

    public function testRegisterTrackbackHook()
    {
        $handler = new TrackbackHandler();
        $this->assertNotFalse(
            has_action(
                'pre_trackback_post',
                [$handler, 'blockTrackback']
            )
        );
    }
}
