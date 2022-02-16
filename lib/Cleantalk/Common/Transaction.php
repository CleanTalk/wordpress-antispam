<?php

namespace Cleantalk\Common;

use Cleantalk\Templates\Multiton;

abstract class Transaction
{
    use Multiton;

    /**
     * Time needed to perform an action
     *
     * @var int
     */
    private $action_time = 5;

    /**
     * Transaction ID option name
     *
     * @var string
     */
    private $tid_option_name;

    /**
     * @var string Option name with a start time of a transaction
     */
    private $start_time_option_name;

    /**
     * Alternative constructor
     *
     * @param $params array
     *
     * @return void
     */
    protected function init($params)
    {
        $params = array_merge(array(
            'action_time' => $this->action_time,
            'name'        => 'common',
        ), $params);

        $this->action_time            = $params['action_time'];
        $this->tid_option_name        = 'cleantalk_transaction__' . $params['name'] . '_id';
        $this->start_time_option_name = 'cleantalk_transaction__' . $params['name'] . '_start_time';
    }

    /**
     * Wrapper for self::getInstance()
     *
     * @param string $instance_name Name of the instance
     * @param int    $action_time_s
     *
     * @return Transaction
     */
    public static function get($instance_name, $action_time_s = 5)
    {
        return static::getInstance($instance_name, array('name' => $instance_name, 'action_time' => $action_time_s));
    }

    /**
     * Performs transaction. Set transaction timer.
     *
     * @return int|false|null
     *      <p>- Integer transaction ID on success.</p>
     *      <p>- false for duplicated request.</p>
     *      <p>- null on error.</p>
     */
    public function perform()
    {
        if ( $this->isTransactionInProcess() === true ) {
            return false;
        }

        $time_ms = microtime(true);
        if ( ! $this->setTransactionTimer() ) {
            return null;
        }
        $halt_time = microtime(true) - $time_ms;

        $tid = mt_rand(0, mt_getrandmax());
        $this->saveTID($tid);
        usleep((int)$halt_time + 1000);

        return $tid === $this->getTID()
            ? $tid
            : false;
    }

    /**
     * Save the transaction ID
     *
     * @param int    $tid
     *
     * @return void
     */
    private function saveTID($tid)
    {
        $this->setOption($this->tid_option_name, $tid);
    }

    /**
     * Get the transaction ID
     *
     * @return int|false
     */
    public function getTID()
    {
        return $this->getOption($this->tid_option_name, false);
    }

    /**
     * Shows if the transaction progress
     *
     * @return bool
     */
    private function isTransactionInProcess()
    {
        return time() - $this->getOption($this->start_time_option_name, 0) < $this->action_time;
    }

    /**
     * Set the time when transaction started
     *
     * @return bool
     */
    private function setTransactionTimer()
    {
        return $this->setOption($this->start_time_option_name, time());
    }

    /**
     * Set transaction data to the DB
     *
     * @param $option_name
     * @param $value
     *
     * @return bool
     */
    abstract protected function setOption($option_name, $value);

    /**
     * Get transaction data from the DB
     *
     * @param $option_name
     * @param $default
     *
     * @return int|false
     */
    abstract protected function getOption($option_name, $default);
}
