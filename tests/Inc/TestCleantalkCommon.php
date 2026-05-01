<?php

namespace Inc;

use Cleantalk\ApbctWP\ApbctConstant;
use Cleantalk\ApbctWP\ServiceConstants;
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

    public function testApbctIsBotDetectorEnabledByConstant()
    {
        // Arrange
        global $apbct;
        $apbct_constant_mock = $this->getMockBuilder(ApbctConstant::class)
                     ->onlyMethods(['isDefined', 'getValue'])
                     ->disableOriginalConstructor()
                     ->getMock();

        $apbct_constant_mock->method('isDefined')
             ->willReturn(true);

        $apbct_constant_mock->method('getValue')
                               ->willReturn(true);
        $apbct->service_constants = new ServiceConstants();
        $apbct->service_constants->bot_detector_enabled = $apbct_constant_mock;

        // Act
        $bot_detector_state = apbct__is_bot_detector_enabled();

        // Assert
        $this->assertTrue($bot_detector_state);
    }

    public function testApbctIsBotDetectorDisabledByConstant()
    {
        // Arrange
        global $apbct;
        $apbct_constant_mock = $this->getMockBuilder(ApbctConstant::class)
                                    ->onlyMethods(['isDefined', 'getValue'])
                                    ->disableOriginalConstructor()
                                    ->getMock();

        $apbct_constant_mock->method('isDefined')
                            ->willReturn(true);

        $apbct_constant_mock->method('getValue')
                            ->willReturn(false);
        $apbct->service_constants = new ServiceConstants();
        $apbct->service_constants->bot_detector_enabled = $apbct_constant_mock;

        // Act
        $bot_detector_state = apbct__is_bot_detector_enabled();

        // Assert
        $this->assertFalse($bot_detector_state);
    }
}
