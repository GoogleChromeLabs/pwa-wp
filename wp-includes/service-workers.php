<?php
/**
 * Service Worker functions.
 *
 * @since ?
 *
 * @package PWA
 */

/**
 * Initialize $wp_service_workers if it has not been set.
 *
 * @global WP_Service_Workers $wp_service_workers
 * @return WP_Service_Workers WP_Service_Workers instance.
 */
function wp_service_workers() {
	global $wp_service_workers;
	if ( ! ( $wp_service_workers instanceof WP_Service_Workers ) ) {
		$wp_service_workers = new WP_Service_Workers();
	}
	return $wp_service_workers;
}

/**
 * Register a new service worker.
 *
 * @since ?
 *
 * @param string $handle Name of the service worker. Should be unique.
 * @param string $path   Relative path of the service worker.
 * @param array  $deps   Optional. An array of registered script handles this depends on. Default empty array.
 * @param string $scope  Optional Scope of the service worker.
 * @return bool Whether the script has been registered. True on success, false on failure.
 */
function wp_register_service_worker( $handle, $path, $deps = array(), $scope = null ) {
	$wp_service_workers = wp_service_workers();

	// If the path is not correct.
	if ( is_wp_error( $wp_service_workers->get_validated_file_path( $path ) ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			/* translators: %s is file URL */
			sprintf( esc_html__( 'Service worker path is incorrect: %s', 'pwa' ), esc_url( $path ) ),
			'0.1'
		);
	}

	$registered = $wp_service_workers->register( $handle, $path, $deps, $scope );

	return $registered;
}

/**
 * Get service worker URL by scope.
 *
 * @param string $scope Scope, for example 'wp-admin'.
 * @return string Service Worker URL.
 */
function wp_get_service_worker_url( $scope ) {

	if ( ! $scope ) {
		$scope = site_url( '/', 'relative' );
	}

	return add_query_arg( array(
		'wp_service_worker' => $scope,
	), site_url( '/', 'relative' ) );
}

/**
 * Print service workers' scripts.
 */
function wp_print_service_workers() {

	$scopes = wp_service_workers()->get_scopes();
	if ( empty( $scopes ) ) {
		return;
	}
	?>
	<script>
		<?php
		foreach ( $scopes as $scope ) {
			?>
			if ( navigator.serviceWorker ) {
				navigator.serviceWorker.register(
					<?php echo wp_json_encode( wp_get_service_worker_url( $scope ) ); ?>,
					{ scope: <?php echo wp_json_encode( compact( 'scope' ) ); ?> }
				);
			}
			<?php
		}
		?>
	</script>
	<?php
}

/**
 * Register rewrite tag for Service Workers.
 */
function wp_add_sw_rewrite_tags() {
	add_rewrite_tag( '%wp_service_worker%', '([^&]+)' );
}

/**
 * If it's a service worker script page, display that.
 */
function service_worker_loaded() {
	if ( ! empty( $GLOBALS['wp']->query_vars['wp_service_worker'] ) ) {
		wp_service_workers()->serve_request( $GLOBALS['wp']->query_vars['wp_service_worker'] );
		exit;
	}
}
