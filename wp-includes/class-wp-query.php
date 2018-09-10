<?php
/**
 * Query API: WP_Query class
 *
 * Add hooks to amend behavior of WP_Query.
 *
 * @package PWA
 * @subpackage Query
 * @since 0.2
 */

/**
 * Parse query for an error template request.
 *
 * @param WP_Query $query Query.
 */
function pwa_parse_query_for_error_template( WP_Query $query ) {
	$error_template = $query->get( 'wp_error_template' );
	if ( ! in_array( $error_template, array( 'offline', '500' ), true ) ) {
		return;
	}

	// Do equivalent of WP_Query::init_query_flags().
	foreach ( array_keys( get_object_vars( $query ) ) as $key ) {
		if ( 0 === strpos( $key, 'is_' ) ) {
			$query->$key = false;
		}
	}

	switch ( $error_template ) {
		case 'offline':
			$query->is_offline = true; // Dynamically-declared.
			break;
		case '500':
			$query->is_500 = true; // Dynamically-declared.
			break;
	}
}
add_action( 'parse_query', 'pwa_parse_query_for_error_template', 1 );
