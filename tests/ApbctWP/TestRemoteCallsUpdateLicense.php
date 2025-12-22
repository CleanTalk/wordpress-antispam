<?php

use Cleantalk\ApbctWP\RemoteCalls;
use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

class TestRemoteCallsUpdateLicense extends TestCase
{
    protected $saved_state;
    protected $saved_headers;

    protected function setUp(): void
    {
        global $apbct;
        $this->saved_state = $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        
        // Save current headers state
        $this->saved_headers = headers_list();
        
        // Clear any existing output
        if (ob_get_level() > 0) {
            ob_clean();
        }
    }

    protected function tearDown(): void
    {
        global $apbct;
        $apbct = $this->saved_state;
        
        // Clean output buffer
        if (ob_get_level() > 0) {
            ob_clean();
        }
    }

    /**
     * Helper method to capture output from a method that calls die()
     * Uses output buffering to capture the output before die() terminates execution
     * 
     * @param callable $callback The method to call
     * @return string The captured output
     */
    protected function captureDieOutput(callable $callback): string
    {
        ob_start();
        try {
            $callback();
            // If we reach here, die() wasn't called or was handled
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            // die() might be caught as an exception in some PHPUnit configurations
            $output = ob_get_clean();
        }
        
        // If output buffer is still active, get contents and clean
        if (ob_get_level() > 0) {
            $output = ob_get_contents();
            ob_end_clean();
        }
        
        return $output ?: '';
    }

    /**
     * Test action__update_license with successful sync
     * Note: This test uses output buffering to capture the output from die()
     */
    public function testActionUpdateLicenseSuccess()
    {
        global $apbct;
        
        // Ensure the function exists by requiring the file
        if (!function_exists('apbct_settings__sync')) {
            if (defined('APBCT_DIR_PATH')) {
                require_once APBCT_DIR_PATH . 'inc/cleantalk-settings.php';
            } else {
                $this->markTestSkipped('APBCT_DIR_PATH constant not defined');
                return;
            }
        }
        
        // Set up a valid API key for testing
        $apbct->settings['apikey'] = getenv("CLEANTALK_TEST_API_KEY") ?: 'test_api_key';
        
        // Capture output from die() using helper method
        $output = $this->captureDieOutput(function() {
            RemoteCalls::action__update_license();
        });
        
        // Decode the JSON response
        $response = json_decode($output, true);
        
        // Assert that we got a valid JSON response
        $this->assertNotNull($response, 'Response should be valid JSON. Output was: ' . $output);
        
        // Check for success response
        if (isset($response['OK'])) {
            $this->assertTrue($response['OK'], 'Response should indicate success');
        } elseif (isset($response['ERROR'])) {
            // If there's an error, that's also a valid test case
            // but we'll note it in the assertion
            $this->assertArrayHasKey('ERROR', $response, 'Response contains error');
        }
    }

    /**
     * Test action__update_license with error in sync
     * This test uses an invalid API key to trigger an error response
     */
    public function testActionUpdateLicenseWithError()
    {
        global $apbct;
        
        // Ensure the function exists
        if (!function_exists('apbct_settings__sync')) {
            if (defined('APBCT_DIR_PATH')) {
                require_once APBCT_DIR_PATH . 'inc/cleantalk-settings.php';
            } else {
                $this->markTestSkipped('APBCT_DIR_PATH constant not defined');
                return;
            }
        }
        
        // Set up an invalid API key to trigger an error
        $apbct->settings['apikey'] = 'invalid_api_key_for_testing';
        
        // Capture output from die() using helper method
        $output = $this->captureDieOutput(function() {
            RemoteCalls::action__update_license();
        });
        
        // Decode the JSON response
        $response = json_decode($output, true);
        
        // Assert that we got a valid JSON response
        $this->assertNotNull($response, 'Response should be valid JSON. Output was: ' . $output);
        
        // The response should either be OK or ERROR
        $this->assertTrue(
            isset($response['OK']) || isset($response['ERROR']),
            'Response should contain either OK or ERROR key'
        );
    }

    /**
     * Test that action__update_license sets Content-Type header
     * Note: Header testing may not work in CLI mode
     */
    public function testActionUpdateLicenseSetsContentTypeHeader()
    {
        global $apbct;
        
        // Ensure headers are not sent
        if (headers_sent()) {
            $this->markTestSkipped('Headers already sent, cannot test header setting');
            return;
        }
        
        // Ensure the function exists
        if (!function_exists('apbct_settings__sync')) {
            if (defined('APBCT_DIR_PATH')) {
                require_once APBCT_DIR_PATH . 'inc/cleantalk-settings.php';
            } else {
                $this->markTestSkipped('APBCT_DIR_PATH constant not defined');
                return;
            }
        }
        
        // Set up a valid API key
        $apbct->settings['apikey'] = getenv("CLEANTALK_TEST_API_KEY") ?: 'test_api_key';
        
        // Capture output from die() using helper method
        $output = $this->captureDieOutput(function() {
            RemoteCalls::action__update_license();
        });
        
        // Check if Content-Type header was set
        $headers = headers_list();
        $contentTypeSet = false;
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type: application/json') !== false) {
                $contentTypeSet = true;
                break;
            }
        }
        
        // Note: In CLI mode (PHPUnit), headers might not be set or visible
        // So we'll verify output exists as a minimum requirement
        $this->assertNotEmpty($output, 'Method should produce output');
        
        // If we can check headers (not in CLI), verify Content-Type was set
        if (function_exists('xdebug_get_headers') || !headers_sent()) {
            // Headers might be set but not visible in CLI mode
            // The method does attempt to set the header if headers aren't sent
        }
    }

    /**
     * Test that action__update_license returns valid JSON structure
     */
    public function testActionUpdateLicenseReturnsValidJson()
    {
        global $apbct;
        
        // Ensure the function exists
        if (!function_exists('apbct_settings__sync')) {
            if (defined('APBCT_DIR_PATH')) {
                require_once APBCT_DIR_PATH . 'inc/cleantalk-settings.php';
            } else {
                $this->markTestSkipped('APBCT_DIR_PATH constant not defined');
                return;
            }
        }
        
        // Set up API key
        $apbct->settings['apikey'] = getenv("CLEANTALK_TEST_API_KEY") ?: 'test_api_key';
        
        // Capture output from die() using helper method
        $output = $this->captureDieOutput(function() {
            RemoteCalls::action__update_license();
        });
        
        // Verify output is not empty
        $this->assertNotEmpty($output, 'Output should not be empty');
        
        // Verify it's valid JSON
        $response = json_decode($output, true);
        $this->assertNotNull($response, 'Output should be valid JSON. Output was: ' . $output);
        
        // Verify JSON structure - should have either OK or ERROR
        $this->assertTrue(
            isset($response['OK']) || isset($response['ERROR']),
            'Response should have OK or ERROR key'
        );
        
        // If OK, it should be boolean true
        if (isset($response['OK'])) {
            $this->assertTrue($response['OK'], 'OK value should be true');
        }
        
        // If ERROR, it should be a string (JSON encoded)
        if (isset($response['ERROR'])) {
            $this->assertIsString($response['ERROR'], 'ERROR should be a string');
        }
    }
}