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
 *     @type array           $deps An array of registered item handles this item depends on. Default empty array.
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
 *
 * @param string $route Route regular expression, without delimiters.
 * @param array  $args  {
 *     Additional route arguments.
 *
 *     @type string $strategy   Required. Strategy, can be WP_Service_Worker_Caching_Routes::STRATEGY_STALE_WHILE_REVALIDATE, WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_FIRST,
 *                              WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_FIRST, WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_ONLY,
 *                              WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_ONLY.
 *     @type string $cache_name Name to use for the cache.
 *     @type array  $plugins    Array of plugins with configuration. The key of each plugin in the array must match the plugin's name.
 *                              See https://developers.google.com/web/tools/workbox/guides/using-plugins#workbox_plugins.
 * }
 */
function wp_register_service_worker_caching_route( $route, $args = array() ) {
	wp_service_workers()->get_registry()->caching_routes()->register( $route, $args );
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

	if ( WP_Service_Workers::SCOPE_FRONT === $scope ) {
		return add_query_arg(
			array( WP_Service_Workers::QUERY_VAR => $scope ),
			home_url( '/', 'https' )
		);
	} else {
		return add_query_arg(
			array( 'action' => WP_Service_Workers::QUERY_VAR ),
			admin_url( 'admin-ajax.php' )
		);
	}
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
			window.addEventListener( 'load', function() {
				<?php foreach ( $scopes as $name => $scope ) : ?>
					{
						let updatedSw;
						navigator.serviceWorker.register(
							<?php echo wp_json_encode( wp_get_service_worker_url( $name ) ); ?>,
							<?php echo wp_json_encode( compact( 'scope' ) ); ?>
						).then( reg => {
							document.cookie = 'wordpress_sw_installed=1; path=/; expires=Fri, 31 Dec 9999 23:59:59 GMT; secure; samesite=strict';
							<?php if ( ! wp_service_worker_skip_waiting() ) : ?>
								reg.addEventListener( 'updatefound', () => {
									if ( ! reg.installing ) {
										return;
									}
									updatedSw = reg.installing;

									/* If new service worker is available, show notification. */
									updatedSw.addEventListener( 'statechange', () => {
										if ( 'installed' === updatedSw.state && navigator.serviceWorker.controller ) {
											const notification = document.getElementById( 'wp-admin-bar-pwa-sw-update-notice' );
											if ( notification ) {
												notification.style.display = 'block';
											}
										}
									} );
								} );
							<?php endif; ?>
						} );

						<?php if ( is_admin_bar_showing() && ! wp_service_worker_skip_waiting() ) : ?>
							/* Post message to Service Worker for skipping the waiting phase. */
							const reloadBtn = document.getElementById( 'wp-admin-bar-pwa-sw-update-notice' );
							if ( reloadBtn ) {
								reloadBtn.addEventListener( 'click', ( event ) => {
									event.preventDefault();
									if ( updatedSw ) {
										updatedSw.postMessage( { action: 'skipWaiting' } );
									}
								} );
							}
						<?php endif; ?>
					}
				<?php endforeach; ?>

				let refreshedPage = false;
				navigator.serviceWorker.addEventListener( 'controllerchange', () => {
					if ( ! refreshedPage ) {
						refreshedPage = true;
						window.location.reload();
					}
				} );
			} );
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
 */
function wp_service_worker_loaded() {
	global $wp;
	if ( isset( $wp->query_vars[ WP_Service_Workers::QUERY_VAR ] ) ) {
		wp_service_workers()->serve_request();
		exit;
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
	exit;
}

/**
 * JSON-encodes with pretty printing.
 *
 * @since 0.2
 *
 * @param mixed $data Data.
 * @return string JSON.
 */
function wp_service_worker_json_encode( $data ) {
	return wp_json_encode( $data, 128 | 64 /* JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES */ );
}

/**
 * Registers all default service workers.
 *
 * @since 0.2
 *
 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
 */
function wp_default_service_workers( $scripts ) {
	$scripts->base_url        = site_url();
	$scripts->content_url     = defined( 'WP_CONTENT_URL' ) ? WP_CONTENT_URL : '';
	$scripts->default_version = get_bloginfo( 'version' );

	$integrations = array(
		'wp-site-icon'         => new WP_Service_Worker_Site_Icon_Integration(),
		'wp-custom-logo'       => new WP_Service_Worker_Custom_Logo_Integration(),
		'wp-custom-header'     => new WP_Service_Worker_Custom_Header_Integration(),
		'wp-custom-background' => new WP_Service_Worker_Custom_Background_Integration(),
		'wp-scripts'           => new WP_Service_Worker_Scripts_Integration(),
		'wp-styles'            => new WP_Service_Worker_Styles_Integration(),
		'wp-fonts'             => new WP_Service_Worker_Fonts_Integration(),
	);

	if ( ! SCRIPT_DEBUG ) {
		$integrations['wp-admin-assets'] = new WP_Service_Worker_Admin_Assets_Integration();
	}

	/**
	 * Filters the service worker integrations to initialize.
	 *
	 * @since 0.2
	 *
	 * @param array $integrations Array of $slug => $integration pairs, where $integration is an instance
	 *                            of a class that implements the WP_Service_Worker_Integration interface.
	 */
	$integrations = apply_filters( 'wp_service_worker_integrations', $integrations );

	foreach ( $integrations as $slug => $integration ) {
		if ( ! $integration instanceof WP_Service_Worker_Integration ) {
			_doing_it_wrong(
				__FUNCTION__,
				sprintf(
					/* translators: 1: integration slug, 2: interface name */
					esc_html__( 'The integration with slug %1$s does not implement the %2$s interface.', 'pwa' ),
					esc_html( $slug ),
					'WP_Service_Worker_Integration'
				),
				'0.2'
			);
			continue;
		}

		$scope    = $integration->get_scope();
		$priority = $integration->get_priority();
		switch ( $scope ) {
			case WP_Service_Workers::SCOPE_FRONT:
				add_action( 'wp_front_service_worker', array( $integration, 'register' ), $priority, 1 );
				break;
			case WP_Service_Workers::SCOPE_ADMIN:
				add_action( 'wp_admin_service_worker', array( $integration, 'register' ), $priority, 1 );
				break;
			case WP_Service_Workers::SCOPE_ALL:
				add_action( 'wp_front_service_worker', array( $integration, 'register' ), $priority, 1 );
				add_action( 'wp_admin_service_worker', array( $integration, 'register' ), $priority, 1 );
				break;
			default:
				$valid_scopes = array( WP_Service_Workers::SCOPE_FRONT, WP_Service_Workers::SCOPE_ADMIN, WP_Service_Workers::SCOPE_ALL );
				_doing_it_wrong(
					__FUNCTION__,
					sprintf(
						/* translators: 1: integration slug, 2: a comma-separated list of valid scopes */
						esc_html__( 'Scope for integration %1$s must be one out of %2$s.', 'pwa' ),
						esc_html( $slug ),
						esc_html( implode( ', ', $valid_scopes ) )
					),
					'0.1'
				);
		}
	}
}

/**
 * Disables concatenating scripts to leverage caching the assets via Service Worker instead.
 */
function wp_disable_script_concatenation() {
	global $concatenate_scripts;

	/*
	 * This cookie is set when the service worker registers successfully, avoiding unnecessary result
	 * for browsers that don't support service workers. Note that concatenation only applies in the admin,
	 * for authenticated users without full-page caching.
	*/
	if ( isset( $_COOKIE['wordpress_sw_installed'] ) ) {
		$concatenate_scripts = false; // WPCS: Override OK.
	}

	// @todo This is just here for debugging purposes.
	if ( isset( $_GET['wp_concatenate_scripts'] ) ) { // WPCS: csrf ok.
		$concatenate_scripts = rest_sanitize_boolean( $_GET['wp_concatenate_scripts'] ); // WPCS: csrf ok, override ok.
	}
}

/**
 * Preserve stream fragment query param on canonical redirects.
 *
 * @since 0.2
 *
 * @param string $link New URL of the post.
 * @return string URL to be redirected.
 */
function wp_service_worker_fragment_redirect_old_slug_to_new_url( $link ) {
	$fragment = WP_Service_Worker_Navigation_Routing_Component::get_stream_fragment_query_var();
	if ( $fragment ) {
		$link = add_query_arg( WP_Service_Worker_Navigation_Routing_Component::STREAM_FRAGMENT_QUERY_VAR, $fragment, $link );
	}
	return $link;
}

/**
 * Service worker styles.
 *
 * @since 0.2
 */
function wp_service_worker_styles() {
	wp_add_inline_style( 'admin-bar', '#wp-admin-bar-pwa-sw-update-notice { display:none }' );
}

/**
 * Add Service Worker update notification to admin bar.
 *
 * @since 0.2
 *
 * @param object $wp_admin_bar WP Admin Bar.
 */
function wp_service_worker_update_node( $wp_admin_bar ) {
	if ( wp_service_worker_skip_waiting() ) {
		return;
	}
	$wp_admin_bar->add_node(
		array(
			'id'    => 'pwa-sw-update-notice',
			'title' => __( 'Update to a new version of this site!', 'pwa' ),
			'href'  => '#',
		)
	);
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

/**
 * Get service worker error messages.
 *
 * @return array Array of error messages: default, comment.
 */
function wp_service_worker_get_error_messages() {
	return apply_filters(
		'wp_service_worker_error_messages',
		array(
			'default' => __( 'Please check your internet connection, and try again.', 'pwa' ),
			'error'   => __( 'Something prevented the page from being rendered. Please try again.', 'pwa' ),
			'comment' => __( 'Your comment will be submitted once you are back online!', 'pwa' ),
		)
	);
}

/**
 * Display service worker error details template.
 *
 * @param string $output Error details template output.
 */
function wp_service_worker_error_details_template( $output = '' ) {
	if ( empty( $output ) ) {
		$output = '<details id="error-details"><summary>' . esc_html__( 'More Details', 'pwa' ) . '</summary>{{{error_details_iframe}}}</details>';
	}
	echo '<!--WP_SERVICE_WORKER_ERROR_TEMPLATE_BEGIN-->'; // WPCS: XSS OK.
	echo wp_kses_post( $output );
	echo '<!--WP_SERVICE_WORKER_ERROR_TEMPLATE_END-->'; // WPCS: XSS OK.
}

/**
 * Display service worker error message template tag.
 */
function wp_service_worker_error_message_placeholder() {
	echo '<p><!--WP_SERVICE_WORKER_ERROR_MESSAGE--></p>'; // WPCS: XSS OK.
}
