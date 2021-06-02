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
		global $apbct;
		return $apbct->stats['cron']['last_start'];
	}

	/**
	 * Save timestamp of running Cron.
	 *
	 * @return bool
	 */
	public function setCronLastStart()
	{
		global $apbct;
		$apbct->stats['cron']['last_start'] = time();
		$apbct->save('stats');
		return true;
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
