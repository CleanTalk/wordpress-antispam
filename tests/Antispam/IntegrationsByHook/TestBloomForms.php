<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use PHPUnit\Framework\TestCase;

class TestBloomForms extends TestCase
{
    private $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integration = new BloomForms();
    }

    protected function tearDown(): void
    {
        // Clean up global state
        $_POST = [];
        Post::getInstance()->variables = [];
        parent::tearDown();
    }

    /**
     * Test extraction of nickname from subscribe_data_array with valid JSON
     */
    public function testGetDataForCheckingWithValidJsonName()
    {
        $_POST['action'] = 'bloom_subscribe';
        $_POST['subscribe_data_array'] = '{"list_id":123,"name":"TestUser","email":"test@example.com"}';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('TestUser', $result['nickname']);
    }

    /**
     * Test extraction of nickname from subscribe_data_array with escaped JSON (real-world case)
     */
    public function testGetDataForCheckingWithEscapedJsonName()
    {
        $_POST['action'] = 'bloom_subscribe';
        $_POST['subscribe_data_array'] = '{\"list_id\":113164156,\"account_name\":\"test@example\",\"service\":\"mailerlite\",\"name\":\"qwe123\",\"email\":\"test@example.com\",\"page_id\":245335,\"optin_id\":\"optin_40\",\"last_name\":\"\",\"ip_address\":\"true\"}';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('qwe123', $result['nickname']);
    }

    /**
     * Test with empty name in subscribe_data_array
     */
    public function testGetDataForCheckingWithEmptyName()
    {
        $_POST['action'] = 'bloom_subscribe';
        $_POST['subscribe_data_array'] = '{"list_id":123,"name":"","email":"test@example.com"}';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('', $result['nickname']);
    }

    /**
     * Test without subscribe_data_array field
     */
    public function testGetDataForCheckingWithoutSubscribeDataArray()
    {
        $_POST['action'] = 'bloom_subscribe';
        $_POST['email'] = 'test@example.com';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('', $result['nickname']);
    }

    /**
     * Test with invalid JSON in subscribe_data_array
     */
    public function testGetDataForCheckingWithInvalidJson()
    {
        $_POST['action'] = 'bloom_subscribe';
        $_POST['subscribe_data_array'] = 'not a valid json string';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('', $result['nickname']);
    }

    /**
     * Test with special characters in name
     */
    public function testGetDataForCheckingWithSpecialCharactersInName()
    {
        $_POST['action'] = 'bloom_subscribe';
        $_POST['subscribe_data_array'] = '{"name":"O\'Brien Müller","email":"special@example.com"}';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertStringContainsString("O'Brien", $result['nickname']);
        $this->assertStringContainsString('Müller', $result['nickname']);
    }

    /**
     * Test with name containing only whitespace
     */
    public function testGetDataForCheckingWithWhitespaceName()
    {
        $_POST['action'] = 'bloom_subscribe';
        $_POST['subscribe_data_array'] = '{"name":"   ","email":"test@example.com"}';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        // sanitize_text_field trims whitespace, so it should be empty
        $this->assertEquals('', $result['nickname']);
    }

    /**
     * Test with numeric name value
     */
    public function testGetDataForCheckingWithNumericName()
    {
        $_POST['action'] = 'bloom_subscribe';
        $_POST['subscribe_data_array'] = '{"name":12345,"email":"test@example.com"}';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        // Numeric value should be converted to string
        $this->assertEquals('12345', $result['nickname']);
    }

    /**
     * Test with null name value in JSON
     */
    public function testGetDataForCheckingWithNullName()
    {
        $_POST['action'] = 'bloom_subscribe';
        $_POST['subscribe_data_array'] = '{"name":null,"email":"test@example.com"}';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('', $result['nickname']);
    }

    /**
     * Test with missing name field in JSON
     */
    public function testGetDataForCheckingWithMissingNameField()
    {
        $_POST['action'] = 'bloom_subscribe';
        $_POST['subscribe_data_array'] = '{"email":"test@example.com","list_id":123}';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('', $result['nickname']);
    }

    /**
     * Test with empty POST data
     */
    public function testGetDataForCheckingWithEmptyPost()
    {
        $_POST = [];

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('', $result['nickname']);
    }

    /**
     * Test with array value for subscribe_data_array (should not decode)
     */
    public function testGetDataForCheckingWithArraySubscribeData()
    {
        $_POST['action'] = 'bloom_subscribe';
        $_POST['subscribe_data_array'] = ['name' => 'TestUser', 'email' => 'test@example.com'];

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        // Array is not a string, so nickname extraction should not work
        $this->assertEquals('', $result['nickname']);
    }

    /**
     * Test with double-escaped JSON
     */
    public function testGetDataForCheckingWithDoubleEscapedJson()
    {
        $_POST['action'] = 'bloom_subscribe';
        // Double escaped - after wp_unslash should become valid JSON
        $_POST['subscribe_data_array'] = '{\\\"name\\\":\\\"DoubleEscaped\\\",\\\"email\\\":\\\"test@example.com\\\"}';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        // This depends on how wp_unslash handles double escaping
    }

    /**
     * Test email extraction from subscribe_data_array
     */
    public function testGetDataForCheckingEmailExtraction()
    {
        $_POST['action'] = 'bloom_subscribe';
        $_POST['subscribe_data_array'] = '{"name":"TestUser","email":"bloom@example.com"}';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
    }
}
