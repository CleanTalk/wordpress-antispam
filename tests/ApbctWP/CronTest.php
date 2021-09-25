<?php

class CronTest extends PHPUnit\Framework\TestCase
{
    protected $cron_object;

    protected $tasks = array(
	    'sfw_update' => array(
		    'handler' => 'apbct_sfw_update__init',
		    'next_call' => 1613737797,
		    'period' => 86400,
		    'params' => array(),
		    'processing' => null,
		    'last_call' => 0,
	    ),
	    'send_sfw_logs' => array(
		    'handler' => 'apbct_sfw_send_logs',
		    'next_call' => 1613674684,
		    'period' => 3600,
		    'params' => array(),
		    'processing' => null,
		    'last_call' => 0,
	    ),
    );

    protected function setUp()
    {
        update_option('cleantalk_cron', $this->tasks);
        $this->cron_object = new \Cleantalk\ApbctWP\Cron();
    }

    public function testAddTaskDouble() {
        self::assertFalse( $this->cron_object->addTask( 'sfw_update', 'apbct_sfw_update', 86400, time() + 60 ) );
    }

    public function testAddTask() {
        self::assertTrue( $this->cron_object->addTask( 'new_task', 'apbct_new_task_handler', 86400, time() + 60 ) );
    }

    public function testRemoveTaskWrong() {
        self::assertFalse( $this->cron_object->removeTask( 'sfw_update_wrong' ) );
    }

    public function testRemoveTask() {
        self::assertTrue( $this->cron_object->removeTask( 'sfw_update' ) );
    }

    public function testUpdateTask() {
        self::assertTrue( $this->cron_object->updateTask( 'sfw_update', 'apbct_sfw_update', 86400, time() + 60 ) );
    }

	public function testUpdateTaskWrong() {
        self::assertFalse( $this->cron_object->updateTask( 'sfw_update_wrong', 'apbct_sfw_update', 86400, time() + 60 ) );
    }

    public function testCheckTasks() {
        self::assertIsArray( $this->cron_object->checkTasks() );
    }

    public function testRunTasks() {
        $cron = new \Cleantalk\ApbctWP\Cron();
        $cron->checkTasks();
        $tasks_to_run = array( 'send_sfw_logs' );
        self::assertEquals( array('send_sfw_logs'=>true), $cron->runTasks( $tasks_to_run ) );
    }

}

function apbct_sfw_send_logs() {
	return true;
}