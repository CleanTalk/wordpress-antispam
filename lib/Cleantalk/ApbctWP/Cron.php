<?php

namespace Cleantalk\ApbctWP;

class Cron extends \Cleantalk\Common\Cron
{
    /**
     * Get fresh instance of Cron
     *
     * @return Cron
     */
    public static function getInstance()
    {
        return new self();
    }

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
        global $wpdb;

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s AND " . rand(1, 100000) . " > 0",
                $this->cron_option_name
            )
        );

        if ( ! $result || ! is_serialized($result) ) {
            return array();
        }

        $unserialize_options = array('allowed_classes' => false);
        $unserialized        = @unserialize(trim($result), $unserialize_options);

        if ( is_string($unserialized) && is_serialized($unserialized) ) {
            $unserialized = @unserialize(trim($unserialized), $unserialize_options);
        }

        return is_array($unserialized) ? $unserialized : array();
    }
}
