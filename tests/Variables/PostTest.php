<?php

use Cleantalk\Variables\Post;
use PHPUnit\Framework\TestCase;

class PostTest extends TestCase
{
    public function test_getAndSanitize_string(){
        $input = 'string';
        Post::getInstance()->getAndSanitize($input);
        $this->assertEquals($input, 'string');
    }

    public function test_getAndSanitize_array(){
        $input = ['value' => 'string'];
        Post::getInstance()->getAndSanitize($input);
        $this->assertEquals($input, ['value' => 'string']);
    }

    public function test_getAndSanitize_nestedArray()
    {
        $input  = ['value' => ['nested_value' => 'string']];
        Post::getInstance()->getAndSanitize($input);
        $this->assertEquals($input, ['value' => ['nested_value' => 'string']]);
    }

    public function test_getAndSanitize_nestedHugeArray()
    {
        $input  = [
            'value' => [
                'nested_value_1' => [
                    'nested_value_2' => [
                        'nested_value_3' => [
                            'nested_value_4' => [
                                'nested_value_5' => [
                                    'nested_value_6' => [
                                        'nested_value7' => [
                                            'nested_value_8' => [
                                                'nested_value_9' => [
                                                    'nested_value_10' => [
                                                        'nested_value_11' => 'string'
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $check = $input;
        Post::getInstance()->getAndSanitize($input);
        $this->assertEquals($input, $check);
    }

    public function test_getAndSanitize_stripsHtmlFromArrayKeys()
    {
        $payload = '<img src=x onerror=alert(document.domain)>';
        $input   = array(
            '6'     => 'stop_email@example.com',
            $payload => 'lab',
        );

        $sanitized = Post::getInstance()->getAndSanitize($input);

        $this->assertSame(array('6' => 'stop_email@example.com'), $sanitized);
        $this->assertArrayNotHasKey($payload, $sanitized);
    }

    public function test_getAndSanitize_stripsNestedHtmlKeys()
    {
        $payload = '<svg onload=alert(1)>';
        $input   = array(
            'item' => array(
                $payload => 'nested',
                '9'      => 'kept',
            ),
        );

        $sanitized = Post::getInstance()->getAndSanitize($input);

        $this->assertSame(
            array(
                'item' => array(
                    '9' => 'kept',
                ),
            ),
            $sanitized
        );
    }

    public function test_getAndSanitize_htmlKeyDoesNotOverwriteFieldId()
    {
        $input = array(
            '6' => 'checked-value',
            '<b>6</b>' => 'spoofed-value',
            "6\0" => 'null-spoofed-value',
        );

        $sanitized = Post::getInstance()->getAndSanitize($input);

        $this->assertSame('checked-value', $sanitized[6]);
        $this->assertCount(1, $sanitized);
    }
}
