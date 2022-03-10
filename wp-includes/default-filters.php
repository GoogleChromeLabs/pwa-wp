<?php
/**
 * Sets up the default filters and actions for PWA hooks.
 *
 * Hooks in here would be added to wp-includes/default-filters.php in core.
 *
 * @package PWA
 */

$service_worker_actions = wp_get_actions_with_registered_service_worker_scripts();

if ( get_option( 'offline_browsing' ) || ! empty( $service_worker_actions ) ) {

	// Ensure service workers are printed on frontend, admin, Customizer, login, sign-up, and activate pages.
	$hooks = array(
		'front' => array(
			'wp_print_footer_scripts',
		),
		'admin' => array(
			'admin_print_scripts',
			'customize_controls_print_scripts',
			'login_footer',
			'after_signup_form',
			'activate_wp_head',
		),
	);

	if ( get_option( 'offline_browsing' ) ) {
		// Ensure service workers are printed on frontend, admin, Customizer, login, sign-up, and activate pages.
		foreach ( array_merge( $hooks['front'], $hooks['admin'] ) as $filter ) {
			add_filter( $filter, 'wp_print_service_workers', 9 );
		}

		add_action( 'parse_query', 'wp_service_worker_loaded' );
		add_action( 'wp_ajax_wp_service_worker', 'wp_ajax_wp_service_worker' );
		add_action( 'wp_ajax_nopriv_wp_service_worker', 'wp_ajax_wp_service_worker' );
		add_action( 'parse_query', 'wp_unauthenticate_error_template_requests' );
	}

	if ( in_array( 'wp_front_service_worker', $service_worker_actions ) ) {
		// Ensure service workers are printed on frontend.
		add_filter( 'wp_print_footer_scripts', 'wp_print_service_workers', 9 );

		add_action( 'parse_query', 'wp_service_worker_loaded' );
		add_action( 'parse_query', 'wp_unauthenticate_error_template_requests' );
	}

	if ( in_array( 'wp_admin_service_worker', $service_worker_actions ) ) {
		// Ensure service workers are printed on admin, Customizer, login, sign-up, and activate pages.
		foreach ( array( 'admin_print_scripts', 'customize_controls_print_scripts', 'login_footer', 'after_signup_form', 'activate_wp_head' ) as $filter ) {
			add_filter( $filter, 'wp_print_service_workers', 9 );
		}

		add_action( 'wp_ajax_wp_service_worker', 'wp_ajax_wp_service_worker' );
		add_action( 'wp_ajax_nopriv_wp_service_worker', 'wp_ajax_wp_service_worker' );
		add_action( 'parse_query', 'wp_unauthenticate_error_template_requests' );
	}
}

if ( version_compare( strtok( get_bloginfo( 'version' ), '-' ), '5.7', '>=' ) ) {
	add_action( 'error_head', 'wp_robots', 1 ); // To match wp_robots running at wp_head.
	add_filter( 'wp_robots', 'wp_filter_robots_for_error_template' );
} else {
	add_action( 'wp_head', 'wp_add_error_template_no_robots' );
	add_action( 'error_head', 'wp_add_error_template_no_robots' );
}
