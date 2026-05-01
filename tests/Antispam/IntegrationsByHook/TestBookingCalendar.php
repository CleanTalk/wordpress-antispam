<?php

namespace Cleantalk\Antispam\Integrations;

use PHPUnit\Framework\TestCase;

class TestBookingCalendar extends TestCase
{
    private $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integration = new BookingCalendar();
    }

    protected function tearDown(): void
    {
        $_POST = [];
        parent::tearDown();
    }

    /**
     * Test with valid booking calendar data (with _val fields)
     */
    public function testGetDataForCheckingWithValFields()
    {
        $_POST['calendar_request_params'] = [
            'formdata' => 'text^firstname1^John~text^firstname_val1^John~text^secondname1^Doe~text^secondname_val1^Doe~email^email1^john.doe@example.com~email^email_val1^john.doe@example.com~textarea^textarea1^Hello~textarea^textarea_val1^Hello'
        ];
        $result = $this->integration->getDataForChecking(null);
        $this->assertIsArray($result);
        $this->assertEquals('john.doe@example.com', $result['email']);
        $this->assertEquals('John Doe', $result['nickname']);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('textarea_val1[value]', $result['message']);
        $this->assertEquals('Hello', $result['message']['textarea_val1[value]']);
        $this->assertArrayNotHasKey('firstname_val1[value]', $result['message']);
        $this->assertArrayNotHasKey('email_val1[value]', $result['message']);
    }

    /**
     * Test with only firstname and email (no secondname)
     */
    public function testGetDataForCheckingWithOnlyFirstname()
    {
        $_POST['calendar_request_params'] = [
            'formdata' => 'text^firstname_val1^Jane~email^email_val1^jane@example.com~textarea^textarea_val1^Hi'
        ];
        $result = $this->integration->getDataForChecking(null);
        $this->assertIsArray($result);
        $this->assertEquals('jane@example.com', $result['email']);
        $this->assertEquals('Jane', $result['nickname']);
        $this->assertArrayHasKey('textarea_val1[value]', $result['message']);
    }

    /**
     * Test with only secondname and email (no firstname)
     */
    public function testGetDataForCheckingWithOnlySecondname()
    {
        $_POST['calendar_request_params'] = [
            'formdata' => 'text^secondname_val1^Smith~email^email_val1^smith@example.com~textarea^textarea_val1^Test message'
        ];
        $result = $this->integration->getDataForChecking(null);
        $this->assertIsArray($result);
        $this->assertEquals('smith@example.com', $result['email']);
        $this->assertEquals('Smith', $result['nickname']);
        $this->assertArrayHasKey('textarea_val1[value]', $result['message']);
    }

    /**
     * Test with no email (should return null)
     */
    public function testGetDataForCheckingNoEmail()
    {
        $_POST['calendar_request_params'] = [
            'formdata' => 'text^firstname_val1^NoEmail~textarea^textarea_val1^No email here'
        ];
        $result = $this->integration->getDataForChecking(null);
        $this->assertIsArray($result);
        $this->assertEquals('', $result['email']);
    }

    /**
     * Test with no formdata (should return null)
     */
    public function testGetDataForCheckingNoFormdata()
    {
        $_POST['calendar_request_params'] = [];
        $result = $this->integration->getDataForChecking(null);
        $this->assertNull($result);
    }

    /**
     * Test with no calendar_request_params (should return null)
     */
    public function testGetDataForCheckingNoParams()
    {
        $_POST = [];
        $result = $this->integration->getDataForChecking(null);
        $this->assertNull($result);
    }

    /**
     * Test with both _val and non-_val textarea fields (should keep only _val)
     */
    public function testGetDataForCheckingTextareaDeduplication()
    {
        $_POST['calendar_request_params'] = [
            'formdata' => 'textarea^textarea1^Duplicate~textarea^textarea_val1^Unique'
        ];
        $result = $this->integration->getDataForChecking(null);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('textarea_val1[value]', $result['message']);
        $this->assertEquals('Unique', $result['message']['textarea_val1[value]']);
        $this->assertArrayNotHasKey('textarea1[value]', $result['message']);
    }
}
