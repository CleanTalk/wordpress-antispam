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
        // Arrange
        global $apbct;
        $apbct->data['bot_detector_enabled'] = 0;

        // Act
        $bot_detector_state = apbct__is_bot_detector_enabled();

        // Assert
        $this->assertFalse($bot_detector_state);
    }

    public function testApbctIsBotDetectorEnabledByDataTrue()
    {
        // Arrange
        global $apbct;
        $apbct->data['bot_detector_enabled'] = 1;

        // Act
        $bot_detector_state = apbct__is_bot_detector_enabled();

        // Assert
        $this->assertTrue($bot_detector_state);
    }

    /*
     * The "service constant wins over $apbct->data" cases used to live here, mocking ApbctConstant
     * and injecting it into the state. Constant:: is static, so there is nothing to inject, and
     * defining APBCT_SERVICE__BOT_DETECTOR_ENABLED for real is not an option either: it is
     * process-global and would override the data-driven cases above and in TestCleantalkPublic.
     * Constant resolution itself is covered by TestConstant.
     */
}
