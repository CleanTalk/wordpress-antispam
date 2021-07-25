<?php


namespace Cleantalk\Common;


use Cleantalk\ApbctWP\CleantalkListTable;

abstract class Queue {

	const QUEUE_NAME = 'sfw_update_queue';

	public $queue;

	function __construct()
	{
		$queue = $this->getQueue();
		if( $queue !== false && isset( $queue['stages'] ) ) {
			$this->queue = $queue;
		} else {
			$this->queue = array(
				'started' => time(),
				'finished' => '',
				'stages' => array(),
			);
		}
	}

	abstract function getQueue();

	abstract static function clearQueue();

	abstract function saveQueue( $queue );

	/**
	 * @param string $stage_name
	 * @param false $is_last_stage
	 */
	public function addStage( $stage_name, $args = array(), $is_last_stage = '0' )
	{
		$this->queue['stages'][] = array(
			'name' => $stage_name,
			'status'  => 'NULL',
			'tries'   => '0',
			'args'    => $args,
			'is_last' => $is_last_stage
		);
		$this->saveQueue( $this->queue );
	}

	public function executeStage()
	{
		if( count( $this->queue['stages'] ) > 0 ) {
			foreach ( $this->queue['stages'] as & $stage ) {
				if( ( $stage['status'] === 'NULL' ) ) {

					error_log(var_export($stage['name'],1));
					$stage['status'] = 'IN_PROGRESS';

					if( is_callable( $stage['name'] ) ) {

						++$stage['tries'];

						if( ! empty( $stage['args'] ) ) {
							$result = $stage['name']( $stage['args'] );
						} else {
							$result = $stage['name']();
						}

						if( isset( $result['error'] ) ) {
							$stage['status'] = 'NULL';
							if( isset( $result['update_args']['args'] ) ) {
								$stage['args'] = $result['update_args']['args'];
							}
							$this->saveQueue( $this->queue );
							if( $stage['tries'] >= 3 ) {
								$stage['status'] = 'FINISHED';
								$stage['error'] = $result['error'];
								$this->saveQueue( $this->queue );
								return $result;
							}
							return \Cleantalk\ApbctWP\Helper::http__request__rc_to_host(
								'sfw_update__worker',
								array( 'stage' => 'Repeat ' . $stage['name'] ),
								array( 'async' )
							);
						}

						if( isset( $result['next_stage'] ) ) {
							$this->addStage(
								$result['next_stage']['name'],
								isset( $result['next_stage']['args'] ) ? $result['next_stage']['args'] : array(),
								isset( $result['next_stage']['is_last'] ) ? $result['next_stage']['is_last'] : false
							);
						}

						if( isset( $result['next_stages'] ) && count( $result['next_stages'] ) ) {
							foreach( $result['next_stages'] as $next_stage ) {
								$this->addStage(
									$next_stage['name'],
									isset( $next_stage['args'] ) ? $next_stage['args'] : array(),
									isset( $next_stage['is_last'] ) ? $next_stage['is_last'] : false
								);
							}
						}

						$stage['status'] = 'FINISHED';
						$this->saveQueue( $this->queue );

						return $result;

					}

					return array( 'error' => $stage['name'] . ' is not a callable function.' );

				}
			} unset( $stage );
		}
	}

	public function isStageWaiting() {
		if( count( $this->queue['stages'] ) > 0 ) {
			foreach ( $this->queue['stages'] as $stage ) {
				if ( $stage['status'] === 'IN_PROGRESS' ) {
					return true;
				}
			}
		}
		return false;
	}

	public function isQueueInProgress()
	{
		if( count( $this->queue['stages'] ) > 0 ) {
			foreach ( $this->queue['stages'] as $stage ) {
				if( $stage['status'] === 'FINISHED' ) {
					continue;
				}
				return true;
			}
		}
		return false;
	}

	public function isQueueFinished()
	{
		if( count( $this->queue['stages'] ) > 0 ) {
			foreach ( $this->queue['stages'] as $stage ) {
				if ( $stage['is_last'] && $stage['status'] === 'FINISHED' ) {
					return true;
				}
				if( $stage['status'] !== 'FINISHED' ) {
					return false;
				}
			}
		}
		return true;
	}

}