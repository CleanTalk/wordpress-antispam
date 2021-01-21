<?php

require_once 'lib/autoloader.php';

use Cleantalk\Common\Helper;
use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    public function setUp() : void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    public function testIp__get()
    {
        $this->assertEquals('127.0.0.1', Helper::ip__get());
    }
}
