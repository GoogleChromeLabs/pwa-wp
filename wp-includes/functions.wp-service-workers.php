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

	// If the path is not relative. @todo should we try formatting it instead?
	if ( ! preg_match( '/^\//', $path ) ) {
		_doing_it_wrong( __FUNCTION__, __( 'Service worker should be registered with relative path.' ), '0.1' );
	}

	// Set default scope if missing.
	if ( ! $scope ) {
		$scope = site_url( '/', 'relative' );
	}

	// @todo Should we use the default add from WP_Dependencies?
	$registered = $wp_service_workers->add( $handle, $path, $deps, false, $scope );

	return $registered;
}

/**
 * Get service worker URL by scope.
 *
 * @param string $scope Scope, for example 'wp-admin'.
 * @return string Service Worker URL.
 */
function wp_get_service_worker_url( $scope ) {

	// @todo This is just a placeholder.
	return '?wp_service_workers=1&scope=' . $scope;
}

/**
 * Print service workers' scripts.
 */
function wp_print_service_workers() {

	// @todo Get actual scopes, this is a placeholder.
	foreach ( wp_service_workers()->scopes as $scope => $path ) {
	?>
	<script>
		if ( navigator.serviceWorker ) {
			navigator.serviceWorker.register(
				<?php echo wp_json_encode( wp_get_service_worker_url( $scope ) ); ?>,
				{ scope: <?php echo wp_json_encode( $scope ); ?> }
			);
		}
	</script>
	<?php
	}
}
