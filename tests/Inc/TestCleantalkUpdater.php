<?php
use Cleantalk\ApbctWP\State;

class TestCleantalkUpdater extends ApbctTestCase
{
    public function testApbctUpdateTo_6_76_0()
    {
        // Arrange
        global $apbct;
        $apbct->settings['data__bot_detector_enabled'] = 1;

        // Act
        apbct_update_to_6_76_0();
        $apbct_rebuilt = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));

        // Assert
        $this->assertEquals('1', $apbct_rebuilt->data['bot_detector_enabled']);
    }
}
