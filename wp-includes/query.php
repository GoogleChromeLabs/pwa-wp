<?php
/**
 * Functions for query.php file.
 *
 * @since 0.2
 * @package PWA
 */

/**
 * Checks if is offline page.
 *
 * @return bool
 */
function is_offline() {
	global $wp_query;
	if ( ! isset( $wp_query ) ) {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Conditional query tags do not work before the query is run. Before then, they always return false.', 'pwa' ), '3.1.0' );
		return false;
	}
	return ! empty( $wp_query->is_offline );
}
