<?php
namespace Inc;

use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests for capability checks in settings AJAX handlers.
 * Verifies that functions properly check current_user_can('activate_plugins').
 */
class TestSettingsCapabilityCheck extends TestCase
{
    /**
     * @var int User ID for subscriber (no activate_plugins capability)
     */
    private static $subscriber_id;

    /**
     * @var int User ID for administrator (has activate_plugins capability)
     */
    private static $admin_id;

    /**
     * @var mixed Backup of global $apbct
     */
    private $apbctBackup;

    /**
     * @var string Path to the settings file
     */
    private $settingsFilePath;

    public static function setUpBeforeClass(): void
    {
        // Create a subscriber user (no activate_plugins capability)
        self::$subscriber_id = wp_insert_user([
            'user_login' => 'test_subscriber_' . wp_generate_password(6, false),
            'user_pass'  => wp_generate_password(),
            'user_email' => 'subscriber_' . wp_generate_password(6, false) . '@test.com',
            'role'       => 'subscriber',
        ]);

        // Create an administrator user (has activate_plugins capability)
        self::$admin_id = wp_insert_user([
            'user_login' => 'test_admin_' . wp_generate_password(6, false),
            'user_pass'  => wp_generate_password(),
            'user_email' => 'admin_' . wp_generate_password(6, false) . '@test.com',
            'role'       => 'administrator',
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up users
        if (self::$subscriber_id && !is_wp_error(self::$subscriber_id)) {
            wp_delete_user(self::$subscriber_id);
        }
        if (self::$admin_id && !is_wp_error(self::$admin_id)) {
            wp_delete_user(self::$admin_id);
        }

        Post::getInstance()->variables = [];
        Request::getInstance()->variables = [];
        $_POST = [];
        $_REQUEST = [];
    }

    protected function setUp(): void
    {
        global $apbct;
        $this->apbctBackup = $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $this->settingsFilePath = CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-settings.php';
    }

    protected function tearDown(): void
    {
        global $apbct;
        $apbct = $this->apbctBackup;

        // Reset current user
        wp_set_current_user(0);

        // Clear POST/REQUEST
        Post::getInstance()->variables = [];
        Request::getInstance()->variables = [];
        $_POST = [];
        $_REQUEST = [];
    }

    /**
     * Test that administrator has activate_plugins capability
     */
    public function testAdminHasActivatePluginsCapability()
    {
        wp_set_current_user(self::$admin_id);

        $this->assertTrue(
            current_user_can('activate_plugins'),
            'Administrator should have activate_plugins capability'
        );
    }

    /**
     * Test that subscriber does NOT have activate_plugins capability
     */
    public function testSubscriberDoesNotHaveActivatePluginsCapability()
    {
        wp_set_current_user(self::$subscriber_id);

        $this->assertFalse(
            current_user_can('activate_plugins'),
            'Subscriber should NOT have activate_plugins capability'
        );
    }

    /**
     * Test that anonymous user does NOT have activate_plugins capability
     */
    public function testAnonymousDoesNotHaveActivatePluginsCapability()
    {
        wp_set_current_user(0);

        $this->assertFalse(
            current_user_can('activate_plugins'),
            'Anonymous user should NOT have activate_plugins capability'
        );
    }

    /**
     * Test that apbct_settings__check_renew_banner contains capability check
     */
    public function testCheckRenewBannerHasCapabilityCheck()
    {
        $this->assertFunctionHasCapabilityCheck(
            'apbct_settings__check_renew_banner',
            'apbct_settings__check_renew_banner should contain current_user_can(\'activate_plugins\') check'
        );
    }

    /**
     * Test that apbct_settings__get__long_description contains capability check
     */
    public function testGetLongDescriptionHasCapabilityCheck()
    {
        $this->assertFunctionHasCapabilityCheck(
            'apbct_settings__get__long_description',
            'apbct_settings__get__long_description should contain current_user_can(\'activate_plugins\') check'
        );
    }

    /**
     * Test that apbct_settings__get_key_auto contains capability check
     */
    public function testGetKeyAutoHasCapabilityCheck()
    {
        $this->assertFunctionHasCapabilityCheck(
            'apbct_settings__get_key_auto',
            'apbct_settings__get_key_auto should contain current_user_can(\'activate_plugins\') check'
        );
    }

    /**
     * Helper method to check if a function contains capability check
     *
     * @param string $functionName Function name to check
     * @param string $message Assertion message
     */
    private function assertFunctionHasCapabilityCheck($functionName, $message)
    {
        $fileContent = file_get_contents($this->settingsFilePath);

        // Find the function definition
        $pattern = '/function\s+' . preg_quote($functionName, '/') . '\s*\([^)]*\)\s*\{/';
        $this->assertRegExp($pattern, $fileContent, "Function $functionName should exist");

        // Extract function body
        $functionBody = $this->extractFunctionBody($fileContent, $functionName);
        $this->assertNotEmpty($functionBody, "Could not extract function body for $functionName");

        // Check for capability check
        $hasCapabilityCheck = strpos($functionBody, "current_user_can('activate_plugins')") !== false
            || strpos($functionBody, 'current_user_can("activate_plugins")') !== false;

        $this->assertTrue($hasCapabilityCheck, $message);

        // Check that it returns/dies on failure
        $hasProperHandling = strpos($functionBody, 'die(') !== false
            || strpos($functionBody, 'wp_die(') !== false
            || strpos($functionBody, 'return') !== false;

        $this->assertTrue(
            $hasProperHandling,
            "$functionName should terminate or return on capability check failure"
        );
    }

    /**
     * Extract function body from file content
     *
     * @param string $fileContent Full file content
     * @param string $functionName Function name
     * @return string Function body
     */
    private function extractFunctionBody($fileContent, $functionName)
    {
        $pattern = '/function\s+' . preg_quote($functionName, '/') . '\s*\([^)]*\)\s*\{/';

        if (!preg_match($pattern, $fileContent, $matches, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $startPos = $matches[0][1];
        $braceCount = 0;
        $inFunction = false;
        $functionBody = '';

        for ($i = $startPos; $i < strlen($fileContent); $i++) {
            $char = $fileContent[$i];

            if ($char === '{') {
                $braceCount++;
                $inFunction = true;
            } elseif ($char === '}') {
                $braceCount--;
            }

            if ($inFunction) {
                $functionBody .= $char;
            }

            if ($inFunction && $braceCount === 0) {
                break;
            }
        }

        return $functionBody;
    }

    /**
     * Test capability check logic works correctly for subscriber
     * This verifies that when a subscriber makes the check, it returns false
     */
    public function testCapabilityCheckLogicForSubscriber()
    {
        wp_set_current_user(self::$subscriber_id);

        // Simulate the check that is in the functions
        $hasCapability = current_user_can('activate_plugins');

        $this->assertFalse(
            $hasCapability,
            'Capability check should return false for subscriber, blocking access'
        );
    }

    /**
     * Test capability check logic works correctly for admin
     * This verifies that when an admin makes the check, it returns true
     */
    public function testCapabilityCheckLogicForAdmin()
    {
        wp_set_current_user(self::$admin_id);

        // Simulate the check that is in the functions
        $hasCapability = current_user_can('activate_plugins');

        $this->assertTrue(
            $hasCapability,
            'Capability check should return true for admin, allowing access'
        );
    }

    /**
     * Test that editor role does NOT have activate_plugins capability
     */
    public function testEditorDoesNotHaveActivatePluginsCapability()
    {
        $editor_id = wp_insert_user([
            'user_login' => 'test_editor_' . wp_generate_password(6, false),
            'user_pass'  => wp_generate_password(),
            'user_email' => 'editor_' . wp_generate_password(6, false) . '@test.com',
            'role'       => 'editor',
        ]);

        wp_set_current_user($editor_id);

        $this->assertFalse(
            current_user_can('activate_plugins'),
            'Editor should NOT have activate_plugins capability'
        );

        wp_delete_user($editor_id);
    }
}
