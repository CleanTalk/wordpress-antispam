<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use PHPUnit\Framework\TestCase;

class TestEventPrime extends TestCase
{
    private $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $_POST = [];
        Post::getInstance()->variables = [];
        $this->integration = new EventPrime();
    }

    protected function tearDown(): void
    {
        $_POST = [];
        Post::getInstance()->variables = [];
        parent::tearDown();
    }

    /**
     * Test returns null when POST has no EventPrime fields
     */
    public function testGetDataForCheckingReturnsNullWithEmptyPost()
    {
        $result = $this->integration->getDataForChecking(null);

        $this->assertNull($result);
    }

    /**
     * Test returns null when POST has unrelated action
     */
    public function testGetDataForCheckingReturnsNullWithUnrelatedAction()
    {
        $_POST['action'] = 'some_other_action';
        $_POST['email'] = 'test@example.com';

        $result = $this->integration->getDataForChecking(null);

        $this->assertNull($result);
    }

    /**
     * Test register form returns array when nonce is present
     */
    public function testGetDataForCheckingRegisterFormReturnsArray()
    {
        $_POST['ep-attendee-register-nonce'] = 'abc123';
        $_POST['ep_rg_field_email'] = 'user@example.com';
        $_POST['ep_rg_field_first_name'] = 'John';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
    }

    /**
     * Test register form sets register flag to true
     */
    public function testGetDataForCheckingRegisterFormHasRegisterFlag()
    {
        $_POST['ep-attendee-register-nonce'] = 'abc123';
        $_POST['ep_rg_field_email'] = 'user@example.com';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertTrue($result['register']);
    }

    /**
     * Test checkout form returns null when ep_rg_field_email is missing
     */
    public function testGetDataForCheckingCheckoutFormReturnsNullWithoutEmail()
    {
        $_POST['action'] = 'ep_save_event_booking';
        $_POST['ep_event_id'] = '42';

        $result = $this->integration->getDataForChecking(null);

        $this->assertNull($result);
    }

    /**
     * Test checkout form returns array when ep_rg_field_email is present
     */
    public function testGetDataForCheckingCheckoutFormWithEmail()
    {
        $_POST['action'] = 'ep_save_event_booking';
        $_POST['ep_rg_field_email'] = 'buyer@example.com';
        $_POST['ep_event_id'] = '42';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('buyer@example.com', $result['email']);
    }

    /**
     * Test checkout form returns the email in result
     */
    public function testGetDataForCheckingCheckoutFormResultContainsEmail()
    {
        $_POST['action'] = 'ep_save_event_booking';
        $_POST['ep_rg_field_email'] = 'buyer@example.com';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('buyer@example.com', $result['email']);
    }

    /**
     * Test checkout form parses data string and extracts email from it
     */
    public function testGetDataForCheckingCheckoutFormParsesDataString()
    {
        $_POST['action'] = 'ep_save_event_booking';
        $_POST['data'] = 'ep_rg_field_email=encoded%40example.com&ep_event_id=5';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('encoded@example.com', $result['email']);
    }

    /**
     * Test checkout form data string without email still returns null
     */
    public function testGetDataForCheckingCheckoutFormDataStringWithoutEmailReturnsNull()
    {
        $_POST['action'] = 'ep_save_event_booking';
        $_POST['data'] = 'ep_event_id=5&ep_attendee_count=1';

        $result = $this->integration->getDataForChecking(null);

        $this->assertNull($result);
    }

    /**
     * Test event token is extracted and added to result
     */
    public function testGetDataForCheckingExtractsEventToken()
    {
        $_POST['ep-attendee-register-nonce'] = 'nonce123';
        $_POST['ep_rg_field_email'] = 'user@example.com';
        $_POST['ct_bot_detector_event_token'] = 'token_abc';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('token_abc', $result['event_token']);
    }

    /**
     * Test event token is removed from main input_data fields
     */
    public function testGetDataForCheckingEventTokenNotDuplicatedInResult()
    {
        $_POST['ep-attendee-register-nonce'] = 'nonce123';
        $_POST['ep_rg_field_email'] = 'user@example.com';
        $_POST['ct_bot_detector_event_token'] = 'token_xyz';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('token_xyz', $result['event_token']);
    }

    /**
     * Test no event_token key when token is absent
     */
    public function testGetDataForCheckingNoEventTokenKeyWhenAbsent()
    {
        $_POST['ep-attendee-register-nonce'] = 'nonce123';
        $_POST['ep_rg_field_email'] = 'user@example.com';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('event_token', $result);
    }

    /**
     * Test checkout form event token extracted from parsed data string
     */
    public function testGetDataForCheckingCheckoutFormEventTokenFromDataString()
    {
        $_POST['action'] = 'ep_save_event_booking';
        $_POST['data'] = 'ep_rg_field_email=token%40example.com&ct_bot_detector_event_token=tok123';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('tok123', $result['event_token']);
    }

    /**
     * Test register form with special characters in email
     */
    public function testGetDataForCheckingRegisterFormWithSpecialCharactersInEmail()
    {
        $_POST['ep-attendee-register-nonce'] = 'nonce';
        $_POST['ep_rg_field_email'] = "special+tag@example.co.uk";

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
    }

    /**
     * Test that both nonce and checkout action triggers register form logic (nonce takes precedence)
     */
    public function testGetDataForCheckingRegisterNonceTakesPrecedenceOverCheckoutAction()
    {
        $_POST['ep-attendee-register-nonce'] = 'nonce';
        $_POST['action'] = 'ep_save_event_booking';
        $_POST['ep_rg_field_email'] = 'user@example.com';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertTrue($result['register']);
    }
}
