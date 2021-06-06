<?php
require_once dirname( dirname( __FILE__ ) ) . '/lib/autoloader.php';
require_once dirname( dirname( __FILE__ ) ) . '/lib/cleantalk-php-patch.php';

global $apbct;
$apbct = new \Cleantalk\ApbctWP\State('cleantalk', array('settings', 'data'));
\Cleantalk\ApbctWP\State::setDefinitions();

if( ! function_exists( 'add_action' ) ) {
	function add_action( $arg1, $arg2, $arg3=0, $arg4=0 ) {
		return null;
	}
}

if( ! function_exists( 'add_filter' ) ) {
	function add_filter( $arg1, $arg2, $arg3=0, $arg4=0 ) {
		return null;
	}
}
