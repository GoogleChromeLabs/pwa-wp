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
 * Register a service worker script.
 *
 * @since 0.2
 *
 * @param string $handle Handle of the script.
 * @param array  $args   {
 *     Additional script arguments.
 *
 *     @type string|callable $src  Required. URL to the source in the WordPress install, or a callback that
 *                                 returns the JS to include in the service worker.
 *     @type string[]        $deps An array of registered item handles this item depends on. Default empty array.
 * }
 */
function wp_register_service_worker_script( $handle, $args = array() ) {
	wp_service_workers()->get_registry()->register( $handle, $args );
}

/**
 * Registers a precaching route.
 *
 * @since 0.2
 *
 * @param string $url  URL to cache.
 * @param array  $args {
 *     Additional route arguments.
 *
 *     @type string $revision Revision.
 * }
 */
function wp_register_service_worker_precaching_route( $url, $args = array() ) {
	wp_service_workers()->get_registry()->precaching_routes()->register( $url, $args );
}

/**
 * Registers a caching route.
 *
 * @since 0.2
 * @since 0.7 Added $strategy parameter.
 *
 * @param string       $route    Route regular expression, without delimiters.
 * @param string|array $strategy Strategy, can be WP_Service_Worker_Caching_Routes::STRATEGY_STALE_WHILE_REVALIDATE,
 *                               WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_FIRST, WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_FIRST,
 *                               WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_ONLY, WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_ONLY.
 *                               Deprecated usage: supplying strategy args as an array.
 * @param array        $args {
 *     Additional caching strategy route arguments.
 *
 *     @type string $cache_name         Name to use for the cache.
 *     @type array  $expiration         Expiration plugin configuration. See <https://developers.google.com/web/tools/workbox/reference-docs/latest/module-workbox-expiration.ExpirationPlugin>.
 *     @type array  $broadcast_update   Broadcast update plugin configuration. See <https://developers.google.com/web/tools/workbox/reference-docs/latest/module-workbox-broadcast-update.BroadcastUpdatePlugin>.
 *     @type array  $cacheable_response Cacheable response plugin configuration. See <https://developers.google.com/web/tools/workbox/reference-docs/latest/module-workbox-cacheable-response.CacheableResponsePlugin>.
 *     @type array  $background_sync    Background sync plugin configuration. See <https://developers.google.com/web/tools/workbox/reference-docs/latest/module-workbox-background-sync.BackgroundSyncPlugin>.
 *     @type array  $plugins            Deprecated. Array of plugins with configuration. The key of each plugin in the array must match the plugin's name.
 *                                      This is deprecated in favor of defining the plugins in the top-level.
 *                                      See <https://developers.google.com/web/tools/workbox/guides/using-plugins#workbox_plugins>.
 * }
 * @return bool Whether the registration was successful.
 */
function wp_register_service_worker_caching_route( $route, $strategy, $args = array() ) {
	return wp_service_workers()->get_registry()->caching_routes()->register( $route, $strategy, $args );
}

/**
 * Get service worker URL by scope.
 *
 * @since 0.1
 *
 * @param int $scope Scope for which service worker to output. Can be WP_Service_Workers::SCOPE_FRONT (default) or WP_Service_Workers::SCOPE_ADMIN.
 * @return string Service Worker URL.
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 */
function wp_get_service_worker_url( $scope = WP_Service_Workers::SCOPE_FRONT ) {
	global $wp_rewrite;

	if ( WP_Service_Workers::SCOPE_FRONT !== $scope && WP_Service_Workers::SCOPE_ADMIN !== $scope ) {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Scope must be either WP_Service_Workers::SCOPE_FRONT or WP_Service_Workers::SCOPE_ADMIN.', 'pwa' ), '?' );
		$scope = WP_Service_Workers::SCOPE_FRONT;
	}

	if ( WP_Service_Workers::SCOPE_FRONT === $scope ) {
		if ( $wp_rewrite->using_permalinks() ) {
			return home_url( '/wp.serviceworker' );
		}

		return add_query_arg(
			array( WP_Service_Workers::QUERY_VAR => $scope ),
			home_url( '/', 'relative' )
		);
	}

	return add_query_arg(
		array( 'action' => WP_Service_Workers::QUERY_VAR ),
		admin_url( 'admin-ajax.php' )
	);
}

/**
 * Print service workers' scripts that can be installed.
 *
 * @since 0.1
 */
