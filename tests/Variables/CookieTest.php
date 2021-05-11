<?php

namespace Cleantalk\ApbctWP\Variables;

use PHPUnit\Framework\TestCase;

class CookieTest extends TestCase {

	public function setUp()
	{
		$_COOKIE = array( 'variable_arr' => '%7B%22testwebsite.test%5C%2Ftestpage%5C%2Ftestsubpage%5C%2F%22%3A%5B1620723357%5D%7D', 'variable' => 'value' );
	}

	public function testGet()
	{
		$var = Cookie::get( 'variable' );
		self::assertEquals($var, $_COOKIE['variable']);
		$wrong_var = Cookie::get( 'wrong_variable' );
		self::assertEmpty($wrong_var);
		$arr = Cookie::get( 'variable_arr', array(), 'array' );
		$this->assertIsArray($arr);
	}

	protected function tearDown()
	{
		unset( $_COOKIE['variable_arr'], $_COOKIE['variable'] );
	}

}
