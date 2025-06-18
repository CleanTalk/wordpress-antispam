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
}
