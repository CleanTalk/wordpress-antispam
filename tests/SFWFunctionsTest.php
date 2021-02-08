<?php

namespace Cleantalk;

class SFWFunctionsTest extends \PHPUnit_Framework_TestCase {
	public function testApbct_is_remote_call()
	{
		$this->assertFalse(apbct_is_remote_call());
	}
}
