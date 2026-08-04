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

    /**
     * The service constant wins over $apbct->data. A PHP constant cannot be undefined once set,
     * so each case gets its own process.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testApbctIsBotDetectorEnabledByConstant()
    {
        // Arrange
        global $apbct;
        $apbct->data['bot_detector_enabled'] = 0;
        define('APBCT_SERVICE__BOT_DETECTOR_ENABLED', true);

        // Act
        $bot_detector_state = apbct__is_bot_detector_enabled();

        // Assert
        $this->assertTrue($bot_detector_state);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testApbctIsBotDetectorDisabledByConstant()
    {
        // Arrange
        global $apbct;
        $apbct->data['bot_detector_enabled'] = 1;
        define('APBCT_SERVICE__BOT_DETECTOR_ENABLED', false);

        // Act
        $bot_detector_state = apbct__is_bot_detector_enabled();

        // Assert
        $this->assertFalse($bot_detector_state);
    }
}
