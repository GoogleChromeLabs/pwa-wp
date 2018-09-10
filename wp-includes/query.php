<?php
/**
 * WordPress Query API
 *
 * Functions here would be added to wp-includes/query.php in core.
 *
 * @since 0.2
 * @package PWA
 * @subpackage Query
 */

/**
 * Checks if is request for offline error template.
 *
 * @return bool
 */
function is_offline() {
	global $wp_query;
	if ( ! isset( $wp_query ) ) {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Conditional query tags do not work before the query is run. Before then, they always return false.', 'pwa' ), '3.1.0' );
		return false;
	}
	return isset( $wp_query->query_vars['wp_error_template'] ) && 'offline' === $wp_query->query_vars['wp_error_template'];
}

/**
 * Checks if is request for 500 error template.
 *
 * @return bool
 */
function is_500() {
	global $wp_query;
	if ( ! isset( $wp_query ) ) {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Conditional query tags do not work before the query is run. Before then, they always return false.', 'pwa' ), '3.1.0' );
		return false;
	}
	return isset( $wp_query->query_vars['wp_error_template'] ) && '500' === $wp_query->query_vars['wp_error_template'];
}