function wp_print_service_workers() {
	/*
	 * Skip installing service worker from context of post embed iframe, as the post embed iframe does not need the
	 * service worker. Also, installation via post embed iframe could be seen to be somewhat sneaky. Lastly, if the
	 * post embed is on the same site and contained iframe is sandbox without allow-same-origin, then the service
	 * worker will fail to install with an exception:
	 * > Uncaught DOMException: Failed to read the 'serviceWorker' property from 'Navigator': Service worker is
	 * > disabled because the context is sandboxed and lacks the 'allow-same-origin' flag.
	 */
	if ( is_embed() ) {
		return;
	}

	global $pagenow;

	$home_port  = wp_parse_url( home_url(), PHP_URL_PORT );
	$admin_port = wp_parse_url( admin_url(), PHP_URL_PORT );

	$home_host  = wp_parse_url( home_url(), PHP_URL_HOST );
	$admin_host = wp_parse_url( admin_url(), PHP_URL_HOST );

	$home_url  = ( $home_port ) ? "$home_host:$home_port" : $home_host;
	$admin_url = ( $admin_port ) ? "$admin_host:$admin_port" : $admin_host;

	$on_front_domain = isset( $_SERVER['HTTP_HOST'] ) && $home_url === $_SERVER['HTTP_HOST'];
	$on_admin_domain = isset( $_SERVER['HTTP_HOST'] ) && $admin_url === $_SERVER['HTTP_HOST'];

	$sw_src = null;
	$scope  = null;

	// Include admin service worker if it seems it will be used (and it can be installed).
	if ( $on_admin_domain && ( is_admin() || in_array( $pagenow, array( 'wp-login.php', 'wp-signup.php', 'wp-activate.php' ), true ) ) ) {
		$sw_src = wp_get_service_worker_url( WP_Service_Workers::SCOPE_ADMIN );
		$scope  = wp_parse_url( admin_url( '/' ), PHP_URL_PATH );
	} elseif ( $on_front_domain ) {
		$sw_src = wp_get_service_worker_url( WP_Service_Workers::SCOPE_FRONT );
		$scope  = home_url( '/', 'relative' ); // The home_url() here will account for subdirectory installs.
	}

	if ( ! $sw_src || ! $scope ) {
		return;
	}

	// Core merge: replace plugins_url() with includes_url().
	$workbox_window_src = plugins_url(
		sprintf(
			'wp-includes/js/workbox-v%s/workbox-window.%s.js',
			PWA_WORKBOX_VERSION,
			SCRIPT_DEBUG ? 'dev' : 'prod'
		),
		PWA_PLUGIN_FILE
	);
	$register_options   = array(
		'scope' => $scope,
	);

	?>
	<script type="module">
		import { Workbox } from <?php echo wp_json_encode( $workbox_window_src ); ?>;

		if ( 'serviceWorker' in navigator ) {
			window.wp = window.wp || {};
			window.wp.serviceWorkerWindow = new Workbox(
				<?php echo wp_json_encode( $sw_src ); ?>,
				<?php echo wp_json_encode( $register_options ); ?>
			);
			window.wp.serviceWorkerWindow.register();
		}
	</script>
	<?php
}

/**
 * Serve the service worker for the frontend if requested.
 *
 * @since 0.1
 * @see rest_api_loaded()
 * @see wp_ajax_wp_service_worker()
 *
 * @param WP_Query $query Query.
 * @global WP $wp
 */
function wp_service_worker_loaded( WP_Query $query ) {
	global $wp;
	if ( ! $query->is_main_query() ) {
		return;
	}

	// Handle case where rewrite rules have not yet been flushed.
	if ( 'wp.serviceworker' === $wp->request ) {
		$query->set( WP_Service_Workers::QUERY_VAR, 1 );
	}

	if ( $query->get( WP_Service_Workers::QUERY_VAR ) ) {
		wp_service_workers()->serve_request();
		die();
	}
}

/**
 * Serve admin service worker.
 *
 * This will be moved to wp-admin/includes/admin-actions.php
 *
 * @since 0.2
 * @see wp_service_worker_loaded()
 */
function wp_ajax_wp_service_worker() {
	wp_service_workers()->serve_request();
	die();
}

/**
 * Checks if Service Worker should skip waiting in case of update and update automatically.
 *
 * @since 0.2
 *
 * @return bool If to skip waiting.
 */
function wp_service_worker_skip_waiting() {

	/**
	 * Filters whether the service worker should update automatically when a new version is available.
	 *
	 * For optioning out from skipping waiting and displaying a notification to update instead, you could do:
	 *
	 *     add_filter( 'wp_service_worker_skip_waiting', '__return_false' );
	 *
	 * @since 0.2
	 *
	 * @param bool $skip_waiting Whether to skip waiting for the Service Worker and update when an update is available.
	 */
	return (bool) apply_filters( 'wp_service_worker_skip_waiting', true );
}
