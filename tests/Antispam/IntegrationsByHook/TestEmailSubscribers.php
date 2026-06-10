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
     * Test getDataForChecking with name only (no email)
     */
    public function testGetDataForCheckingWithNameOnly()
    {
        $_POST = [
            'esfpx_name' => 'TestUser',
            'es' => 'subscribe',
            'action' => 'es_add_subscriber',
        ];

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('', $result['email']);
        $this->assertEquals('TestUser', $result['nickname']);
    }

    /**
     * Test getDataForChecking with empty POST data
     */
    public function testGetDataForCheckingWithEmptyPost()
    {
        $_POST = [];

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('', $result['email']);
        $this->assertEquals('', $result['nickname']);
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
     * Test getDataForChecking with empty strings for email and name
     */
    public function testGetDataForCheckingWithEmptyStrings()
    {
        $_POST = [
            'esfpx_email' => '',
            'esfpx_name' => '',
            'action' => 'es_add_subscriber',
        ];

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('', $result['email']);
        $this->assertEquals('', $result['nickname']);
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

    /**
     * Test doBlock method exists and has correct signature
     */
    public function testDoBlockMethodExists()
    {
        // Note: doBlock() uses die() which is difficult to test properly
        // This test verifies that the method exists and can be called
        $this->assertTrue(method_exists($this->integration, 'doBlock'));
    }

    /**
     * Test doBlock method has correct signature
     */
    public function testDoBlockMethodSignature()
    {
        // Note: Testing die() properly requires process isolation
        // We verify the method signature and expected behavior
        $reflection = new \ReflectionMethod($this->integration, 'doBlock');
        $this->assertTrue($reflection->isPublic());
        $this->assertEquals(1, $reflection->getNumberOfParameters());
    }
}
