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

    /**
     * Users who already stored blocked orders have to keep the legacy checkout behavior,
     * so the rejection message stays visible for them after the update.
     */
    public function testApbctUpdateTo_6_89_0_EnablesRejectionMessageWhenBlockedOrdersAreStored()
    {
        // Arrange
        global $apbct;
        $apbct->settings['data__wc_store_blocked_orders'] = 1;
        $apbct->settings['forms__wc_show_rejection_message'] = 0;
        $apbct->saveSettings();

        // Act
        apbct_update_to_6_89_0();
        $apbct_rebuilt = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));

        // Assert
        $this->assertEquals(1, $apbct_rebuilt->settings['forms__wc_show_rejection_message']);
    }

    /**
     * Sites without the stored blocked orders get the new silent behavior, so the option stays off.
     */
    public function testApbctUpdateTo_6_89_0_KeepsRejectionMessageOffWhenBlockedOrdersAreNotStored()
    {
        // Arrange
        global $apbct;
        $apbct->settings['data__wc_store_blocked_orders'] = 0;
        $apbct->settings['forms__wc_show_rejection_message'] = 0;
        $apbct->saveSettings();

        // Act
        apbct_update_to_6_89_0();
        $apbct_rebuilt = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));

        // Assert
        $this->assertEquals(0, $apbct_rebuilt->settings['forms__wc_show_rejection_message']);
    }

    /**
     * The migration must not be triggered by an empty or missing setting value.
     */
    public function testApbctUpdateTo_6_89_0_DoesNothingWhenBlockedOrdersSettingIsMissing()
    {
        // Arrange
        global $apbct;
        unset($apbct->settings['data__wc_store_blocked_orders']);
        $apbct->settings['forms__wc_show_rejection_message'] = 0;
        $apbct->saveSettings();

        // Act
        apbct_update_to_6_89_0();
        $apbct_rebuilt = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));

        // Assert
        $this->assertEquals(0, $apbct_rebuilt->settings['forms__wc_show_rejection_message']);
    }

    /**
     * The migration is registered under a version that the updater loop actually reaches.
     */
    public function testApbctUpdateTo_6_89_0_IsReachableByTheUpdaterLoop()
    {
        $this->assertTrue(function_exists('apbct_update_to_6_89_0'));

        $version_arr = apbct_version_standardization('6.89.0');

        $this->assertSame(6, $version_arr[0]);
        $this->assertSame(89, $version_arr[1]);
        $this->assertSame(0, $version_arr[2]);

        // The loop iterates minor versions up to 300, so 6.89.0 is inside the scanned range
        $this->assertLessThanOrEqual(300, $version_arr[1]);
    }
}
