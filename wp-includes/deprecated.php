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
