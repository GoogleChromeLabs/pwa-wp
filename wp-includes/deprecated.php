<?php
/**
 * Service Worker deprecated.
 *
 * @since 0.5
 *
 * @package PWA
 */

/**
 * Service worker styles.
 *
 * @deprecated No longer used.
 * @codeCoverageIgnore
 * @since 0.2
 * @since 0.5 Deprecated.
 */
function wp_service_worker_styles() {
	_deprecated_function( __FUNCTION__, '0.5' );
}

/**
 * Add Service Worker update notification to admin bar.
 *
 * @deprecated No longer used.
 * @codeCoverageIgnore
 * @since 0.2
 * @since 0.5 Deprecated.
 */
function wp_service_worker_update_node() {
	_deprecated_function( __FUNCTION__, '0.5' );
}

/**
 * Hide the admin bar if serving the offline template.
 *
 * @deprecated No longer used.
 * @codeCoverageIgnore
 * @since 0.2
 * @since 0.5 Deprecated.
 */
function wp_hide_admin_bar_offline() {
	_deprecated_function( __FUNCTION__, '0.5' );
}

/**
 * JSON-encodes with pretty printing.
 *
 * @since 0.2
 * @deprecated 0.6 Now wp_json_encode() is used directly.
 * @codeCoverageIgnore
 *
 * @param mixed $data Data.
 * @return string JSON.
 */
function wp_service_worker_json_encode( $data ) {
	_deprecated_function( __FUNCTION__, '0.6', 'wp_json_encode()' );
	return wp_json_encode( $data, 128 | 64 /* JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES */ );
}

/**
 * Disables concatenating scripts to leverage caching the assets via Service Worker instead.
 *
 * @deprecated 0.7 No longer used.
 * @codeCoverageIgnore
 */
function wp_disable_script_concatenation() {
	_deprecated_function( __FUNCTION__, '0.7' );

	global $concatenate_scripts;

	/*
	 * This cookie is set when the service worker registers successfully, avoiding unnecessary result
	 * for browsers that don't support service workers. Note that concatenation only applies in the admin,
	 * for authenticated users without full-page caching.
	 */
	if ( isset( $_COOKIE['wordpress_sw_installed'] ) ) {
		$concatenate_scripts = false; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	// phpcs:disable
	// @todo This is just here for debugging purposes.
	if ( isset( $_GET['wp_concatenate_scripts'] ) ) {
		$concatenate_scripts = rest_sanitize_boolean( $_GET['wp_concatenate_scripts'] );
	}
	// phpcs:enable
}

/**
 * Load service worker integrations.
 *
 * @since 0.2.0
 * @deprecated 0.7 Integrations will not be proposed for WordPress core merge.
 *
 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
 */
function pwa_load_service_worker_integrations( WP_Service_Worker_Scripts $scripts ) {
	if ( ! current_theme_supports( 'service_worker' ) ) {
		return;
	}

	/** WordPress Service Worker Integration Functions */
	require_once PWA_PLUGIN_DIR . '/integrations/functions.php';

	pwa_register_service_worker_integrations( $scripts );
}
add_action( 'wp_default_service_workers', 'pwa_load_service_worker_integrations', -1 );
