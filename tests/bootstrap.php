<?php

/**
 * PHPUnit bootstrap file
 *
 * @package Security_Malware_Firewall
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin()
{
	require dirname( dirname( __FILE__ ) ) . '/cleantalk.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

require_once dirname( dirname( __FILE__ ) ) . '/lib/autoloader.php';
require_once dirname( dirname( __FILE__ ) ) . '/lib/cleantalk-php-patch.php';
require_once dirname( dirname( __FILE__ ) ) . '/vendor/wp-cli/wp-cli/php/class-wp-cli.php';
require_once dirname( dirname( __FILE__ ) ) . '/vendor/wp-cli/wp-cli/php/utils.php';

//Specific includes for TravisCI
if( getenv( 'TRAVISCI' ) === 'psalm' ) {
	$wp_admin_dir = dirname( __FILE__, 5 );
	require_once $wp_admin_dir . '/wp-admin/includes/class-wp-upgrader.php';
	require_once $wp_admin_dir . '/wp-admin/includes/class-plugin-upgrader.php';
}

