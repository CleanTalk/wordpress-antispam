<?php

use Cleantalk\Common\Helper;
use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    public function setUp()
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    public function testIp__get()
    {
        $this->assertEquals('127.0.0.1', Helper::ip__get());
    }

    public function test_http__multi_request_error() {
    	$this->assertArrayHasKey( 'error', Helper::http__multi_request( 'string' ) );
	    $this->assertArrayHasKey( 'error', Helper::http__multi_request( '' ) );
	    $this->assertArrayHasKey( 'error', Helper::http__multi_request( array() ) );
	    $this->assertArrayHasKey( 'error', Helper::http__multi_request( array(array('https://google.com')) ) );
    }

	public function test_http__multi_request_success() {
    	$res = Helper::http__multi_request( array('https://google.com') );
		$this->assertIsArray( $res );
		$this->assertContainsOnly( 'string', $res );
	}
}
