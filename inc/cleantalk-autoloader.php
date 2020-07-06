<?php

/**
 * Autoloader for \Cleantalk\* classes
 *
 * @param string $class
 *
 * @return void
 */
function apbct_autoloader( $class ){
	// Register class auto loader
	// Custom modules
	if( strpos( $class, 'cleantalk-spam-protect') !== false && ! class_exists( '\\' . $class )) {
		$class_file = CLEANTALK_PLUGIN_DIR . 'lib'  . DIRECTORY_SEPARATOR . $class . '.php';
		if( file_exists( $class_file ) ){
			require_once( $class_file );
		}
	}
}

spl_autoload_register( 'apbct_autoloader' );
