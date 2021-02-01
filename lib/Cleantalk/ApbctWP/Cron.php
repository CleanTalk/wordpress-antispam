<?php

namespace Cleantalk\ApbctWP;

/**
 * CleanTalk Cron class
 *
 * @package Antispam by CleanTalk
 * @subpackage Cron
 * @Version 2.1.1
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 *
 */

class Cron
{
    public $tasks = array(); // Array with tasks
    public $tasks_to_run = array(); // Array with tasks which should be run now
    public $tasks_completed = array(); // Result of executed tasks
    
    // Currently selected task
    public  $task;
    private $handler;
    private $period;
    private $next_call;
    private $params;
    
    // Option name with cron data
    const CRON_OPTION_NAME = 'cleantalk_cron';
    
    // Interval in seconds for restarting the task
    const TASK_EXECUTION_MIN_INTERVAL = 120;
    
    /**
     * Cron constructor.
     * Getting tasks option.
     */
    public function __construct()
    {
        $this->tasks = self::getTasks();
    }
    
    /**
     * Getting all tasks
     *
     * @return array|bool|mixed|void
     */
    public static function getTasks(){
        $tasks = get_option(self::CRON_OPTION_NAME);
        return empty($tasks) ? array() : $tasks;
    }
    
    /**
     * Adding new cron task
     *
     * @param $task
     * @param $handler
     * @param $period
     * @param null $first_call
     * @param array $params
     *
     * @return bool
     */
    public static function addTask($task, $handler, $period, $first_call = null, $params = array())
    {
        // First call time() + preiod
        $first_call = !$first_call ? time()+$period : $first_call;
        
        $tasks = self::getTasks();
        
        if( isset( $tasks[ $task ] ) ){
            return false;
        }
        
        // Task entry
        $tasks[$task] = array(
            'handler' => $handler,
            'next_call' => $first_call,
            'period' => $period,
            'params' => $params,
        );
        
        return update_option(self::CRON_OPTION_NAME, $tasks);
    }
    
    /**
     * Removing cron task
     *
     * @param $task
     *
     * @return bool
     */
    public static function removeTask($task)
    {
        $tasks = self::getTasks();
        
        if( ! isset( $tasks[ $task ] ) ){
            return false;
        }
        
        unset($tasks[$task]);
        
        return update_option(self::CRON_OPTION_NAME, $tasks);
    }
    
    // Updates cron task, create task if not exists
    public static function updateTask($task, $handler, $period, $first_call = null, $params = array()){
        self::removeTask($task);
        self::addTask($task, $handler, $period, $first_call, $params);
    }
    
    /**
     * Getting tasks which should be run
     *
     * @return bool|array
     */
    public static function checkTasks()
    {
        $tasks = self::getTasks();
        
        // No tasks to run
        if( empty( $tasks ) ){
            return false;
        }
        
        $tasks_to_run = array();
        foreach($tasks as $task => &$task_data){
            
            if(
                ! isset( $task_data['processing'], $task_data['last_call'] ) ||
                ( $task_data['processing'] === true && time() - $task_data['last_call'] > self::TASK_EXECUTION_MIN_INTERVAL )
            ){
                $task_data['processing'] = false;
                $task_data['last_call'] = 0;
            }
            
            if(
                $task_data['processing'] === false &&
                $task_data['next_call'] <= time() // default condition
            ){
                
                $task_data['processing'] = true;
                $task_data['last_call'] = time();
                
                $tasks_to_run[] = $task;
            }

            // Hard bug fix
            if( ! isset( $task_data['params'] ) ) {
                $task_data['params'] = array();
            }
            
        }
        
        self::saveTasks( $tasks );
        
        return $tasks_to_run;
    }
    
    /**
     * Run all tasks from $this->tasks_to_run.
     * Saving all results to (array) $this->tasks_completed
     *
     * @return void
     */
    public function runTasks()
    {
        global $apbct;
        
        if( empty( $this->tasks_to_run ) ){
            return;
        }
        
        foreach($this->tasks_to_run as $task){
            
            $this->selectTask($task);
            
            if(function_exists($this->handler)){
                
                $result = call_user_func_array($this->handler, isset($this->params) ? $this->params : array());
                
                if(empty($result['error'])){
                    $this->tasks_completed[$task] = true;
                    $apbct->error_delete($task, 'save_data', 'cron');
                }else{
                    $this->tasks_completed[$task] = false;
                    $apbct->error_add($task, $result, 'cron');
                }
                
            }else{
                $this->tasks_completed[$task] = false;
                $apbct->error_add($task, $this->handler.'_IS_NOT_EXISTS', 'cron');
            }
            
            $this->saveTask($task);
            
        }
        
        //* Merging executed tasks with updated during execution
        $tasks = self::getTasks();
        
        foreach($tasks as $task => $task_data){
            
            // Task where added during execution
            if(!isset($this->tasks[$task])){
                $this->tasks[$task] = $task_data;
                continue;
            }
            
            // Task where updated during execution
            if($task_data !== $this->tasks[$task]){
                $this->tasks[$task] = $task_data;
                continue;
            }
            
            // Setting next call depending on results
            if(isset($this->tasks[$task], $this->tasks_completed[$task])){
                $this->tasks[$task]['next_call'] = $this->tasks_completed[$task]
                    ? time() + $this->tasks[$task]['period']
                    : time() + round($this->tasks[$task]['period']/4);
            }
            
            if(empty($this->tasks[$task]['next_call']) || $this->tasks[$task]['next_call'] < time()){
                $this->tasks[$task]['next_call'] = time() + $this->tasks[$task]['period'];
            }
            
        }
        
        // Task where deleted during execution
        $tmp = $this->tasks;
        foreach($tmp as $task => $task_data){
            if( ! isset( $tasks[ $task ] ) ){
                unset( $this->tasks[ $task ] );
            }
        }
        
        //*/ End of merging
        
        self::saveTasks( $this->tasks );
    }
    
    /**
     * Select task in private properties for comfortable use
     *
     * @param $task
     */
    private function selectTask($task)
    {
        $this->task      = $task;
        $this->handler   = $this->tasks[$task]['handler'];
        $this->period    = $this->tasks[$task]['period'];
        $this->next_call = $this->tasks[$task]['next_call'];
        $this->params    = isset($this->tasks[$task]['params']) ? $this->tasks[$task]['params'] : array();
    }
    
    /**
     * Save task in private properties for comfortable use
     *
     * @param null $task
     */
    private function saveTask( $task = null )
    {
        $task = $task ?: $this->task;
        
        $this->tasks[$task]['handler']   = $this->handler;
        $this->tasks[$task]['period']    = $this->period;
        $this->tasks[$task]['next_call'] = $this->next_call;
        $this->tasks[$task]['params']    = $this->params;
    }
    
    /**
     * Save option with tasks
     *
     * @param array $tasks
     */
    public static function saveTasks( $tasks = array() )
    {
        update_option( self::CRON_OPTION_NAME, $tasks );
    }
    
    /**
     * @param array $tasks_to_run
     */
    public function setTasksToRun( $tasks_to_run ){
        $this->tasks_to_run = $tasks_to_run;
    }
}
