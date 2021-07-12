<?php

use Cleantalk\ApbctWP\Cron;
use PHPUnit\Framework\TestCase;

class TestApbctCron extends TestCase {

	protected $cronObj;

	protected $tasks;

	public function setUp()
	{

		$this->tasks = array(
			'sfw_update' => array(
				'handler' => 'apbct_sfw_update__init',
				'next_call' => time() + 86400,
				'period' => 86400,
				'params' => array(),
				'processing' => null,
				'last_call' => 0,
			),
			'send_sfw_logs' => array(
				'handler' => 'apbct_sfw_send_logs',
				'next_call' => time() + 3600,
				'period' => 3600,
				'params' => array(),
				'processing' => null,
				'last_call' => 0,
			),
		);

		global $apbct;
		$apbct->stats['cron']['last_start'] = 0;
		$this->cronObj = new Cron();
		update_option( $this->cronObj->getCronOptionName(), $this->tasks );
	}

	public function testSaveTasks()
	{
		$tasks = $this->tasks;
		//Some modify tasks and try to save it
		++ $tasks['sfw_update']['next_call'];
		++ $tasks['send_sfw_logs']['next_call'];
		self::assertTrue( $this->cronObj->saveTasks( $tasks ) );
	}

	public function testRemoveTask()
	{
		self::assertTrue( $this->cronObj->removeTask( 'sfw_update' ) );
	}

	public function testRemoveTaskWrong()
	{
		self::assertFalse( $this->cronObj->removeTask( 'wrong_task_name' ) );
	}

	public function testAddTask()
	{
		self::assertTrue( $this->cronObj->addTask( 'some_task_name', 'some_task_handler', 86400 ) );
	}

	public function testGetTasks()
	{
		self::assertIsArray( $this->cronObj->getTasks() );
	}

	public function testUpdateTask()
	{
		self::assertTrue( $this->cronObj->updateTask( 'sfw_update', 'apbct_sfw_update__init', 86400 ) );
	}

	public function testCompareStructureAfterUpdatingTask()
	{
		$tasks = $this->tasks;
		//Some modify tasks and try to save it
		++ $tasks['sfw_update']['next_call'];
		update_option( $this->cronObj->getCronOptionName(), $tasks );
		//Do updating the task
		$this->cronObj->updateTask( 'sfw_update', 'apbct_sfw_update__init', 86400 );
		self::assertIsArray( $this->cronObj->getTasks() );
		$tasks_from_obj = $this->cronObj->getTasks();
		self::assertArrayHasKey( 'sfw_update', $tasks_from_obj );
		self::assertArrayHasKey( 'send_sfw_logs', $tasks_from_obj );
		foreach( $tasks_from_obj as $task_from_obj ) {
			self::assertIsArray( $task_from_obj );
			self::assertArrayHasKey( 'handler', $task_from_obj );
			self::assertArrayHasKey( 'next_call', $task_from_obj );
			self::assertIsInt( $task_from_obj['next_call'] );
			self::assertGreaterThanOrEqual( time() + $task_from_obj['period'], $task_from_obj['next_call'] );
			self::assertArrayHasKey( 'period', $task_from_obj );
			self::assertIsInt( $task_from_obj['period'] );
			self::assertArrayHasKey( 'params', $task_from_obj );
		}
	}
}
