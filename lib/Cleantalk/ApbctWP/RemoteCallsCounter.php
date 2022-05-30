<?php

namespace Cleantalk\ApbctWP;

class RemoteCallsCounter
{
    /**
     * Counter reset period in seconds
     */
    const reset_period = 86400;

    /**
     * Number of allowed calls during the reset period
     */
    const call_limit = 600;

    /**
     * Current counter state
     */
    private $state;

    /**
     * WP option name
     */
    private $option_name = 'cleantalk_rc_counter';

    /**
     * Constructor: getting or create counter state
     */
    public function __construct()
    {
        $current_state = $this->getCounterState();

        // Create new counter, first start
        if (!$current_state) {
            $current_state = $this->createCounterState();
        }

        $this->state = $current_state;
    }

    /**
     * Get counter state from DB
     */
    private function getCounterState()
    {
        return get_option($this->option_name);
    }

    /**
     * Set counter state to DB
     */
    private function setCounterState($state)
    {
        return update_option($this->option_name, $state);
    }

    /**
     * Create counter state
     */
    private function createCounterState() {
        $this->state = array(
            'counter_start_time' => time(),
            'count_calls' => 1
        );

        $this->setCounterState($this->state);

        return $this->state;
    }

    /**
     * What happens when the number of calls is exceeded
     */
    private function actionExceedingLimit() {
        die;
    }

    /**
     * Full counter launch with script shutdown
     */
    public function execute()
    {
        // Checking the expiration time of the reset period
        $current_time = time();
        $counter_start_time = $this->state['counter_start_time'];
        $time_difference = $current_time - $counter_start_time;

        if ($time_difference > self::reset_period) {
            $this->createCounterState();
            return;
        }

        // Checking for exceeding the number of calls
        $current_count_calls = $this->state['count_calls'];

        if ($current_count_calls >= self::call_limit) {
            $this->actionExceedingLimit();
        }

        // All right, set updated state
        ++ $this->state['count_calls'];
        $this->setCounterState($this->state);
    }
}
