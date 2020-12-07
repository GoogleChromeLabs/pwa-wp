<?php
/**
 * Add hooks to amend behavior of the WP class.
 *
 * @package PWA
 * @since 0.2
 */

/**
 * Adds rewrite rules to enable pretty permalinks for the service worker script.
 *
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 */
function pwa_add_rewrite_rules() {
	global $wp_rewrite;
	$rewrite_rule_regex = '^wp\.serviceworker$';

	if ( ! isset( $wp_rewrite->extra_rules_top[ $rewrite_rule_regex ] ) ) {
		// Note: This logic will not be required as part of core merge since rewrite rules are flushed upon DB upgrade (as long as the DB version is bumped).
		add_action(
			'admin_init',
			function () {
				flush_rewrite_rules( false );
			}
		);
	}
	add_rewrite_rule( $rewrite_rule_regex, 'index.php?' . WP_Service_Workers::QUERY_VAR . '=' . WP_Service_Workers::SCOPE_FRONT, 'top' );

	add_rewrite_tag( '%' . WP_Service_Workers::QUERY_VAR . '%', '([^?]+)' );
}

add_action( 'init', 'pwa_add_rewrite_rules' );

/**
 * Add recognition of wp_error_template query var.
 *
 * Upon core merge the query vars here could be added straight to `WP::$public_query_vars`.
 *
 * @param string[] $query_vars Query vars.
 * @return string[] Query vars.
 */
function pwa_add_public_query_vars( $query_vars ) {
	$query_vars[] = 'wp_error_template';
	return $query_vars;
}

add_filter( 'query_vars', 'pwa_add_public_query_vars' );

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
