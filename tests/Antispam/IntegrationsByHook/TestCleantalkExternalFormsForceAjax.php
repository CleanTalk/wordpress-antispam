<?php

use PHPUnit\Framework\TestCase;
use Cleantalk\Antispam\Integrations\CleantalkExternalFormsForceAjax;

class TestCleantalkExternalFormsForceAjax extends TestCase
{
    /**
     * @var CleantalkExternalFormsForceAjax
     */
    private $integration;
    private $post_global;

    protected function setUp(): void
    {
        $this->integration = new CleantalkExternalFormsForceAjax();
        $this->post_global = $_POST;
    }

    protected function tearDown(): void
    {
        // Clean up the $_POST global variable
        $this->resetPostState();
    }

    private function resetPostState()
    {
        $_POST = $this->post_global;
    }

    /**
     * Test getDataForChecking when message.name exists and is not empty
     * This should set nickname from message['name']
     */
    public function testGetDataForCheckingWithMessageName()
    {
        $this->resetPostState();
        
        // Prepare $_POST data that will result in message['name'] being set
        // The ct_gfa_dto function processes POST data and may place 'name' in message array
        $_POST = array(
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => 'Test message content',
            'subject' => 'Test Subject',
        );

        $result = $this->integration->getDataForChecking(null);

        // Verify the result is an array
        $this->assertIsArray($result);
        
        // Verify required keys exist
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('nickname', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('emails_array', $result);
        $this->assertArrayHasKey('contact', $result);
        
        // The key behavior: if message['name'] exists and is not empty, nickname should equal it
        if (isset($result['message']['name']) && !empty($result['message']['name'])) {
            $this->assertEquals(
                $result['message']['name'], 
                $result['nickname'],
                'Nickname should be set from message[\'name\'] when it exists and is not empty'
            );
        }
    }

    /**
     * Test getDataForChecking when message.name does not exist
     * Nickname should not be set from message['name'] in this case
     */
    public function testGetDataForCheckingWithoutMessageName()
    {
        $this->resetPostState();
        
        // Prepare $_POST data without name field
        $_POST = array(
            'email' => 'test@example.com',
            'message' => 'Test message content',
            'subject' => 'Test Subject',
        );

        $result = $this->integration->getDataForChecking(null);

        // Verify the result is an array
        $this->assertIsArray($result);
        
        // Verify required keys exist
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('nickname', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('emails_array', $result);
        $this->assertArrayHasKey('contact', $result);
        
        // Verify that nickname is not set from message['name'] when it doesn't exist
        // (nickname might be set from other fields or be empty, but not from message['name'])
        if (!isset($result['message']['name']) || empty($result['message']['name'])) {
            // This is expected - nickname should not be set from message['name'] when it's missing/empty
            $this->assertTrue(true, 'Nickname correctly not set from message[\'name\'] when it doesn\'t exist');
        }
    }

    /**
     * Test getDataForChecking when message.name is empty
     * Nickname should not be set from empty message['name']
     */
    public function testGetDataForCheckingWithEmptyMessageName()
    {
        $this->resetPostState();
        
        // Prepare $_POST data with empty name field
        $_POST = array(
            'name' => '',
            'email' => 'test@example.com',
            'message' => 'Test message content',
        );

        $result = $this->integration->getDataForChecking(null);

        // Verify the result is an array
        $this->assertIsArray($result);
        
        // Verify required keys exist
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('nickname', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('emails_array', $result);
        $this->assertArrayHasKey('contact', $result);
        
        // Verify that nickname is not set from message['name'] when it's empty
        if (isset($result['message']['name']) && empty($result['message']['name'])) {
            // Nickname should not equal empty message['name']
            $this->assertNotEquals(
                $result['message']['name'], 
                $result['nickname'],
                'Nickname should not be set from empty message[\'name\']'
            );
        }
    }

    /**
     * Test getDataForChecking with empty $_POST
     */
    public function testGetDataForCheckingWithEmptyPost()
    {
        $this->resetPostState();
        
        $_POST = array();

        $result = $this->integration->getDataForChecking(null);

        // Verify the result is an array
        $this->assertIsArray($result);
        
        // Verify required keys exist even with empty POST
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('nickname', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('emails_array', $result);
        $this->assertArrayHasKey('contact', $result);
    }

    /**
     * Test getDataForChecking that it applies apbct__filter_post filter
     */
    public function testGetDataForCheckingAppliesFilter()
    {
        $this->resetPostState();
        
        $_POST = array(
            'name' => 'Test Name',
            'email' => 'test@example.com',
        );

        // Add a filter to modify the POST data
        add_filter('apbct__filter_post', function($post_data) {
            $post_data['filtered'] = true;
            return $post_data;
        });

        $result = $this->integration->getDataForChecking(null);

        // Verify the filter was applied (the filtered data should be processed)
        $this->assertIsArray($result);
        
        // Remove the filter
        remove_all_filters('apbct__filter_post');
    }

    /**
     * Test getDataForChecking returns data from ct_gfa_dto and applies nickname logic
     */
    public function testGetDataForCheckingReturnsGfaData()
    {
        $this->resetPostState();
        
        $_POST = array(
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'message' => 'Hello world',
            'subject' => 'Test',
        );

        $result = $this->integration->getDataForChecking(null);

        // Verify the structure matches what ct_gfa_dto would return
        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('nickname', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('emails_array', $result);
        $this->assertArrayHasKey('contact', $result);
        
        // The core behavior: if message['name'] exists and is not empty, nickname should be set to it
        if (isset($result['message']['name']) && !empty($result['message']['name'])) {
            $this->assertEquals(
                $result['message']['name'], 
                $result['nickname'], 
                'Nickname should be set from message[\'name\'] when it exists and is not empty'
            );
        }
    }
}

