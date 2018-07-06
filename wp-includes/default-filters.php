<?php
/**
 * Sets up the default filters and actions for PWA hooks.
 *
 * @package PWA
 */
foreach ( array( 'wp_print_scripts', 'admin_print_scripts', 'customize_controls_print_scripts' ) as $filter ) {
	add_filter( $filter, 'wp_print_service_workers', 9 );
}
add_action( 'parse_request', 'wp_service_worker_loaded' );
add_filter( 'query_vars', 'wp_add_service_worker_query_var' );
