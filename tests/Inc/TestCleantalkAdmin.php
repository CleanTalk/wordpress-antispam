<?php

namespace Inc;

use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

class TestCleantalkAdmin extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-admin.php');
        global $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));

        wp_scripts()->remove('cleantalk-admin-js');
    }

    protected function tearDown(): void
    {
        global $apbct;
        unset($apbct);
        wp_scripts()->remove('cleantalk-admin-js');
        parent::tearDown();
    }

    /**
     * Pulls the `ctAdminCommon` payload that `apbct_admin__enqueue_scripts` registers
     * via `wp_localize_script`, decoded back to a PHP array.
     */
    private function getCtAdminCommonData()
    {
        apbct_admin__enqueue_scripts('index.php');

        $raw = wp_scripts()->get_data('cleantalk-admin-js', 'data');
        $this->assertIsString($raw, 'ctAdminCommon was not localized on cleantalk-admin-js');

        preg_match('/var ctAdminCommon = (.*);$/s', $raw, $matches);
        $this->assertNotEmpty($matches, 'Could not extract ctAdminCommon JSON payload');

        return json_decode($matches[1], true);
    }

    public function testCtAdminCommonContainsLinksArray()
    {
        $data = $this->getCtAdminCommonData();

        $this->assertArrayHasKey('links', $data);
        $this->assertIsArray($data['links']);
        $this->assertArrayHasKey('users_editscreen', $data['links']);
        $this->assertArrayHasKey('comments_editscreen', $data['links']);
    }

    public function testCtAdminCommonLinksUsersEditscreen()
    {
        $data = $this->getCtAdminCommonData();

        $expected = 'https://cleantalk.org/blacklists/{TARGET}?utm_source=admin_side&utm_medium=comments&utm_content=avatar&utm_campaign=apbct_links';
        $this->assertSame($expected, $data['links']['users_editscreen']);
    }

    public function testCtAdminCommonLinksCommentsEditscreen()
    {
        $data = $this->getCtAdminCommonData();

        $expected = 'https://cleantalk.org/blacklists/{TARGET}?utm_source=admin_side&utm_medium=comments&utm_content=avatar&utm_campaign=apbct_links';
        $this->assertSame($expected, $data['links']['comments_editscreen']);
    }
}
