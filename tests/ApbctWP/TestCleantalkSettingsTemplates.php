<?php

use Cleantalk\ApbctWP\CleantalkSettingsTemplates;

class TestCleantalkSettingsTemplates extends PHPUnit\Framework\TestCase {

	public function testGet_options_template_ok() {
		$this->assertIsArray(  CleantalkSettingsTemplates::get_options_template( getenv("CLEANTALK_TEST_API_KEY") ) );
	}
	public function testGet_options_template_not_ok() {
		$this->assertIsArray(  CleantalkSettingsTemplates::get_options_template( 'wrong_key' ) );
	}
}
