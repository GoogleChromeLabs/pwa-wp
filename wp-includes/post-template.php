<?php
/**
 * WordPress Post Template Functions.
 *
 * Functions that amend wp-includes/post-template.php
 *
 * @package PWA
 * @subpackage Template
 * @since 0.2
 */

/**
 * Add error classes (for offline and 500) to the body class.
 *
 * The logic in here would be added to `get_body_class()`.
 *
 * @since 0.2
 * @global WP_Query $wp_query
 * @see get_body_class()
 *
 * @param array $classes One or more classes.
 * @return array Array of classes.
 */
function pwa_filter_body_class( $classes ) {
	if ( is_500() ) {
		$classes[] = 'error';
		$classes[] = 'error500';
	} elseif ( is_offline() ) {
		$classes[] = 'error';
		$classes[] = 'offline';
	}
	return $classes;
}
add_filter( 'body_class', 'pwa_filter_body_class' );
