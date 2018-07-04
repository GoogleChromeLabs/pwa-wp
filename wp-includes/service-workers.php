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
 * @param string          $handle Name of the service worker. Should be unique.
 * @param string|callable $src    Callback method or relative path of the service worker.
 * @param array           $deps   Optional. An array of registered script handles this depends on. Default empty array.
 * @param array           $scopes Optional Scopes of the service worker.
 * @return bool Whether the script has been registered. True on success, false on failure.
 */
function wp_register_service_worker( $handle, $src, $deps = array(), $scopes = array() ) {
	$service_workers = wp_service_workers();
	$registered      = $service_workers->register( $handle, $src, $deps, $scopes );

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
				window.addEventListener('load', function() {

					window.wp = window.wp || {};
					wp.serviceWorkerRegistrations = wp.serviceWorkerRegistrations || {};

					wp.serviceWorkerRegistrations[ <?php echo wp_json_encode( $scope ); ?> ] = navigator.serviceWorker.register(
						<?php echo wp_json_encode( wp_get_service_worker_url( $scope ) ); ?>,
						<?php echo wp_json_encode( compact( 'scope' ) ); ?>
					);
				} );
			}
			<?php
		}
		?>
	</script>
	<?php
}

/**
 * Register query var.
 *
 * @param array $query_vars Query vars.
 * @return array Query vars.
 */
function wp_add_sw_query_vars( $query_vars ) {
	$query_vars[] = 'wp_service_worker';
	return $query_vars;
}

/**
 * If it's a service worker script page, display that.
 */
function wp_service_worker_loaded() {
	if ( ! empty( $GLOBALS['wp']->query_vars['wp_service_worker'] ) ) {
		wp_service_workers()->serve_request( $GLOBALS['wp']->query_vars['wp_service_worker'] );
		exit;
	}
}
