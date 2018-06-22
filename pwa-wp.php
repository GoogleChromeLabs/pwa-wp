<?php
/**
 * PWA-WP
 *
 * @package      PWAWP
 * @author       XWP
 * @copyright    2018 XWP
 * @license      GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name:       PWA-WP
 * Plugin URI:        https://github.com/xwp/pwa-wp
 * Description:       Feature plugin to bring Progressive Web Apps (PWA) to Core
 * Version:           0.1.0-alpha
 * Author:            XWP and contributors
 * Author URI:        https://github.com/xwp/pwa-wp/graphs/contributors
 * Text Domain:       pwa-wp
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/xwp/pwa-wp
 * Requires PHP:      5.2
 * Requires WP:       4.9
 */

define( 'PWAWP_VERSION', '0.1.0-alpha' );
define( 'PWAWP_PLUGIN_FILE', __FILE__ );
define( 'PWAWP_PLUGIN_DIR', dirname( __FILE__ ) );

pwawp_init();

/**
 * Loads and instantiates the classes.
 */
function pwawp_init() {
	$classes = array(
		'wp-web-app-manifest',
	);
	foreach ( $classes as $class ) {
		require PWAWP_PLUGIN_DIR . "/php/class-{$class}.php";
	}

	$wp_web_app_manifest = new WP_Web_App_Manifest();
	$wp_web_app_manifest->init();

	// These could be in ABSPATH . WPINC . '/script-loader.php' file.
	/** WordPress Service Workers Class */
	require PWAWP_PLUGIN_DIR . '/wp-includes/class.wp-service-workers.php';

	/** WordPress Scripts Functions */
	require PWAWP_PLUGIN_DIR . '/wp-includes/functions.wp-service-workers.php';

	add_action( 'wp_print_footer_scripts', 'wp_print_service_workers' );

	// Alternative for this could be in wp-includes/functions.php.
	add_action( 'template_redirect', 'pwawp_maybe_display_sw_script' );

	add_action( 'init', 'pwawp_add_sw_rewrite_rules' );
}

/**
 * Register rewrite rules for Service Workers.
 */
function pwawp_add_sw_rewrite_rules() {
	add_rewrite_tag( '%wp_service_worker%', '(0|1)' );
	add_rewrite_tag( '%scope%', '([^&]+)' );
	add_rewrite_rule( '^wp-service-worker.js?', 'index.php?wp_service_worker=1', 'top' );
}

/**
 * If it's a service worker script page, display that.
 */
function pwawp_maybe_display_sw_script() {

	if (
		true === filter_var( get_query_var( 'wp_service_worker' ), FILTER_VALIDATE_BOOLEAN ) &&
		strlen( get_query_var( 'scope' ) )
	) {
		wp_service_workers()->do_service_worker( get_query_var( 'scope' ) );
	}
}
