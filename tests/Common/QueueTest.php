<?php

use Cleantalk\Common\Queue;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase {

	public function setUp()
	{
		update_option('cleantalk_sfw_update_queue', array() );
	}

	public function testIsQueueInProgress() {
		$queue = new \Cleantalk\ApbctWP\Queue();
		$this->assertIsBool($queue->isQueueInProgress());
	}

	public function testIsQueueInProgress_false() {
		$queue = new \Cleantalk\ApbctWP\Queue();
		$queue->queue['stages'] = array();
		$this->assertFalse($queue->isQueueInProgress());
	}

	public function testIsQueueInProgress_true() {
		$queue = new \Cleantalk\ApbctWP\Queue();
		$queue->queue['stages'][] = array(
			'status' => 'NULL'
		);
		$queue->queue['stages'][] = array(
			'status' => 'IN_PROGRESS'
		);
		$queue->queue['stages'][] = array(
			'status' => 'FINISHED'
		);
		$this->assertTrue($queue->isQueueInProgress());
	}

}
