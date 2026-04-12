<?php

namespace Inc;

use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

class TestCleantalkUpdater extends TestCase
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

    public function testApbctUpdateTo_6_76_0()
    {
        // Arrange
        global $apbct;
        $apbct->settings['data__bot_detector_enabled'] = '1';

        // Act
        apbct_update_to_6_76_0();

        // Assert
        $this->assertEquals('1', $apbct->data['bot_detector_enabled']);
    }
}
