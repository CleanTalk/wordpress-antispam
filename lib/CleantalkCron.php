<?php

/*
*	CleanTalk cron class
*	Version 1.0
*/

class CleantalkCron
{
	public $tasks = array(); // Array with tasks
	public $tasks_to_run = array(); // Array with tasks which should be run now
	public $tasks_completed = array(); // Result of executed tasks
	
	// Currently selected task
	private $task;
	private $handler;
	private $period;
	private $next_call;
	
	// Option name with cron data
	const CRON_OPTION_NAME = 'cleantalk_cron';
	
	// Getting tasks option
	public function __construct()
	{
		$tasks = get_option(self::CRON_OPTION_NAME);
		$this->tasks = empty($tasks) ? array() : $tasks;
	}
	
	// Adding new cron task
	static public function addTask($task, $handler, $period, $first_call = null)
	{		
		// First call time() + preiod
		$first_call = !$first_call ? time()+$period : $first_call;
		
		$tasks = get_option(self::CRON_OPTION_NAME);
		$tasks = empty($tasks) ? array() : $tasks;
		
		if(isset($tasks[$task]))
			return false;
		
		// Task entry
		$tasks[$task] = array(
			'handler' => $handler,
			'next_call' => $first_call,
			'period' => $period,
		);
		
		update_option(self::CRON_OPTION_NAME, $tasks);
		
		return true;
	}
	
	// Removing cron task
	static public function removeTask($task)
	{		
		$tasks = get_option(self::CRON_OPTION_NAME);
		$tasks = empty($tasks) ? array() : $tasks;
		
		if(!isset($tasks[$task]))
			return false;
		
		unset($tasks[$task]);
		
		update_option(self::CRON_OPTION_NAME, $tasks);
		
		return true;	
	}
	
	// Updates cron task, creates task if not exists
	static public function updateTask($task, $handler, $period, $first_call = null){
		self::removeTask($task);
		self::addTask($task, $handler, $period, $first_call = null);
	}
	
	// Getting tasks which should be run. Putting tasks that should be run to $this->tasks_to_run
	public function checkTasks()
	{
		if(empty($this->tasks))
			return true;
		
		foreach($this->tasks as $task => $task_data){
			
			if($task_data['next_call'] <= time())
				$this->tasks_to_run[] = $task;
			
		}unset($task, $task_data);
		
		return $this->tasks_to_run;
	}
	
	// Run all tasks from $this->tasks_to_run. Saving all results to (array) $this->tasks_completed
	public function runTasks()
	{
		if(empty($this->tasks_to_run))
			return true;
		
		foreach($this->tasks_to_run as $task){
			
			$this->selectTask($task);
			
			if(function_exists($this->handler)){
				$this->tasks_completed[$task] = call_user_func($this->handler);
				$this->next_call =  time() + $this->period;
			}else{
				$this->tasks_completed[$task] = false;
			}
			
			$this->saveTask($task);
			
		}unset($task, $task_data);
		
		$this->saveTasks();
		
		return $this->tasks_completed;
	}
	
	// Select task in private properties for comfortable use.
	private function selectTask($task)
	{
		$this->task      = $task;
		$this->handler   = $this->tasks[$task]['handler'];
		$this->period    = $this->tasks[$task]['period'];
		$this->next_call = $this->tasks[$task]['next_call'];
	}
	
	// Save task in private properties for comfortable use
	private function saveTask($task)
	{
		$task                            = $this->task;
		$this->tasks[$task]['handler']   = $this->handler;
		$this->tasks[$task]['period']    = $this->period;
		$this->tasks[$task]['next_call'] = $this->next_call;
	}
	
	// Save option with tasks
	private function saveTasks()
	{
		update_option(self::CRON_OPTION_NAME, $this->tasks);
	}
}
