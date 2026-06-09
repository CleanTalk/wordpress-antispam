<?php

namespace ApbctWP\PingbackTrackback;

use Cleantalk\ApbctWP\PingbackTrackback\SettingsView;
use PHPUnit\Framework\TestCase;

class TestSettingsView extends TestCase
{

    public function testSettingsViewReturnsNonEmptyStrings()
    {
        $this->assertIsString(
            SettingsView::getTitle()
        );
        $this->assertIsString(
            SettingsView::getDescription()
        );
        $this->assertIsString(
            SettingsView::getLongDescription()
        );
        $this->assertNotEmpty(
            SettingsView::getTitle()
        );
        $this->assertNotEmpty(
            SettingsView::getDescription()
        );
        $this->assertNotEmpty(
            SettingsView::getLongDescription()
        );
        $this->assertStringContainsString('<p>',
            SettingsView::getLongDescription()
        );
    }
}
