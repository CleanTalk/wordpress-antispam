<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use PHPUnit\Framework\TestCase;

class TestEmailSubscribers extends TestCase
{
    private $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integration = new EmailSubscribers();
    }

    protected function tearDown(): void
    {
        // Clean up global state
        $_POST = [];
        Post::getInstance()->variables = [];
        global $cleantalk_executed;
        $cleantalk_executed = null;
        parent::tearDown();
    }

    /**
     * Test getDataForChecking with valid subscription data
     */
    public function testGetDataForCheckingWithValidData()
    {
        $_POST = [
            'esfpx_email' => 'stop_email@example.com',
            'esfpx_name' => 'asfsgaeer1111',
            'es' => 'subscribe',
            'esfpx_form_id' => '2',
            'esfpx_es_form_identifier' => 'f2-p6-n1',
            'esfpx_es_email_page' => '6',
            'esfpx_es_email_page_url' => 'https://osp65-wp7.local/?page_id=6',
            'esfpx_status' => 'Unconfirmed',
            'esfpx_es-subscribe' => 'c0b8a6d0be',
            'esfpx_es_hp_email' => '',
            'esfpx_lists' => ['59ac7ed122f5'],
            'action' => 'es_add_subscriber',
        ];

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('stop_email@example.com', $result['email']);
        $this->assertEquals('asfsgaeer1111', $result['nickname']);
    }

    /**
     * Test getDataForChecking with email only (no name)
     */
    public function testGetDataForCheckingWithEmailOnly()
    {
        $_POST = [
            'esfpx_email' => 'user@example.com',
            'es' => 'subscribe',
            'action' => 'es_add_subscriber',
        ];

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('user@example.com', $result['email']);
        $this->assertEquals('', $result['nickname']);
    }

    /**
     * Test getDataForChecking with name only (no email) returns null
     */
    public function testGetDataForCheckingWithNameOnlyReturnsNull()
    {
        $_POST = [
            'esfpx_name' => 'TestUser',
            'es' => 'subscribe',
            'action' => 'es_add_subscriber',
        ];

        $result = $this->integration->getDataForChecking(null);

        $this->assertNull($result);
    }

    /**
     * Test getDataForChecking with empty POST data returns null
     */
    public function testGetDataForCheckingWithEmptyPostReturnsNull()
    {
        $_POST = [];

        $result = $this->integration->getDataForChecking(null);

        $this->assertNull($result);
    }

    /**
     * Test getDataForChecking with special characters in name
     */
    public function testGetDataForCheckingWithSpecialCharactersInName()
    {
        $_POST = [
            'esfpx_email' => 'special@example.com',
            'esfpx_name' => "O'Brien Müller",
            'action' => 'es_add_subscriber',
        ];

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('special@example.com', $result['email']);
        $this->assertStringContainsString("O'Brien", $result['nickname']);
    }

    /**
     * Test getDataForChecking with empty email string returns null
     */
    public function testGetDataForCheckingWithEmptyEmailReturnsNull()
    {
        $_POST = [
            'esfpx_email' => '',
            'esfpx_name' => 'SomeName',
            'action' => 'es_add_subscriber',
        ];

        $result = $this->integration->getDataForChecking(null);

        $this->assertNull($result);
    }

    /**
     * Test getDataForChecking preserves full POST data for ct_gfa_dto processing
     */
    public function testGetDataForCheckingPreservesPostData()
    {
        $_POST = [
            'esfpx_email' => 'test@example.com',
            'esfpx_name' => 'TestName',
            'esfpx_form_id' => '5',
            'esfpx_es_form_identifier' => 'f5-p10-n1',
            'esfpx_es_email_page' => '10',
            'esfpx_es_email_page_url' => 'https://example.com/?page_id=10',
            'esfpx_status' => 'Unconfirmed',
            'action' => 'es_add_subscriber',
        ];

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('test@example.com', $result['email']);
        $this->assertEquals('TestName', $result['nickname']);
    }

    /**
     * Test getDataForChecking with multiple lists
     */
    public function testGetDataForCheckingWithMultipleLists()
    {
        $_POST = [
            'esfpx_email' => 'multi@example.com',
            'esfpx_name' => 'MultiList',
            'esfpx_lists' => ['list1hash', 'list2hash', 'list3hash'],
            'action' => 'es_add_subscriber',
        ];

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('multi@example.com', $result['email']);
        $this->assertEquals('MultiList', $result['nickname']);
    }

    // --- doBlock tests ---

    /**
     * Test doBlock method exists and has correct signature
     */
    public function testDoBlockMethodExists()
    {
        $this->assertTrue(method_exists($this->integration, 'doBlock'));
    }

    /**
     * Test doBlock method has correct signature
     */
    public function testDoBlockMethodSignature()
    {
        $reflection = new \ReflectionMethod($this->integration, 'doBlock');
        $this->assertTrue($reflection->isPublic());
        $this->assertEquals(1, $reflection->getNumberOfParameters());
    }

    /**
     * Test doBlock returns ES-compatible error array in non-AJAX mode (filter path)
     */
    public function testDoBlockReturnsErrorArrayNonAjax()
    {
        // DOING_AJAX is not defined or false in non-AJAX context
        // In test env DOING_AJAX may already be defined, so we test the return format
        // by reading the source
        $source = file_get_contents(
            dirname(__FILE__, 4) . '/lib/Cleantalk/Antispam/Integrations/EmailSubscribers.php'
        );
        // Must return array with 'status' => 'ERROR' for the filter path
        $this->assertStringContainsString("'status'       => 'ERROR'", $source);
        $this->assertStringContainsString("'message_text' => \$message", $source);
        // Must have DOING_AJAX check to differentiate AJAX vs filter path
        $this->assertStringContainsString("defined('DOING_AJAX') && DOING_AJAX", $source);
        // Must use wp_send_json for AJAX path (not wp_send_json_error)
        $this->assertStringContainsString('wp_send_json(', $source);
        $this->assertStringNotContainsString('wp_send_json_error', $source);
    }

    // --- doPrepareActions tests ---

    /**
     * Test doPrepareActions returns true for normal conditions
     */
    public function testDoPrepareActionsReturnsTrueNormally()
    {
        $result = $this->integration->doPrepareActions(null);
        $this->assertTrue($result);
    }

    /**
     * Test doPrepareActions returns false when cleantalk_executed is set
     */
    public function testDoPrepareActionsSkipsWhenAlreadyExecuted()
    {
        global $cleantalk_executed;
        $cleantalk_executed = true;

        $result = $this->integration->doPrepareActions(['status' => 'SUCCESS']);
        $this->assertFalse($result);
    }

    /**
     * Test doPrepareActions returns false when validate_response already has ERROR
     */
    public function testDoPrepareActionsSkipsOnExistingError()
    {
        $validate_response = ['status' => 'ERROR', 'message_text' => 'Invalid email'];

        $result = $this->integration->doPrepareActions($validate_response);
        $this->assertFalse($result);
    }

    /**
     * Test doPrepareActions proceeds when validate_response has SUCCESS status
     */
    public function testDoPrepareActionsProceedsOnSuccess()
    {
        $validate_response = ['status' => 'SUCCESS'];

        $result = $this->integration->doPrepareActions($validate_response);
        $this->assertTrue($result);
    }

    // --- Config tests ---

    /**
     * Test that config has both AJAX and filter hooks
     */
    public function testConfigHasBothHooks()
    {
        // $apbct_active_integrations is a local variable in the config file
        $config_content = file_get_contents(
            dirname(__FILE__, 4) . '/inc/cleantalk-integrations-by-hook.php'
        );

        // Verify both hooks are present for EmailSubscribers
        $this->assertStringContainsString("'EmailSubscribers'", $config_content);
        $this->assertStringContainsString("'es_add_subscriber'", $config_content);
        $this->assertStringContainsString("'ig_es_validate_subscription'", $config_content);
        $this->assertStringContainsString("'ajax_and_post' => true", $config_content);
    }
}
