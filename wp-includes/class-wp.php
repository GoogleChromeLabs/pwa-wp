<?php
/**
 * Add hooks to amend behavior of the WP class.
 *
 * @package PWA
 * @since 0.2
 */

/**
 * Add recognition of wp_error_template query var.
 *
 * Upon core merge the query vars here could be added straight to `WP::$public_query_vars`.
 *
 * @global WP $wp
 */
function pwa_add_error_template_query_var() {
	global $wp;
	$wp->add_query_var( 'wp_error_template' );
	$wp->add_query_var( WP_Service_Workers::QUERY_VAR );
}
add_action( 'init', 'pwa_add_error_template_query_var' );

/**
 * Prevent handling an offline template request as a 404 when there are no posts published.
 *
 * For a core merge, this logic should be incorporated into `WP::handle_404()`.
 *
 * @see \WP::handle_404()
 *
 * @param bool     $preempt  Whether to short-circuit default header status handling. Default false.
 * @param WP_Query $wp_query WordPress Query object.
 * @return bool
 */
function pwa_filter_pre_handle_404_for_error_template_requests( $preempt, WP_Query $wp_query ) {
	if ( $wp_query->get( 'wp_error_template' ) ) {
		$preempt = true;
	}
	return $preempt;
}
add_filter( 'pre_handle_404', 'pwa_filter_pre_handle_404_for_error_template_requests', 10, 2 );
