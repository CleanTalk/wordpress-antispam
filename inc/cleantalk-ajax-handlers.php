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
\Cleantalk\ApbctWP\State::setDefinitions();

/*******************************************************************/
/*******************  H A N D L E R S   H E R E  *******************/
/*******************************************************************/
function apbct_js_keys__get() {
	require_once( __DIR__ . '/cleantalk-common.php' );
	require_once( __DIR__ . '/cleantalk-pluggable.php' );
	apbct_js_keys__get__ajax();
}

function apbct_email_check_before_post() {
	if (count($_POST) && isset($_POST['data']['email']) && !empty($_POST['data']['email'])) {
		$email = trim($_POST['data']['email']);
		$result = \Cleantalk\ApbctWP\API::method__email_check($email);
		if (isset($result['data'])) {
			die(json_encode(array('result' => $result['data'])));
		}
		die(json_encode(array('error' => 'ERROR_CHECKING_EMAIL')));
	}
	die(json_encode(array('error' => 'EMPTY_DATA')));
}

function apbct_alt_session__save__AJAX() {
	Cleantalk\ApbctWP\Variables\AltSessions::set_fromRemote();
}

function apbct_alt_session__get__AJAX() {
	Cleantalk\ApbctWP\Variables\AltSessions::get_fromRemote();
}