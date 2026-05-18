<?php

use Cleantalk\ApbctWP\Localize\CtPublicLocalize;
use Cleantalk\ApbctWP\State;

class TestCtPublicLocalize extends \PHPUnit\Framework\TestCase
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
        $localize_data = CtPublicLocalize::getData();

        // Assert
        $this->assertArrayHasKey('bot_detector_enabled', $localize_data);
        $this->assertArrayNotHasKey('data__bot_detector_enabled', $localize_data);
    }

    public function testGetDataContainsLinksArray()
    {
        $localize_data = CtPublicLocalize::getData();

        $this->assertArrayHasKey('links', $localize_data);
        $this->assertIsArray($localize_data['links']);
        $this->assertArrayHasKey('users_editscreen', $localize_data['links']);
        $this->assertArrayHasKey('comments_editscreen', $localize_data['links']);
    }

    public function testGetDataLinksUsersEditscreen()
    {
        $localize_data = CtPublicLocalize::getData();

        $expected = 'https://cleantalk.org/blacklists/{TARGET}?utm_source=admin_side&utm_medium=comments&utm_content=avatar&utm_campaign=apbct_links';
        $this->assertSame($expected, $localize_data['links']['users_editscreen']);
    }

    public function testGetDataLinksCommentsEditscreen()
    {
        $localize_data = CtPublicLocalize::getData();

        $expected = 'https://cleantalk.org/blacklists/{TARGET}?utm_source=admin_side&utm_medium=comments&utm_content=avatar&utm_campaign=apbct_links';
        $this->assertSame($expected, $localize_data['links']['comments_editscreen']);
    }
}