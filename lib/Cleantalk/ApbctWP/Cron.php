<?php

namespace Cleantalk\ApbctWP;

class Cron extends \Cleantalk\Common\Cron
{
    /**
     * Get timestamp last Cron started.
     *
     * @return int timestamp
     */
    public function getCronLastStart()
    {
        return get_option('cleantalk_cron_last_start');
    }

    /**
     * Save timestamp of running Cron.
     *
     * @return bool
     */
    public function setCronLastStart()
    {
        return update_option('cleantalk_cron_last_start', time());
    }

    /**
     * Save option with tasks
     *
     * @param array $tasks
     *
     * @return bool
     */
    public function saveTasks($tasks)
    {
        return update_option($this->cron_option_name, $tasks);
    }

    /**
     * Getting all tasks
     *
     * @return array
     */
    public function getTasks()
    {
        $tasks = get_option($this->cron_option_name);

        return empty($tasks) ? array() : $tasks;
    }
}
