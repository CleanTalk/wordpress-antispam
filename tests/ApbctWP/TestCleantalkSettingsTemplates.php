<?php

use Cleantalk\ApbctWP\CleantalkSettingsTemplates;
use Cleantalk\ApbctWP\DB;
use Cleantalk\ApbctWP\State;

class TestCleantalkSettingsTemplates extends PHPUnit\Framework\TestCase {

    private $apbct_copy;
    public function setUp(): void
    {
        global $apbct;
        $this->apbct_copy = $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
    }

    protected function tearDown(): void
    {
        global $apbct;
        $apbct = $this->apbct_copy;

        parent::tearDown();
    }
	public function testGet_options_template_ok() {
		$this->assertIsArray(  CleantalkSettingsTemplates::getOptionsTemplate( getenv("CLEANTALK_TEST_API_KEY") ) );
	}
	public function testGet_options_template_not_ok() {
		$this->assertIsArray(  CleantalkSettingsTemplates::getOptionsTemplate( 'wrong_key' ) );
	}
}
