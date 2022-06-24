<?php

namespace Cleantalk\ApbctWP;

class RemoteCallsCounter
{
    /**
     * Counter reset period in seconds
     */
    const RESET_PERIOD = 86400;

    /**
     * Number of allowed calls during the reset period
     */
    const CALL_LIMIT = 600;

    /**
     * Maximum number of blocked calls
     */
    const LOG_LIMIT = 10;

    /**
     * Current counter state
     */
    private $state;

    /**
     * WP option name
     */
    private $option_name = 'cleantalk_rc_counter';

    /**
     * Data for logger
     */
    private $logging_data;

    /**
     * Constructor:
     * getting or create counter state
     *
     * @param array | string | null $logging_data
     */
    public function __construct($logging_data = null)
    {
        $current_state = $this->getCounterState();

        // Create new counter, first start
        if (!$current_state) {
            $current_state = $this->createCounterState();
        }

        $this->state = $current_state;

        // Logger
        if ($logging_data) {
            $this->logging_data = $logging_data;
        }
    }

    /**
     * Get counter state from DB
     *
     * @return array
     */
    private function getCounterState()
    {
        return get_option($this->option_name);
    }

    /**
     * Set counter state to DB
     *
     * @param array $state
     *
     * @return boolean
     */
    private function setCounterState($state)
    {
        return update_option($this->option_name, $state);
    }

    /**
     * Create counter state
     *
     * @return array
     */
    private function createCounterState()
    {
        $this->state = array(
            'counter_start_time' => time(),
            'count_calls' => 1
        );

        $this->setCounterState($this->state);

        return $this->state;
    }

    /**
     * What happens when the number of calls is exceeded
     *
     * @return void
     */
    private function actionExceedingLimit()
    {
        // Logger
        if (($this->state['count_calls'] - self::CALL_LIMIT) <= self::LOG_LIMIT) {
            $logger = new RemoteCallsLogger($this->logging_data);
            $logger->writeLog();
        }

        die;
    }

    /**
     * Full counter launch with script shutdown
     *
     * @return void
     */
    public function execute()
    {
        // Checking the expiration time of the reset period
        $current_time = time();
        $counter_start_time = $this->state['counter_start_time'];
        $time_difference = $current_time - $counter_start_time;

        if ($time_difference > self::RESET_PERIOD) {
            $this->createCounterState();
            return;
        }

        // Checking for exceeding the number of calls
        $current_count_calls = $this->state['count_calls'];

        if ($current_count_calls >= self::CALL_LIMIT) {
            // We save the number of calls to log the last few calls
            ++$this->state['count_calls'];
            $this->setCounterState($this->state);

            $this->actionExceedingLimit();
        }

        // All right, set updated state
        ++$this->state['count_calls'];
        $this->setCounterState($this->state);
    }
}
