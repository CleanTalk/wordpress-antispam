<?php

namespace Cleantalk\ApbctWP;

class Cron extends \Cleantalk\Common\Cron {

	/**
	 * Get timestamp last Cron started.
	 *
	 * @return int timestamp
	 */
	public function getCronLastStart()
	{
		$cron_options = get_option( $this->cron_option_name );
		return ( ! empty( $cron_options ) && isset( $cron_options['last_start'] ) ) ? $cron_options['last_start'] : 0;
	}

	/**
	 * Save timestamp of running Cron.
	 *
	 * @return bool
	 */
	public function setCronLastStart()
	{
		return update_option( $this->cron_option_name, array('last_start' => time(), 'tasks' => $this->getTasks()) );
	}

	/**
	 * Save option with tasks
	 *
	 * @param array $tasks
	 *
	 * @return bool
	 */
	public function saveTasks( $tasks )
	{
		return update_option( $this->cron_option_name, $tasks );
	}

	/**
	 * Getting all tasks
	 *
	 * @return array
	 */
	public function getTasks()
	{
		$tasks = get_option( $this->cron_option_name );
		return empty( $tasks ) ? array() : $tasks;
	}
}
