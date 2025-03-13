<?php

use Cleantalk\ApbctWP\DTO\GetFieldsAnyDTO;
use Cleantalk\ApbctWP\GetFieldsAny;
use Cleantalk\ApbctWP\Variables\Post;
use PHPUnit\Framework\TestCase;

class TestGetFieldsAnyNested extends TestCase {

	/**
	 * Fake $_POST
	 * @var array
	 */
	private $post;

	/**
	 * @var GetFieldsAny
	 */
	private $gfa;

	public function setUp()
	{
        // Elementor Forms Pro request example
        $this->post = array(
            'post_id' => '103',
            'form_id' => 'b4bcdb3',
            'referer_title' => 'Elementor Pro Form',
            'queried_id' => '103',
            'form_fields' =>
                array (
                    'name' => 'test name',
                    'email' => 's@cleantalk.org',
                    'message' => 'message',
                ),
            'apbct_visible_fields' => 'eyIwIjp7InZpc2libGVfZmllbGRzIjoiZm9ybV9maWVsZHNbbmFtZV0gZm9ybV9maWVsZHNbZW1haWxdIGZvcm1fZmllbGRzW21lc3NhZ2VdIiwidmlzaWJsZV9maWVsZHNfY291bnQiOjMsImludmlzaWJsZV9maWVsZHMiOiJwb3N0X2lkIGZvcm1faWQgcmVmZXJlcl90aXRsZSBxdWVyaWVkX2lkIGN0X2JvdF9kZXRlY3Rvcl9ldmVudF90b2tlbiIsImludmlzaWJsZV9maWVsZHNfY291bnQiOjV9fQ==',
            'action' => 'elementor_pro_forms_send_form',
        );

        //Emulate Elementor Pro Form request
        $_POST = $this->post;
        Post::getInstance()->variables['apbct_visible_fields'] = $this->post['apbct_visible_fields'];

		$this->gfa = new GetFieldsAny($this->post);
	}
    public function tearDown()
    {
        unset($_POST);
    }

    /**
     * Check if visible fields are compared by $_POST['apbct_visible_fields']
     * @return void
     */
    public function testNestedVisibleFields()
    {
        $visible_fields = \Cleantalk\ApbctWP\GetFieldsAny::getVisibleFieldsData();
        $this->assertIsArray($visible_fields);
        $this->assertArrayHasKey('visible_fields', $visible_fields);
        // 3 items, they are array (
        //  0 => 'form_fields[name]',
        //  1 => 'form_fields[email]',
        //  2 => 'form_fields[message]',
        //)
        $this->assertEquals(3, $visible_fields['visible_fields_count']);
    }

    /**
     * Check if GFA get only visible fields
     * @return void
     */
    public function testNestedGfa()
    {
        $fields = $this->gfa->getFieldsDTO();
        $this->assertInstanceOf(GetFieldsAnyDTO::class, $fields);

        $this->assertArrayNotHasKey('post_id', $fields->message);
        $this->assertArrayNotHasKey('referer_title', $fields->message);
        $this->assertArrayNotHasKey('queried_id', $fields->message);
        $this->assertArrayNotHasKey('apbct_visible_fields', $fields->message);
        $this->assertArrayNotHasKey('action', $fields->message);
    }
}
