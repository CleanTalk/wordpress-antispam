<?php

use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests for capability checks in settings AJAX handlers.
 * Verifies that functions properly check current_user_can('activate_plugins').
 *
 * Tests verify:
 * 1. Functions deny access to users without activate_plugins capability
 * 2. Source code contains required checks (static analysis)
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

    /**
     * @var string Path to the admin file
     */
    private $adminFilePath;

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
        $this->adminFilePath = CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-admin.php';

        // Set up wp_die handler to throw an exception
        add_filter('wp_die_handler', function() {
            return function($message) {
                throw new WPDieException($message);
            };
        });

        // Also handle AJAX die
        add_filter('wp_die_ajax_handler', function() {
            return function($message) {
                throw new WPDieException($message);
            };
        });
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

        // Remove filters
        remove_all_filters('wp_die_handler');
        remove_all_filters('wp_die_ajax_handler');
    }

    // ==================== Real Function Call Tests ====================
    // These tests call functions directly and verify they deny access

    /**
     * Test apbct_settings__check_renew_banner denies access for subscriber
     */
    public function testCheckRenewBannerDeniesAccessForSubscriber()
    {
        wp_set_current_user(self::$subscriber_id);

        // Create valid nonce for ct_secret_nonce (admin nonce) while logged in as subscriber
        $nonce = wp_create_nonce('ct_secret_nonce');
        $_REQUEST['_wpnonce'] = $nonce;
        $_POST['_wpnonce'] = $nonce;
        Post::getInstance()->variables['_wpnonce'] = $nonce;
        Request::getInstance()->variables['_wpnonce'] = $nonce;

        $output = null;

        try {
            ob_start();
            apbct_settings__check_renew_banner();
            $output = ob_get_clean();
        } catch (WPDieException $e) {
            $output = ob_get_clean();
            $output .= $e->getMessage();
        }

        $this->assertNotEmpty($output);
        $result = json_decode($output, true);

        $this->assertIsArray($result, 'Output should be valid JSON');
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success'], 'Should return success=false for unauthorized access');
    }

    /**
     * Test apbct_settings__get__long_description denies access for subscriber
     */
    public function testGetLongDescriptionDeniesAccessForSubscriber()
    {
        wp_set_current_user(self::$subscriber_id);

        // Create valid nonce for ct_secret_nonce (admin nonce) while logged in as subscriber
        $nonce = wp_create_nonce('ct_secret_nonce');
        $_REQUEST['_wpnonce'] = $nonce;
        $_POST['_wpnonce'] = $nonce;
        Post::getInstance()->variables['_wpnonce'] = $nonce;
        Request::getInstance()->variables['_wpnonce'] = $nonce;

        $output = null;

        try {
            ob_start();
            apbct_settings__get__long_description();
            $output = ob_get_clean();
        } catch (WPDieException $e) {
            $output = ob_get_clean();
            $output .= $e->getMessage();
        }

        $this->assertNotEmpty($output);
        $result = json_decode($output, true);

        $this->assertIsArray($result, 'Output should be valid JSON');
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success'], 'Should return success=false for unauthorized access');
    }

    /**
     * Test apbct_settings__get_key_auto denies access for subscriber
     */
    public function testGetKeyAutoDeniesAccessForSubscriber()
    {
        wp_set_current_user(self::$subscriber_id);

        // Create valid nonce for ct_secret_nonce (admin nonce) while logged in as subscriber
        $nonce = wp_create_nonce('ct_secret_nonce');
        $_REQUEST['_wpnonce'] = $nonce;
        $_POST['_wpnonce'] = $nonce;
        Post::getInstance()->variables['_wpnonce'] = $nonce;
        Request::getInstance()->variables['_wpnonce'] = $nonce;

        $output = null;

        try {
            ob_start();
            apbct_settings__get_key_auto();
            $output = ob_get_clean();
        } catch (WPDieException $e) {
            $output = ob_get_clean();
            $output .= $e->getMessage();
        }

        $this->assertNotEmpty($output);
        $result = json_decode($output, true);

        $this->assertIsArray($result, 'Output should be valid JSON');
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success'], 'Should return success=false for unauthorized access');
    }

    /**
     * Test apbct_action__create_support_user denies access for subscriber
     * Note: Uses wp_send_json_error which is harder to test, verified via static analysis below
     */
    public function testCreateSupportUserDeniesAccessForSubscriberStatic()
    {
        $this->assertFunctionHasCapabilityCheck(
            'apbct_action__create_support_user',
            $this->adminFilePath
        );
    }

    /**
     * Test apbct_action_adjust_change denies access for subscriber
     * Note: Uses wp_send_json_error which is harder to test, verified via static analysis below
     */
    public function testAdjustChangeDeniesAccessForSubscriberStatic()
    {
        $this->assertFunctionHasCapabilityCheck(
            'apbct_action_adjust_change',
            $this->adminFilePath
        );
    }

    /**
     * Test apbct_action_adjust_reverse denies access for subscriber
     * Note: Uses wp_send_json_error which is harder to test, verified via static analysis below
     */
    public function testAdjustReverseDeniesAccessForSubscriberStatic()
    {
        $this->assertFunctionHasCapabilityCheck(
            'apbct_action_adjust_reverse',
            $this->adminFilePath
        );
    }

    // ==================== Source Code Verification Tests ====================
    // These tests verify that the required capability checks exist in source code

    /**
     * Test that apbct_settings__check_renew_banner contains capability check
     */
    public function testCheckRenewBannerHasCapabilityCheck()
    {
        $this->assertFunctionHasCapabilityCheck(
            'apbct_settings__check_renew_banner',
            $this->settingsFilePath
        );
    }

    /**
     * Test that apbct_settings__get__long_description contains capability check
     */
    public function testGetLongDescriptionHasCapabilityCheck()
    {
        $this->assertFunctionHasCapabilityCheck(
            'apbct_settings__get__long_description',
            $this->settingsFilePath
        );
    }

    /**
     * Test that apbct_settings__get_key_auto contains capability check
     */
    public function testGetKeyAutoHasCapabilityCheck()
    {
        $this->assertFunctionHasCapabilityCheck(
            'apbct_settings__get_key_auto',
            $this->settingsFilePath
        );
    }

    /**
     * Helper method to check if a function contains capability check in source code
     *
     * @param string $functionName Function name to check
     * @param string $filePath Path to the source file
     */
    private function assertFunctionHasCapabilityCheck($functionName, $filePath)
    {
        $fileContent = file_get_contents($filePath);

        // Extract function body
        $functionBody = $this->extractFunctionBody($fileContent, $functionName);
        $this->assertNotEmpty($functionBody, "Could not extract function body for $functionName");

        // Check for capability check
        $hasCapabilityCheck = strpos($functionBody, "current_user_can('activate_plugins')") !== false
            || strpos($functionBody, 'current_user_can("activate_plugins")') !== false;

        $this->assertTrue(
            $hasCapabilityCheck,
            "$functionName should contain current_user_can('activate_plugins') check"
        );

        // Check that it returns/dies on failure
        $hasProperHandling = strpos($functionBody, 'die(') !== false
            || strpos($functionBody, 'wp_die(') !== false
            || strpos($functionBody, 'wp_send_json_error') !== false;

        $this->assertTrue(
            $hasProperHandling,
            "$functionName should terminate on capability check failure"
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
}
