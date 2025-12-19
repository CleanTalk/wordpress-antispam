<?php

use Cleantalk\Antispam\Cleantalk;
use Cleantalk\Antispam\CleantalkMock;
use Cleantalk\Antispam\CleantalkRequest;
use Cleantalk\ApbctWP\State;

class CleantalkTest extends \PHPUnit\Framework\TestCase
{
	protected $ct;
	protected $ct_request;

	public function setUp()
	{
        global $apbct;
        $apbct = new State( 'cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats') );

		$this->ct = new CleantalkMock();
		$this->ct->server_url = APBCT_MODERATE_URL;
		$this->ct_request = new CleantalkRequest();
		$this->ct_request->auth_key = getenv("CLEANTALK_TEST_API_KEY");
	}

	public function testIsAllowMessage()
	{
		$this->ct_request->sender_email = 's@cleantalk.org';
		$this->ct_request->message = 'stop_word bad message';
		$result = $this->ct->isAllowMessage($this->ct_request);
		$this->assertEquals(0, $result->allow);
		$this->assertEquals('stop_word', $result->stop_word);

		$this->ct_request->message = '';
		$this->ct_request->sender_email = '';
	}

	// public function testIsAllowUser()
	// {
	// 	$this->ct_request->sender_email = 's@cleantalk.org';
	// 	$result = $this->ct->isAllowUser($this->ct_request);
	// 	$this->assertEquals(0, $result->allow);

	// 	$this->ct_request->sender_email = '';
	// }
}
