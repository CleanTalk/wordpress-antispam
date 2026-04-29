<?php

namespace Inc;

use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

class TestCleantalkPublic extends TestCase
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

    public function testApbctInitPixelUrlLoad()
    {
        // Arrange
        global $apbct;
        $apbct->data['bot_detector_enabled'] = 0;
        $apbct->settings['data__pixel'] = '3';

        // Act
        apbct_init();

        // Assert
        $this->assertNotNull($apbct->pixel_url);
    }

    public function testApbctInitPixelUrlNotLoad()
    {
        // Arrange
        global $apbct;
        $apbct->data['bot_detector_enabled'] = 1;
        $apbct->settings['data__pixel'] = '3';

        // Act
        apbct_init();

        // Assert
        $this->assertNull($apbct->pixel_url);
    }

    public function testApbctHookWpFooterShowPixel()
    {
        // Arrange
        global $apbct;
        $apbct->data['bot_detector_enabled'] = 0;
        $apbct->settings['data__pixel'] = '3';

        // Act
        ob_start();
        apbct_hook__wp_footer();
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('img alt="Cleantalk Pixel" title="Cleantalk Pixel" id="apbct_pixel" style="display: none;"', $output);
    }
}