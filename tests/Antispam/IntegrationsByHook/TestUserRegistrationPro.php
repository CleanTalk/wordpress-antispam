<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use PHPUnit\Framework\TestCase;

class TestUserRegistrationPro extends TestCase
{
    private $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integration = new UserRegistrationPro();
    }

    protected function tearDown(): void
    {
        $_POST = [];
        Post::getInstance()->variables = [];
        parent::tearDown();
    }

    /**
     * Test with valid form data containing email and username
     */
    public function testGetDataForCheckingWithValidData()
    {
        $_POST['action'] = 'user_registration_user_form_submit';
        $_POST['form_data'] = json_encode([
            ['field_name' => 'user_login', 'value' => 'testuser', 'field_type' => 'text', 'label' => 'Username'],
            ['field_name' => 'user_email', 'value' => 'test@example.com', 'field_type' => 'email', 'label' => 'User Email'],
            ['field_name' => 'user_pass', 'value' => 'password123', 'field_type' => 'password', 'label' => 'User Password'],
        ]);
        $_POST['form_id'] = '48';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('test@example.com', $result['email']);
        $this->assertEquals('testuser', $result['nickname']);
        $this->assertTrue($result['register']);
    }

    /**
     * Test with escaped JSON (WordPress magic quotes simulation)
     */
    public function testGetDataForCheckingWithEscapedJson()
    {
        $_POST['action'] = 'user_registration_user_form_submit';
        $_POST['form_data'] = '[{\"field_name\":\"user_login\",\"value\":\"john_doe\",\"field_type\":\"text\",\"label\":\"Username\"},{\"field_name\":\"user_email\",\"value\":\"john@example.com\",\"field_type\":\"email\",\"label\":\"User Email\"}]';
        $_POST['form_id'] = '48';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('john@example.com', $result['email']);
        $this->assertEquals('john_doe', $result['nickname']);
        $this->assertTrue($result['register']);
    }

    /**
     * Test with only email (no username field)
     */
    public function testGetDataForCheckingWithOnlyEmail()
    {
        $_POST['action'] = 'user_registration_user_form_submit';
        $_POST['form_data'] = json_encode([
            ['field_name' => 'user_email', 'value' => 'only.email@example.com', 'field_type' => 'email', 'label' => 'User Email'],
            ['field_name' => 'user_pass', 'value' => 'password123', 'field_type' => 'password', 'label' => 'User Password'],
        ]);

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('only.email@example.com', $result['email']);
        $this->assertTrue($result['register']);
    }

    /**
     * Test with only username (no email field)
     */
    public function testGetDataForCheckingWithOnlyUsername()
    {
        $_POST['action'] = 'user_registration_user_form_submit';
        $_POST['form_data'] = json_encode([
            ['field_name' => 'user_login', 'value' => 'onlyuser', 'field_type' => 'text', 'label' => 'Username'],
            ['field_name' => 'user_pass', 'value' => 'password123', 'field_type' => 'password', 'label' => 'User Password'],
        ]);

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('onlyuser', $result['nickname']);
        $this->assertTrue($result['register']);
    }

    /**
     * Test with empty form_data
     */
    public function testGetDataForCheckingWithEmptyFormData()
    {
        $_POST['action'] = 'user_registration_user_form_submit';
        $_POST['form_data'] = '[]';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertTrue($result['register']);
    }

    /**
     * Test with invalid JSON in form_data
     */
    public function testGetDataForCheckingWithInvalidJson()
    {
        $_POST['action'] = 'user_registration_user_form_submit';
        $_POST['form_data'] = 'invalid json string';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertTrue($result['register']);
    }

    /**
     * Test with form_data already as array (pre-decoded)
     */
    public function testGetDataForCheckingWithFormDataAsArray()
    {
        $_POST['action'] = 'user_registration_user_form_submit';
        $_POST['form_data'] = [
            ['field_name' => 'user_login', 'value' => 'arrayuser', 'field_type' => 'text'],
            ['field_name' => 'user_email', 'value' => 'array@example.com', 'field_type' => 'email'],
        ];

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertTrue($result['register']);
    }

    /**
     * Test with special characters in username and email
     */
    public function testGetDataForCheckingWithSpecialCharacters()
    {
        $_POST['action'] = 'user_registration_user_form_submit';
        $_POST['form_data'] = json_encode([
            ['field_name' => 'user_login', 'value' => "user_o'brien", 'field_type' => 'text', 'label' => 'Username'],
            ['field_name' => 'user_email', 'value' => 'test+tag@example.com', 'field_type' => 'email', 'label' => 'User Email'],
        ]);

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('test+tag@example.com', $result['email']);
        $this->assertStringContainsString("o'brien", $result['nickname']);
        $this->assertTrue($result['register']);
    }

    /**
     * Test with unicode characters in username
     */
    public function testGetDataForCheckingWithUnicodeCharacters()
    {
        $_POST['action'] = 'user_registration_user_form_submit';
        $_POST['form_data'] = json_encode([
            ['field_name' => 'user_login', 'value' => 'Müller', 'field_type' => 'text', 'label' => 'Username'],
            ['field_name' => 'user_email', 'value' => 'muller@example.com', 'field_type' => 'email', 'label' => 'User Email'],
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('muller@example.com', $result['email']);
        $this->assertEquals('Müller', $result['nickname']);
    }

    /**
     * Test with empty POST data
     */
    public function testGetDataForCheckingWithEmptyPost()
    {
        $_POST = [];

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertTrue($result['register']);
    }

    /**
     * Test with no form_data key
     */
    public function testGetDataForCheckingWithoutFormDataKey()
    {
        $_POST['action'] = 'user_registration_user_form_submit';
        $_POST['form_id'] = '48';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertTrue($result['register']);
    }

    /**
     * Test with membership form data
     */
    public function testGetDataForCheckingWithMembershipData()
    {
        $_POST['action'] = 'user_registration_user_form_submit';
        $_POST['form_data'] = json_encode([
            ['field_name' => 'user_login', 'value' => 'memberuser', 'field_type' => 'text', 'label' => 'Username'],
            ['field_name' => 'user_email', 'value' => 'member@example.com', 'field_type' => 'email', 'label' => 'User Email'],
            ['field_name' => 'user_pass', 'value' => 'securepass123', 'field_type' => 'password', 'label' => 'User Password'],
            ['field_name' => 'membership_field_123', 'value' => '55', 'field_type' => 'radio', 'label' => 'membership'],
        ]);
        $_POST['form_id'] = '48';
        $_POST['members_data'] = '{"membership":"55","payment_method":"free"}';
        $_POST['is_membership_active'] = '55';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('member@example.com', $result['email']);
        $this->assertEquals('memberuser', $result['nickname']);
        $this->assertTrue($result['register']);
    }

    /**
     * Test with fields missing value key
     */
    public function testGetDataForCheckingWithFieldsMissingValue()
    {
        $_POST['action'] = 'user_registration_user_form_submit';
        $_POST['form_data'] = json_encode([
            ['field_name' => 'user_login', 'field_type' => 'text', 'label' => 'Username'],
            ['field_name' => 'user_email', 'value' => 'valid@example.com', 'field_type' => 'email'],
        ]);

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('valid@example.com', $result['email']);
        $this->assertTrue($result['register']);
    }

    /**
     * Test with fields missing field_name key
     */
    public function testGetDataForCheckingWithFieldsMissingFieldName()
    {
        $_POST['action'] = 'user_registration_user_form_submit';
        $_POST['form_data'] = json_encode([
            ['value' => 'somevalue', 'field_type' => 'text'],
            ['field_name' => 'user_email', 'value' => 'name@example.com', 'field_type' => 'email'],
        ]);

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('name@example.com', $result['email']);
        $this->assertTrue($result['register']);
    }

    /**
     * Test with empty string values
     */
    public function testGetDataForCheckingWithEmptyStringValues()
    {
        $_POST['action'] = 'user_registration_user_form_submit';
        $_POST['form_data'] = json_encode([
            ['field_name' => 'user_login', 'value' => '', 'field_type' => 'text', 'label' => 'Username'],
            ['field_name' => 'user_email', 'value' => '', 'field_type' => 'email', 'label' => 'User Email'],
        ]);

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('', $result['email']);
        $this->assertEquals('', $result['nickname']);
        $this->assertTrue($result['register']);
    }
}
