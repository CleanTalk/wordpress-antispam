<?php

class CronTest extends PHPUnit\Framework\TestCase
{
    protected $cron_object;

    protected function setUp()
    {
        $tasks = array(
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
        $authorizeNet = $this->getMockBuilder('\Cleantalk\Common\Cron')
            ->setMethods(array())
            ->getMock();
        $stub = $this->getMockForAbstractClass('\Cleantalk\Common\Cron');
        $stub->expects( self::any() )
            ->method('saveTasks')
            ->willReturn(true);
        $stub->expects( self::any() )
            ->method('setCronLastStart')
            ->willReturn(true);
        $stub->expects( self::any() )
            ->method('getCronLastStart')
            ->willReturn(123456);
        $stub->expects( self::any() )
            ->method('getTasks')
            ->willReturn($tasks);
        $this->cron_object = $stub;

        $reflection = new \ReflectionClass( $this->cron_object );
        $reflection_property = $reflection->getProperty( 'tasks' );
        $reflection_property->setAccessible( true );
        $reflection_property->setValue( $this->cron_object, $tasks );
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

    public function testCheckTasks() {
        self::assertIsArray( $this->cron_object->checkTasks() );
    }

    public function testRunTasks() {
        $tasks_to_run = array( 'send_sfw_logs' );
        self::assertEquals( array('send_sfw_logs'=>true), $this->cron_object->runTasks( $tasks_to_run ) );
    }

}

function apbct_sfw_send_logs() {
	return true;
}

function apbct_sfw_update__init() {
	return true;
}