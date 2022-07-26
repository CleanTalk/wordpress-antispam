<?php

use Cleantalk\Common\Helper;
use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    public function test_http__multi_request_error() {
    	$this->assertArrayHasKey( 'error', Helper::httpMultiRequest( 'string' ) );
	    $this->assertArrayHasKey( 'error', Helper::httpMultiRequest( '' ) );
	    $this->assertArrayHasKey( 'error', Helper::httpMultiRequest( array() ) );
	    $this->assertArrayHasKey( 'error', Helper::httpMultiRequest( array(array('https://google.com')) ) );
    }

	public function test_http__multi_request_success() {
    	$res = Helper::httpMultiRequest( array('https://google.com', 'https://cleantalk.org') );
		$this->assertIsArray( $res );
		$this->assertContainsOnly( 'string', $res );
	}
}
