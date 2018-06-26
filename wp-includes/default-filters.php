<?php
/**
 * Sets up the default filters and actions for PWA hooks.
 *
 * @package PWA
 */

foreach ( array( 'wp_print_footer_scripts', 'admin_print_footer_scripts', 'customize_controls_print_footer_scripts' ) as $filter ) {
	add_filter( $filter, 'wp_print_service_workers' );
}

add_action( 'parse_request', 'service_worker_loaded' );

add_action( 'init', 'wp_add_sw_rewrite_tags' );
