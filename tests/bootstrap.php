<?php
/**
 * PHPUnit Bootstrap
 *
 * Copied from <https://github.com/xwp/wp-dev-lib/blob/1.6.5/sample-config/phpunit-plugin-bootstrap.php>.
 *
 * @package PWA
 */

// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_trigger_error

/**
 * Determine if we should update the content and plugin paths.
 */
if ( ! defined( 'WP_CONTENT_DIR' ) && getenv( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', getenv( 'WP_CONTENT_DIR' ) );
}
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	if ( file_exists( dirname( __DIR__ ) . '/wp-load.php' ) ) {
		define( 'WP_CONTENT_DIR', dirname( __DIR__ ) . '/wp-content' );
	} elseif ( file_exists( '../../../wp-content' ) ) {
		define( 'WP_CONTENT_DIR', dirname( dirname( dirname( getcwd() ) ) ) . '/wp-content' );
	}
}

if ( defined( 'WP_CONTENT_DIR' ) && ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', rtrim( WP_CONTENT_DIR, '/' ) . '/plugins' );
}

if ( file_exists( __DIR__ . '/../phpunit-plugin-bootstrap.project.php' ) ) {
	require_once __DIR__ . '/../phpunit-plugin-bootstrap.project.php';
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );

// Travis CI & Vagrant SSH tests directory.
if ( empty( $_tests_dir ) ) {
	$_tests_dir = '/tmp/wordpress-tests';
}

// Relative path to Core tests directory.
if ( ! is_dir( $_tests_dir . '/includes/' ) ) {
	$_tests_dir = '../../../../tests/phpunit';
}

if ( ! is_dir( $_tests_dir . '/includes/' ) ) {
	trigger_error( 'Unable to locate wordpress-tests-lib', E_USER_ERROR );
}
require_once $_tests_dir . '/includes/functions.php';

/**
 * Load plugin file.
 */
function pwa_unit_test_load_plugin_file() {
	require_once __DIR__ . '/../pwa.php';
}
tests_add_filter( 'muplugins_loaded', 'pwa_unit_test_load_plugin_file' );

require $_tests_dir . '/includes/bootstrap.php';
