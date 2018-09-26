<?php
/**
 * Sets up the default filters and actions for PWA hooks.
 *
 * Hooks in here would be added to wp-includes/default-filters.php in core.
 *
 * @package PWA
 */

// Ensure service workers are printed on frontend, admin, Customizer, login, sign-up, and activate pages.
foreach ( array( 'wp_print_scripts', 'admin_print_scripts', 'customize_controls_print_scripts', 'login_footer', 'after_signup_form', 'activate_wp_head' ) as $filter ) {
	add_filter( $filter, 'wp_print_service_workers', 9 );
}

add_action( 'parse_query', 'wp_service_worker_loaded' );
add_action( 'parse_query', 'wp_hide_admin_bar_offline' );

add_action( 'wp_head', 'wp_add_error_template_no_robots' );
add_action( 'error_head', 'wp_add_error_template_no_robots' );
add_action( 'wp_default_service_workers', 'wp_default_service_workers' );

// Service Worker Updating.
add_action( 'admin_print_footer_scripts', 'wp_print_admin_service_worker_update_script' );
add_action( 'wp_print_footer_scripts', 'wp_print_admin_service_worker_update_script', 11 );
add_action( 'admin_bar_menu', 'wp_service_worker_update_node', 999 );

// This could go to script-loader.php instead.
add_action( 'wp_enqueue_scripts', 'wp_service_worker_default_assets' );
add_action( 'admin_enqueue_scripts', 'wp_service_worker_default_assets' );
