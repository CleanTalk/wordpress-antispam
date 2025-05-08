<?php

use PHPUnit\Framework\TestCase;
use Cleantalk\Antispam\Integrations\WPZOOMForms;
use Cleantalk\ApbctWP\Variables\Post;

class WPZOOMFormsTest extends TestCase
{
    /**
     * @var WPZOOMForms
     */
    private $wpzoomForms;
    private $post_global;

    protected function setUp(): void
    {
        $this->wpzoomForms = new WPZOOMForms();
        $this->post_global = $_POST;
    }

    protected function tearDown(): void
    {
        // Clean up the $_POST global variable
        $this->resetPostState();
    }

    private function prepareDefaultPostData()
    {
        return array (
            'action' => 'wpzf_submit',
            'form_id' => '163',
            '_wpnonce' => '0d142c2c7e',
            '_wp_http_referer' => '/wpzomm/?success=1',
            'wpzf_input_name' => 'Test Name',
            'wpzf_input_email' => 'test@example.com',
            'wpzf_input_message' => 'Test message',
            'wpzf_replyto' => 'wpzf_input_email',
            'wpzf_subject' => 'wpzf_input_subject',
            'apbct_visible_fields' => 'test_vfields',
            'event_token' => 'test_token',
        );
    }

    private function prepareExpectedDataBeforeBaseCall()
    {
        return array(
            'email' => 'test@example.com',
            'message' => 'Test message',
            'emails_array' => Array (),
            'nickname' => 'Test Name',
            'nickname_first' => '',
            'nickname_last' => '',
            'nickname_nick' => '',
            'subject' => 'wpzf_input_subject',
            'contact' => true,
            'register' => false
        );
    }

    private function resetPostState()
    {
        $_POST = $this->post_global;
    }

    public function testGetDataForChecking()
    {
        $this->resetPostState(); //use this everytime if new test is added
        // Mock $_POST data
        $_POST = $this->prepareDefaultPostData();

        // Assert the result
        $this->assertEquals(
            $this->prepareExpectedDataBeforeBaseCall(),
            $this->wpzoomForms->getDataForChecking(null)
        );
    }
}
