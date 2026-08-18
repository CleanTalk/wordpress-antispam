<?php

use Cleantalk\Antispam\Integrations\WebMagicStudio;
use Cleantalk\ApbctWP\Variables\Post;
use PHPUnit\Framework\TestCase;

class WebMagicStudioTest extends TestCase
{
    /**
     * @var WebMagicStudio
     */
    private $integration;

    private $post_global;

    protected function setUp(): void
    {
        $this->integration = new WebMagicStudio();
        $this->post_global = $_POST;
        Post::getInstance()->variables = [];
    }

    protected function tearDown(): void
    {
        $_POST = $this->post_global;
        Post::getInstance()->variables = [];
    }

    private function prepareDefaultPostData()
    {
        return array(
            'action'                      => 'wms_contact',
            'wms_name'                    => 'Test Name',
            'wms_email'                   => 'test@example.com',
            'wms_need'                    => 'A new website',
            'wms_message'                 => 'Test message',
            'apbct_visible_fields'        => 'test_vfields',
            'ct_bot_detector_event_token' => 'test_token',
        );
    }

    private function prepareExpectedDataBeforeBaseCall()
    {
        return array(
            'email'          => 'test@example.com',
            'message'        => 'Test message',
            'emails_array'   => array(),
            'nickname'       => 'Test Name',
            'nickname_first' => '',
            'nickname_last'  => '',
            'nickname_nick'  => '',
            'subject'        => 'A new website',
            'contact'        => true,
            'register'       => false,
            'event_token'    => 'test_token',
        );
    }

    public function testGetDataForChecking()
    {
        Post::getInstance()->variables = [];
        $_POST = $this->prepareDefaultPostData();

        $this->assertEquals(
            $this->prepareExpectedDataBeforeBaseCall(),
            $this->integration->getDataForChecking(null)
        );
    }
}
