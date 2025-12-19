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
		$this->ct = new CleantalkMock();
		$this->ct->server_url = APBCT_MODERATE_URL;
		$this->ct_request = new CleantalkRequest();
		$this->ct_request->auth_key = "TEST_API_KEY";
	}

	public function testIsAllowMessage()
	{
		$this->ct_request->sender_email = 's@cleantalk.org';
		$this->ct_request->message = 'stop_word bad message';
		$result = $this->ct->isAllowMessage($this->ct_request);
		$this->assertEquals(0, $result->allow);
		$this->assertEquals('stop_word', $result->stop_words);

		$this->ct_request->message = '';
		$this->ct_request->sender_email = '';
	}

	public function testIsAllowUser()
	{
		$this->ct_request->sender_email = 's@cleantalk.org';
		$result = $this->ct->isAllowUser($this->ct_request);
		$this->assertEquals(0, $result->allow);

		$this->ct_request->sender_email = '';
	}
}
