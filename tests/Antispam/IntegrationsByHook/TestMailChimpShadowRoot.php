<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Post;
use PHPUnit\Framework\TestCase;

class TestMailChimpShadowRoot extends TestCase
{
    private $integration;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use State class like in TestNinjaForms
        global $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        
        $this->integration = new MailChimpShadowRoot();
    }

    protected function tearDown(): void
    {
        global $apbct;
        unset($apbct);
        $_POST = [];
        Post::getInstance()->variables = [];
        parent::tearDown();
    }

    /**
     * Test with valid email data
     */
    public function testGetDataForCheckingWithValidEmail()
    {
        $_POST['email'] = 'test@example.com';
        $_POST['firstName'] = 'John';
        $_POST['lastName'] = 'Doe';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
        $this->assertEquals('test@example.com', $result['email']);
    }

    /**
     * Test with empty POST data
     */
    public function testGetDataForCheckingWithEmptyPost()
    {
        $_POST = [];

        $result = $this->integration->getDataForChecking(null);

        $this->assertNull($result);
    }

    /**
     * Test with only email field
     */
    public function testGetDataForCheckingWithOnlyEmail()
    {
        $_POST['email'] = 'subscriber@example.com';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
    }

    /**
     * Test with special characters in data
     */
    public function testGetDataForCheckingWithSpecialCharacters()
    {
        $_POST['email'] = 'test+tag@example.com';
        $_POST['firstName'] = "O'Brien";
        $_POST['lastName'] = 'Müller-Schmidt';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('test+tag@example.com', $result['email']);
    }

    /**
     * Test with raw_body field (when JSON parsing fails on JS side)
     */
    public function testGetDataForCheckingWithRawBody()
    {
        $_POST['raw_body'] = 'email=raw@example.com&name=Test';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
    }

    /**
     * Test with Mailchimp typical fields
     */
    public function testGetDataForCheckingWithMailchimpFields()
    {
        $_POST['email'] = 'mailchimp@example.com';
        $_POST['FNAME'] = 'First';
        $_POST['LNAME'] = 'Last';
        $_POST['PHONE'] = '+1234567890';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
    }

    /**
     * Test with bot detector token
     */
    public function testGetDataForCheckingWithBotDetectorToken()
    {
        $_POST['email'] = 'bot-detector@example.com';
        $_POST['ct_bot_detector_event_token'] = 'test-token-123';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
    }

    /**
     * Test with no cookie hidden field
     */
    public function testGetDataForCheckingWithNoCookieField()
    {
        $_POST['email'] = 'no-cookie@example.com';
        $_POST['ct_no_cookie_hidden_field'] = 'encoded-data';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
    }

    /**
     * Test with empty email
     */
    public function testGetDataForCheckingWithEmptyEmail()
    {
        $_POST['email'] = '';
        $_POST['firstName'] = 'Test';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
    }

    /**
     * Test with invalid email format
     */
    public function testGetDataForCheckingWithInvalidEmailFormat()
    {
        $_POST['email'] = 'not-an-email';
        $_POST['firstName'] = 'Test';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
    }

    /**
     * Test with multiple email fields (Mailchimp forms may have different field names)
     */
    public function testGetDataForCheckingWithMultipleEmailFields()
    {
        $_POST['email'] = 'primary@example.com';
        $_POST['EMAIL'] = 'secondary@example.com';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
    }

    /**
     * Test with whitespace in data
     */
    public function testGetDataForCheckingWithWhitespace()
    {
        $_POST['email'] = '  whitespace@example.com  ';
        $_POST['firstName'] = '  John  ';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
    }

    /**
     * Test with unicode characters
     */
    public function testGetDataForCheckingWithUnicodeCharacters()
    {
        $_POST['email'] = 'unicode@example.com';
        $_POST['firstName'] = '日本語';
        $_POST['lastName'] = 'Кириллица';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
    }

    /**
     * Test with very long field values
     */
    public function testGetDataForCheckingWithLongValues()
    {
        $_POST['email'] = 'long@example.com';
        $_POST['firstName'] = str_repeat('a', 1000);

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
    }

    /**
     * Test with numeric field values
     */
    public function testGetDataForCheckingWithNumericValues()
    {
        $_POST['email'] = 'numeric@example.com';
        $_POST['phone'] = 1234567890;
        $_POST['zip'] = 12345;

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
    }

    /**
     * Test with array values in POST
     */
    public function testGetDataForCheckingWithArrayValues()
    {
        $_POST['email'] = 'array@example.com';
        $_POST['interests'] = ['option1', 'option2'];

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
    }

    /**
     * Test with no_cookie_data_taken flag already set
     */
    public function testGetDataForCheckingWithNoCookieDataAlreadyTaken()
    {
        global $apbct;
        $apbct->stats['no_cookie_data_taken'] = true;

        $_POST['email'] = 'already-taken@example.com';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
    }

    /**
     * Test with XSS attempt in data
     */
    public function testGetDataForCheckingWithXssAttempt()
    {
        $_POST['email'] = 'xss@example.com';
        $_POST['firstName'] = '<script>alert("xss")</script>';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
    }

    /**
     * Test with SQL injection attempt in data
     */
    public function testGetDataForCheckingWithSqlInjectionAttempt()
    {
        $_POST['email'] = 'sql@example.com';
        $_POST['firstName'] = "'; DROP TABLE users; --";

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
    }
}