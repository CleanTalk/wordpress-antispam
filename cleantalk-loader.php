<?php

define( 'CLEANTALK_PLUGIN_DIR', dirname( __FILE__ ) . '/' );

apbct_load( substr(CLEANTALK_PLUGIN_DIR, 0, -1 ) );

function apbct_load( $main_path ) {
	
	$paths = glob( $main_path . '/*' );
	
	foreach ( $paths as $path ) {
		
		// Include subdirectories
		if ( is_dir( $path ) ) {
			apbct_load( $path );
			
		// Include only Cleantalk*.php
		} elseif (
			// preg_match( '/.*cleantalk-spam-protect[\\/][\S]*[\\/]cleantalk-[\S]+\.php$/', $path ) || // Includes
			preg_match( '@.*cleantalk-spam-protect[/][\S]*[/]Cleantalk[\S]+\.php$@', $path ) ||         // Libs
			preg_match( '@.*cleantalk-spam-protect[/][\S]*inc.common[/]cleantalk-[\S]+\.php$@', $path ) // Common
		) {
			require_once( $path );
		}
	}
}