<?php

use Cleantalk\ApbctWP\Variables\Post;
use PHPUnit\Framework\TestCase;

class FormidableAjaxKeyTest extends TestCase
{
    protected function tearDown(): void
    {
        $_POST = array();
        Post::getInstance()->variables = array();
        parent::tearDown();
    }

    public function test_pick_field_id_skips_markup_key()
    {
        $key = apbct__formidable_pick_field_id(
            array(
                '6' => 'stop_email@example.com',
                '<img src=x onerror=alert(document.domain)>' => 'lab',
            )
        );

        $this->assertSame('6', $key);
    }

    public function test_pick_field_id_prefers_second_numeric_key()
    {
        $key = apbct__formidable_pick_field_id(
            array(
                '6' => 'first',
                '7' => 'second',
            )
        );

        $this->assertSame('7', $key);
    }

    public function test_is_field_id_rejects_markup()
    {
        $this->assertFalse(apbct__formidable_is_field_id('<img src=x onerror=alert(1)>'));
        $this->assertFalse(apbct__formidable_is_field_id('img src=x onerror=alert(1)'));
        $this->assertTrue(apbct__formidable_is_field_id('12'));
        $this->assertTrue(apbct__formidable_is_field_id(12));
    }

    public function test_ajax_response_key_is_numeric_when_post_contains_markup_key()
    {
        $_POST['item_meta'] = array(
            '6' => 'stop_email@example.com',
            '<img src=x onerror=alert(document.domain)>' => 'lab',
        );
        Post::getInstance()->variables = array();

        $key = apbct__formidable_get_key_field_for_ajax_response(array());

        $this->assertSame('113', $key);
        $this->assertSame(1, preg_match('/^[0-9]+$/', $key));
    }

    public function test_ajax_response_key_uses_second_real_field()
    {
        $_POST['item_meta'] = array(
            '6' => 'first',
            '8' => 'second',
        );
        Post::getInstance()->variables = array();

        $key = apbct__formidable_get_key_field_for_ajax_response(array());

        $this->assertSame('8', $key);
    }
}
