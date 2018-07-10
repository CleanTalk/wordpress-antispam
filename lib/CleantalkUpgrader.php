<?php

/**
 * Antispam by CleanTalk plugin
 *
 * Class that extends the WP Core Plugin_Upgrader.
 *
 * @copyright  : http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since      : 1.0.0
 */

class CleantalkUpgrader extends Plugin_Upgrader
{
	
	public $result;
	
	public $apbct_result = 'OK';
	
	public function upgrade_strings() {
		$this->strings['up_to_date'] = 'UP_TO_DATE';
		$this->strings['no_package'] = 'PACKAGE_NOT_AVAILABLE';
		/* translators: %s: package URL */
		$this->strings['remove_old_failed'] = 'COULD_NOT_REMOVE_OLD_PLUGIN';
		$this->strings['process_failed'] = 'PLUGIN_UPDATE_FAILED';
		$this->strings['process_success'] = 'OK';
	}
	
	public function run( $options ) {
		
		$defaults = array(
			'package' => '',
			'destination' => '',
			'clear_destination' => false,
			'abort_if_destination_exists' => true, // Abort if the Destination directory exists, Pass clear_destination as false please
			'clear_working' => true,
			'is_multi' => false,
			'hook_extra' => array() // Pass any extra $hook_extra args here, this will be passed to any hooked filters.
		);

		$options = wp_parse_args( $options, $defaults );
		
		$options = apply_filters( 'upgrader_package_options', $options );

		if ( ! $options['is_multi'] ) { // call $this->header separately if running multiple times
			$this->skin->header();
		}

		// Connect to the Filesystem first.
		$res = $this->fs_connect( array( WP_CONTENT_DIR, $options['destination'] ) );
		// Mainly for non-connected filesystem.
		if ( ! $res ) {
			if ( ! $options['is_multi'] ) {
				$this->skin->footer();
			}
			return false;
		}

		$this->skin->before();

		if ( is_wp_error($res) ) {
			$this->skin->error($res);
			$this->skin->after();
			if ( ! $options['is_multi'] ) {
				$this->skin->footer();
			}
			return $res;
		}

		/*
		 * Download the package (Note, This just returns the filename
		 * of the file if the package is a local file)
		 */
		$download = $this->download_package( $options['package'] );
		if ( is_wp_error($download) ) {
			$this->skin->error($download);
			$this->skin->after();
			if ( ! $options['is_multi'] ) {
				$this->skin->footer();
			}
			return $download;
		}

		$delete_package = ( $download != $options['package'] ); // Do not delete a "local" file

		// Unzips the file into a temporary directory.
		$working_dir = $this->unpack_package( $download, $delete_package );
		if ( is_wp_error($working_dir) ) {
			$this->skin->error($working_dir);
			$this->skin->after();
			if ( ! $options['is_multi'] ) {
				$this->skin->footer();
			}
			return $working_dir;
		}

		// With the given options, this installs it to the destination directory.
		$result = $this->install_package( array(
			'source' => $working_dir,
			'destination' => $options['destination'],
			'clear_destination' => $options['clear_destination'],
			'abort_if_destination_exists' => $options['abort_if_destination_exists'],
			'clear_working' => $options['clear_working'],
			'hook_extra' => $options['hook_extra']
		) );

		$this->skin->set_result($result);
		if ( is_wp_error($result) ) {
			$this->skin->error($result);
			$this->skin->feedback('process_failed');
		} else {
			// Installation succeeded.
			$this->skin->feedback('process_success');
		}
		
		return $result;
	}
	
	public function rollback( $plugin, $args = array() ) {
		
		$defaults    = array(
			'clear_update_cache' => true,
		);
		$parsed_args = wp_parse_args( $args, $defaults );
		
		$this->init();
		$this->upgrade_strings();
		
		// add_filter( 'upgrader_pre_install', array( $this, 'deactivate_plugin_before_upgrade' ), 10, 2 );
		add_filter( 'upgrader_clear_destination', array( $this, 'delete_old_plugin' ), 10, 4 );

		$result = $this->run( array(
			'package'           => 'https://downloads.wordpress.org/plugin/' . $this->skin->options['plugin_slug'] . '.' . $this->skin->options['prev_version'] . '.zip',
			'destination'       => WP_PLUGIN_DIR,
			'clear_destination' => true,
			'clear_working'     => true,
			'hook_extra'        => array(
				'plugin' => $plugin,
				'type'   => 'plugin',
				'action' => 'update',
			),
		));
		
		// remove_filter( 'upgrader_pre_install', array( $this, 'deactivate_plugin_before_upgrade' ) );
		remove_filter( 'upgrader_clear_destination', array( $this, 'delete_old_plugin' ) );
		
		if(!$this->result || is_wp_error($this->result)){
			return $this->result;
		}
		
		wp_clean_plugins_cache( $parsed_args['clear_update_cache'] ); // Refresh of plugin update information.

		return $result;
	}
}
