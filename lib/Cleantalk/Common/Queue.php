<?php

namespace Cleantalk\Common;

abstract class Queue
{
    const QUEUE_NAME = 'sfw_update_queue';

    public $queue;

    private $unstarted_stage;

    /**
     * Process identifier
     *
     * @var int
     */
    private $pid;

    public function __construct()
    {
        $this->pid = mt_rand(0, mt_getrandmax());

        $queue = $this->getQueue();
        if ($queue !== false && isset($queue['stages'])) {
            $this->queue = $queue;
        } else {
            $this->queue = array(
                'started'  => time(),
                'finished' => '',
                'stages'   => array(),
            );
        }
    }

    abstract public function getQueue();

    abstract public function saveQueue($queue);

    /**
     * Refreshes the $this->queue from the DB
     *
     * @return void
     */
    public function refreshQueue()
    {
        $this->queue = $this->getQueue();
    }

    /**
     * @param string $stage_name
     * @param array $args
     */
    public function addStage($stage_name, $args = array(), $accepted_tries = 3)
    {
        $this->queue['stages'][] = array(
            'name'   => $stage_name,
            'status' => 'NULL',
            'tries'  => '0',
            'accepted_tries'  => $accepted_tries,
            'args'   => $args,
            'pid'    => null,
        );
        $this->saveQueue($this->queue);
    }

    public function executeStage()
    {
        global $apbct;

        $stage_to_execute = null;

        if ($this->hasUnstartedStages()) {
            $this->queue['stages'][$this->unstarted_stage]['status'] = 'IN_PROGRESS';
            $this->queue['stages'][$this->unstarted_stage]['start']  = time();
            $this->queue['stages'][$this->unstarted_stage]['pid']    = $this->pid;

            $this->saveQueue($this->queue);

            sleep(2);

            $this->refreshQueue();

            if ($this->queue['stages'][$this->unstarted_stage]['pid'] !== $this->pid) {
                return true;
            }

            $stage_to_execute = &$this->queue['stages'][$this->unstarted_stage];
        }

        if ($stage_to_execute) {
            if (is_callable($stage_to_execute['name'])) {
                ++$stage_to_execute['tries'];

                if (! empty($stage_to_execute['args'])) {
                    $result = $stage_to_execute['name']($stage_to_execute['args']);
                } else {
                    $result = $stage_to_execute['name']();
                }

                if (isset($result['error'])) {
                    $stage_to_execute['status'] = 'NULL';
                    $stage_to_execute['error'][]  = $result['error'];
                    if (isset($result['update_args']['args'])) {
                        $stage_to_execute['args'] = $result['update_args']['args'];
                    }
                    $this->saveQueue($this->queue);
                    $accepted_tries = isset($stage_to_execute['accepted_tries']) ? $stage_to_execute['accepted_tries'] : 3;
                    if ($stage_to_execute['tries'] >= $accepted_tries) {
                        $stage_to_execute['status'] = 'FINISHED';
                        $this->saveQueue($this->queue);
                        return $result;
                    }

                    return \Cleantalk\ApbctWP\Helper::httpRequestRcToHost(
                        'sfw_update__worker',
                        array(
                            'firewall_updating_id' => $apbct->fw_stats['firewall_updating_id'],
                            'stage'                => 'Repeat ' . $stage_to_execute['name']
                        ),
                        array('async')
                    );
                }

                if (isset($result['next_stage'])) {
                    $this->addStage(
                        $result['next_stage']['name'],
                        isset($result['next_stage']['args']) ? $result['next_stage']['args'] : array(),
                        isset($result['next_stage']['accepted_tries']) ? $result['next_stage']['accepted_tries'] : 3
                    );
                }

                if (isset($result['next_stages']) && count($result['next_stages'])) {
                    foreach ($result['next_stages'] as $next_stage) {
                        $this->addStage(
                            $next_stage['name'],
                            isset($next_stage['args']) ? $next_stage['args'] : array(),
                            isset($result['next_stage']['accepted_tries']) ? $result['next_stage']['accepted_tries'] : 3
                        );
                    }
                }

                $stage_to_execute['status'] = 'FINISHED';
                $this->saveQueue($this->queue);

                return $result;
            }

            return array('error' => $stage_to_execute['name'] . ' is not a callable function.', 'status' => 'FINISHED');
        }

        return null;
    }

    public function isQueueInProgress()
    {
        if (count($this->queue['stages']) > 0) {
            $this->unstarted_stage = array_search('IN_PROGRESS', array_column($this->queue['stages'], 'status'), true);

            return is_int($this->unstarted_stage);
        }

        return false;
    }

    public function isQueueFinished()
    {
        return ! $this->isQueueInProgress() && ! $this->hasUnstartedStages();
    }

    /**
     * Checks if the queue is over
     *
     * @return bool
     */
    public function hasUnstartedStages()
    {
        if (count($this->queue['stages']) > 0) {
            $this->unstarted_stage = array_search('NULL', array_column($this->queue['stages'], 'status'), true);

            return is_int($this->unstarted_stage);
        }

        return false;
    }
}
