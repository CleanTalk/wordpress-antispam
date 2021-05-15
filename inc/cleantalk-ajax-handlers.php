<?php

// If this file is called directly, abort.
if ( ! defined( 'DOING_AJAX' ) ) {
	http_response_code( 403 );
	die('Not allowed.');
}

require_once( __DIR__ . '/../lib/autoloader.php' );

$plugin_info = get_file_data( __DIR__ . '/../cleantalk.php', array('Version' => 'Version', 'Name' => 'Plugin Name') );
if( !defined( 'APBCT_VERSION' ) ) {
	define( 'APBCT_VERSION', $plugin_info['Version'] );
}

global $apbct;
$apbct = new \Cleantalk\ApbctWP\State('cleantalk', array('settings', 'data'));

/*******************************************************************/
/*******************  H A N D L E R S   H E R E  *******************/
/*******************************************************************/
function apbct_js_keys__get() {
	require_once( __DIR__ . '/cleantalk-common.php' );
	require_once( __DIR__ . '/cleantalk-pluggable.php' );
	apbct_js_keys__get__ajax();
}