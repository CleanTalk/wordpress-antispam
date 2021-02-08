<?php

namespace Cleantalk;

class SFWFunctionsTest extends \PHPUnit_Framework_TestCase {

	public function testCt_sfw_send_logs()
	{
		$res = ct_sfw_send_logs( getenv( 'CLEANTALK_TEST_API_KEY') );
		$this->assertIsArray( $res );
		$this->assertArrayHasKey( 'rows', $res );
		$res = ct_sfw_send_logs();
		$this->assertIsArray( $res );
		$this->assertArrayHasKey( 'error', $res );
	}
}
