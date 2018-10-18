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
