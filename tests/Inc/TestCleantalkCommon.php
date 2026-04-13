<?php

namespace Inc;

use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

class TestCleantalkCommon extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        global $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
    }

    protected function tearDown(): void
    {
        global $apbct;
        unset($apbct);
        parent::tearDown();
    }

    public function testApbctIsBotDetectorEnabledByDefault()
    {
        // Arrange empty

        // Act
        $bot_detector_state = apbct__is_bot_detector_enabled();

        // Assert
        $this->assertTrue($bot_detector_state);
    }

    public function testApbctIsBotDetectorDisabledByData()
    {
        // Arrange empty
        global $apbct;
        $apbct->data['bot_detector_enabled'] = 0;

        // Act
        $bot_detector_state = apbct__is_bot_detector_enabled();

        // Assert
        $this->assertFalse($bot_detector_state);
    }
}
