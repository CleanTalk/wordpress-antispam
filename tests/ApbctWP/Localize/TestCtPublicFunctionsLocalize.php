<?php

use Cleantalk\ApbctWP\Localize\CtPublicFunctionsLocalize;
use Cleantalk\ApbctWP\State;

class TestCtPublicFunctionsLocalize extends \PHPUnit\Framework\TestCase
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

    public function testGetData()
    {
        // Arrange

        // Act
        $localize_data = CtPublicFunctionsLocalize::getData();

        // Assert
        $this->assertArrayHasKey('bot_detector_enabled', $localize_data);
        $this->assertArrayNotHasKey('data__bot_detector_enabled', $localize_data);
    }
}