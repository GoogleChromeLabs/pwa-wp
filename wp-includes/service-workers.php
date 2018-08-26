<?php
/**
 * Service Worker functions.
 *
 * @since 0.1
 *
 * @package PWA
 */

/**
 * Initialize $wp_service_workers if it has not been set.
 *
 * @since 0.1
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
 * @since 0.1
 *
 * @param string          $handle Name of the service worker. Should be unique.
 * @param string|callable $src    Callback method or relative path of the service worker.
 * @param array           $deps   Optional. An array of registered script handles this depends on. Default empty array.
 * @param int             $scope  Scope for which service worker the script will be part of. Can be WP_Service_Workers::SCOPE_FRONT, WP_Service_Workers::SCOPE_ADMIN, or WP_Service_Workers::SCOPE_ALL. Default to WP_Service_Workers::SCOPE_ALL.
 * @return bool Whether the script has been registered. True on success, false on failure.
 */
function wp_register_service_worker( $handle, $src, $deps = array(), $scope = WP_Service_Workers::SCOPE_ALL ) {
	return wp_service_workers()->register_script( $handle, $src, $deps, $scope );
}

/**
 * Register route and caching strategy using regex pattern for route.
 *
 * @since 0.2
 *
 * @param string $route Route, has to be valid regex.
 * @param string $strategy Strategy, can be WP_Service_Workers::STRATEGY_STALE_WHILE_REVALIDATE, WP_Service_Workers::STRATEGY_CACHE_FIRST,
 *                         WP_Service_Workers::STRATEGY_NETWORK_FIRST, WP_Service_Workers::STRATEGY_CACHE_ONLY,
 *                         WP_Service_Workers::STRATEGY_NETWORK_ONLY.
 * @param array  $strategy_args {
 *     An array of strategy arguments.
 *
 *     @type string $cache_name Cache name.
 *     @type array  $plugins    Array of plugins with configuration. The key of each plugin in the array must match the plugin's name.
 *                              See https://developers.google.com/web/tools/workbox/guides/using-plugins#workbox_plugins.
 * }
 */
function wp_register_route_caching_strategy( $route, $strategy = WP_Service_Workers::STRATEGY_STALE_WHILE_REVALIDATE, $strategy_args = array() ) {
	wp_service_workers()->register_cached_route( $route, $strategy, $strategy_args );
}

/**
 * Register routes / files for precaching.
 *
 * @since 0.2
 *
 * @param array $routes {
 *      Array of routes.
 *
 *      @type string $url      URL of the route.
 *      @type string $revision Revision (optional).
 * }
 */
function wp_register_routes_precaching( $routes ) {
	return wp_service_workers()->register_precached_routes( $routes );
}

/**
 * Get service worker URL by scope.
 *
 * @since 0.1
 *
 * @param int $scope Scope for which service worker to output. Can be WP_Service_Workers::SCOPE_FRONT (default) or WP_Service_Workers::SCOPE_ADMIN.
 * @return string Service Worker URL.
 */
function wp_get_service_worker_url( $scope = WP_Service_Workers::SCOPE_FRONT ) {
	if ( WP_Service_Workers::SCOPE_FRONT !== $scope && WP_Service_Workers::SCOPE_ADMIN !== $scope ) {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Scope must be either WP_Service_Workers::SCOPE_FRONT or WP_Service_Workers::SCOPE_ADMIN.', 'pwa' ), '?' );
		$scope = WP_Service_Workers::SCOPE_FRONT;
	}

	return add_query_arg(
		array( WP_Service_Workers::QUERY_VAR => $scope ),
		home_url( '/', 'https' )
	);
}

/**
 * Print service workers' scripts that can be installed.
 *
 * @since 0.1
 */
function wp_print_service_workers() {
	global $pagenow;
	$scopes = array();

	$on_front_domain = isset( $_SERVER['HTTP_HOST'] ) && wp_parse_url( home_url(), PHP_URL_HOST ) === $_SERVER['HTTP_HOST'];
	$on_admin_domain = isset( $_SERVER['HTTP_HOST'] ) && wp_parse_url( admin_url(), PHP_URL_HOST ) === $_SERVER['HTTP_HOST'];

	// Install the front service worker if currently on the home domain.
	if ( $on_front_domain ) {
		$scopes[ WP_Service_Workers::SCOPE_FRONT ] = home_url( '/', 'relative' ); // The home_url() here will account for subdirectory installs.
	}

	// Include admin service worker if it seems it will be used (and it can be installed).
	if ( $on_admin_domain && ( is_user_logged_in() || is_admin() || in_array( $pagenow, array( 'wp-login.php', 'wp-signup.php', 'wp-activate.php' ), true ) ) ) {
		$scopes[ WP_Service_Workers::SCOPE_ADMIN ] = wp_parse_url( admin_url( '/' ), PHP_URL_PATH );
	}

	if ( empty( $scopes ) ) {
		return;
	}

	?>
	<script>
		if ( navigator.serviceWorker ) {
			window.addEventListener('load', function() {
				<?php foreach ( $scopes as $name => $scope ) { ?>
					navigator.serviceWorker.register(
						<?php echo wp_json_encode( wp_get_service_worker_url( $name ) ); ?>,
						<?php echo wp_json_encode( compact( 'scope' ) ); ?>
					);
				<?php } ?>
			} );
		}
	</script>
	<?php
}

/**
 * Print the script that is responsible for populating the details iframe with the error info from the service worker.
 *
 * Broadcast a request to obtain the original response text from the internal server error response and display it inside
 * a details iframe if the 500 response included any body (such as an error message). This is used in a the 500.php template.
 *
 * @since 0.2
 *
 * @param string $callback Function in JS to invoke with the data. This may be either a global function name or method of another object, e.g. "mySite.handleServerError".
 */
function wp_print_service_worker_error_details_script( $callback ) {
	?>
	<script>
		{
			const clientUrl = location.href;
			const channel = new BroadcastChannel( 'wordpress-server-errors' );
			channel.onmessage = ( event ) => {
				if ( event.data && event.data.requestUrl && clientUrl === event.data.requestUrl ) {
					channel.onmessage = null;
					channel.close();

					<?php echo 'window[' . implode( '][', array_map( 'json_encode', explode( '.', $callback ) ) ) . ']( event.data );'; ?>
				}
			};
			channel.postMessage( { clientUrl } )
		}
	</script>
	<?php
}

/**
 * If it's a service worker script page, display that.
 *
 * @since 0.1
 * @see rest_api_loaded()
 */
function wp_service_worker_loaded() {
	$scope = wp_service_workers()->get_current_scope();
	if ( 0 !== $scope ) {
		wp_service_workers()->serve_request( $scope );
		exit;
	}
}
