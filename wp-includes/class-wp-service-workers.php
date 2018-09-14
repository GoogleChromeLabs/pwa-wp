<?php
/**
 * WP_Service_Workers class.
 *
 * @since 0.2
 * @package PWA
 */

/**
 * Class used to register service workers.
 *
 * @since 0.1
 *
 * @see WP_Dependencies
 */
class WP_Service_Workers {

	/**
	 * Param for service workers.
	 *
	 * @var string
	 */
	const QUERY_VAR = 'wp_service_worker';

	/**
	 * Scope for front.
	 *
	 * @var int
	 */
	const SCOPE_FRONT = 1;

	/**
	 * Scope for admin.
	 *
	 * @var int
	 */
	const SCOPE_ADMIN = 2;

	/**
	 * Scope for both front and admin.
	 *
	 * @var int
	 */
	const SCOPE_ALL = 3;

	/**
	 * Service worker scripts registry.
	 *
	 * @var WP_Service_Worker_Scripts
	 */
	protected $scripts;

	/**
	 * Output for service worker scope script.
	 *
	 * @var string
	 */
	protected $output = '';

	/**
	 * Constructor.
	 *
	 * Instantiates the service worker scripts registry.
	 */
	public function __construct() {
		$this->scripts = new WP_Service_Worker_Scripts();
	}

	/**
	 * Gets the service worker scripts registry.
	 *
	 * @return WP_Service_Worker_Scripts Scripts registry.
	 */
	public function get_registry() {
		return $this->scripts;
	}

	/**
	 * Get the current scope for the service worker request.
	 *
	 * @global WP $wp
	 *
	 * @return int Scope. Either SCOPE_FRONT, SCOPE_ADMIN, or if neither then 0.
	 */
	public function get_current_scope() {
		global $wp;
		if ( ! isset( $wp->query_vars[ self::QUERY_VAR ] ) || ! is_numeric( $wp->query_vars[ self::QUERY_VAR ] ) ) {
			return 0;
		}
		$scope = (int) $wp->query_vars[ self::QUERY_VAR ];
		if ( self::SCOPE_FRONT === $scope ) {
			return self::SCOPE_FRONT;
		} elseif ( self::SCOPE_ADMIN === $scope ) {
			return self::SCOPE_ADMIN;
		}
		return 0;
	}

	/**
	 * Get script for handling of error responses when the user is offline or when there is an internal server error.
	 *
	 * @return string Script.
	 */
	protected function get_error_response_handling_script() {
		$template   = get_template();
		$stylesheet = get_stylesheet();

		$revision = sprintf( '%s-v%s', $template, wp_get_theme( $template )->Version );
		if ( $template !== $stylesheet ) {
			$revision .= sprintf( ';%s-v%s', $stylesheet, wp_get_theme( $stylesheet )->Version );
		}

		// Ensure the user-specific offline/500 pages are precached, and thet they update when user logs out or switches to another user.
		$revision .= sprintf( ';user-%d', get_current_user_id() );

		$scope = $this->get_current_scope();
		if ( self::SCOPE_FRONT === $scope ) {
			$offline_error_template_file  = pwa_locate_template( array( 'offline.php', 'error.php' ) );
			$offline_error_precache_entry = array(
				'url'      => add_query_arg( 'wp_error_template', 'offline', home_url( '/' ) ),
				'revision' => $revision . ';' . md5( $offline_error_template_file . file_get_contents( $offline_error_template_file ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			);
			$server_error_template_file   = pwa_locate_template( array( '500.php', 'error.php' ) );
			$server_error_precache_entry  = array(
				'url'      => add_query_arg( 'wp_error_template', '500', home_url( '/' ) ),
				'revision' => $revision . ';' . md5( $server_error_template_file . file_get_contents( $server_error_template_file ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			);

			/**
			 * Filters what is precached to serve as the offline error response on the frontend.
			 *
			 * The URL returned in this array will be precached by the service worker and served as the response when
			 * the client is offline or their connection fails. To prevent this behavior, this value can be filtered
			 * to return false. When a theme or plugin makes a change to the response, the revision value in the array
			 * must be incremented to ensure the URL is re-fetched to store in the precache.
			 *
			 * @since 0.2
			 *
			 * @param array|false $entry {
			 *     Offline error precache entry.
			 *
			 *     @type string $url      URL to page that shows the offline error template.
			 *     @type string $revision Revision for the template. This defaults to the template and stylesheet names, with their respective theme versions.
			 * }
			 */
			$offline_error_precache_entry = apply_filters( 'wp_offline_error_precache_entry', $offline_error_precache_entry );

			/**
			 * Filters what is precached to serve as the internal server error response on the frontend.
			 *
			 * The URL returned in this array will be precached by the service worker and served as the response when
			 * the server returns a 500 internal server error . To prevent this behavior, this value can be filtered
			 * to return false. When a theme or plugin makes a change to the response, the revision value in the array
			 * must be incremented to ensure the URL is re-fetched to store in the precache.
			 *
			 * @since 0.2
			 *
			 * @param array $entry {
			 *     Server error precache entry.
			 *
			 *     @type string $url      URL to page that shows the server error template.
			 *     @type string $revision Revision for the template. This defaults to the template and stylesheet names, with their respective theme versions.
			 * }
			 */
			$server_error_precache_entry = apply_filters( 'wp_server_error_precache_entry', $server_error_precache_entry );

		} else {
			$offline_error_precache_entry = array(
				'url'      => add_query_arg( 'code', 'offline', admin_url( 'admin-ajax.php?action=wp_error_template' ) ), // Upon core merge, this would use admin_url( 'error.php' ).
				'revision' => PWA_VERSION, // Upon core merge, this should be the core version.
			);
			$server_error_precache_entry  = array(
				'url'      => add_query_arg( 'code', '500', admin_url( 'admin-ajax.php?action=wp_error_template' ) ), // Upon core merge, this would use admin_url( 'error.php' ).
				'revision' => PWA_VERSION, // Upon core merge, this should be the core version.
			);
		}

		if ( $offline_error_precache_entry ) {
			$this->scripts->cache_registry->register_precached_route( $offline_error_precache_entry['url'], isset( $offline_error_precache_entry['revision'] ) ? $offline_error_precache_entry['revision'] : null );
		}
		if ( $server_error_precache_entry ) {
			$this->scripts->cache_registry->register_precached_route( $server_error_precache_entry['url'], isset( $server_error_precache_entry['revision'] ) ? $server_error_precache_entry['revision'] : null );
		}

		$blacklist_patterns = array();
		if ( self::SCOPE_FRONT === $scope ) {
			$blacklist_patterns[] = '^' . preg_quote( untrailingslashit( wp_parse_url( admin_url(), PHP_URL_PATH ) ), '/' ) . '($|\?.*|/.*)';
		}

		$replacements = array(
			'ERROR_OFFLINE_URL'  => isset( $offline_error_precache_entry['url'] ) ? $this->scripts->json_encode( $offline_error_precache_entry['url'] ) : null,
			'ERROR_500_URL'      => isset( $server_error_precache_entry['url'] ) ? $this->scripts->json_encode( $server_error_precache_entry['url'] ) : null,
			'BLACKLIST_PATTERNS' => $this->scripts->json_encode( $blacklist_patterns ),
		);

		$script = file_get_contents( PWA_PLUGIN_DIR . '/wp-includes/js/service-worker-error-response-handling.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$script = preg_replace( '#/\*\s*global.+?\*/#', '', $script );

		return str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$script
		);
	}

	/**
	 * Get base script for service worker.
	 *
	 * This involves the loading and configuring Workbox. However, the `workbox` global should not be directly
	 * interacted with. Instead, developers should interface with `wp.serviceWorker` which is a wrapper around
	 * the Workbox library.
	 *
	 * @link https://github.com/GoogleChrome/workbox
	 *
	 * @return string Script.
	 */
	protected function get_base_script() {

		$current_scope = $this->get_current_scope();
		$workbox_dir   = 'wp-includes/js/workbox-v3.5.0/';

		$script = sprintf(
			"importScripts( %s );\n",
			$this->scripts->json_encode( PWA_PLUGIN_URL . $workbox_dir . 'workbox-sw.js' )
		);

		$options = array(
			'debug'            => WP_DEBUG,
			'modulePathPrefix' => PWA_PLUGIN_URL . $workbox_dir,
		);
		$script .= sprintf( "workbox.setConfig( %s );\n", $this->scripts->json_encode( $options ) );

		$cache_name_details = array(
			'prefix' => 'wordpress',
			'suffix' => 'v1',
		);

		$script .= sprintf( "workbox.core.setCacheNameDetails( %s );\n", $this->scripts->json_encode( $cache_name_details ) );

		// @todo Add filter controlling workbox.skipWaiting().
		// @todo Add filter controlling workbox.clientsClaim().
		/**
		 * Filters whether navigation preload is enabled.
		 *
		 * The filtered value will be sent as the Service-Worker-Navigation-Preload header value if a truthy string.
		 * This filter should be set to return false to disable navigation preload such as when a site is using
		 * the app shell model. Take care of the current scope when setting this, as it is unlikely that the admin
		 * should have navigation preload disabled until core has an admin single-page app. To disable navigation preload on
		 * the frontend only, you may do:
		 *
		 *     add_filter( 'wp_front_service_worker', function() {
		 *         add_filter( 'wp_service_worker_navigation_preload', '__return_false' );
		 *     } );
		 *
		 * Alternatively, you should check the `$current_scope` for example:
		 *
		 *     add_filter( 'wp_service_worker_navigation_preload', function( $preload, $current_scope ) {
		 *         if ( WP_Service_Workers::SCOPE_FRONT === $current_scope ) {
		 *             $preload = false;
		 *         }
		 *         return $preload;
		 *     }, 10, 2 );
		 *
		 * @param bool|string $navigation_preload Whether to use navigation preload. Returning a string will cause it it to populate the Service-Worker-Navigation-Preload header.
		 * @param int         $current_scope      The current scope. Either 1 (WP_Service_Workers::SCOPE_FRONT) or 2 (WP_Service_Workers::SCOPE_ADMIN).
		 */
		$navigation_preload = apply_filters( 'wp_service_worker_navigation_preload', true, $current_scope );
		if ( false !== $navigation_preload ) {
			if ( is_string( $navigation_preload ) ) {
				$script .= sprintf( "workbox.navigationPreload.enable( %s );\n", $this->scripts->json_encode( $navigation_preload ) );
			} else {
				$script .= "workbox.navigationPreload.enable();\n";
			}
		} else {
			$script .= "/* Navigation preload disabled. */\n";
		}

		// Note: This includes the aliasing of `workbox` to `wp.serviceWorker`.
		$script .= file_get_contents( PWA_PLUGIN_DIR . '/wp-includes/js/service-worker.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return $script;
	}

	/**
	 * Gets the script for precaching routes.
	 *
	 * @return string Precaching logic.
	 */
	protected function get_precaching_for_routes_script() {
		$precache_entries = $this->scripts->cache_registry->get_precached_routes();
		if ( empty( $precache_entries ) ) {
			return '';
		}

		$replacements = array(
			'PRECACHE_ENTRIES' => $this->scripts->json_encode( $precache_entries ),
		);

		$script = file_get_contents( PWA_PLUGIN_DIR . '/wp-includes/js/service-worker-precaching.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$script = preg_replace( '#/\*\s*global.+?\*/#', '', $script );

		return str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$script
		);
	}

	/**
	 * Get the caching strategy script for route.
	 *
	 * @param string $route Route.
	 * @param int    $strategy Caching strategy.
	 * @param array  $strategy_args {
	 *     An array of strategy arguments. If argument keys are supplied in snake_case, they'll be converted to camelCase for JS.
	 *
	 *     @type string $cache_name    Cache name to store and retrieve requests.
	 *     @type array  $plugins       Array of plugins with configuration. The key of each plugin must match the plugins name, with values being strategy options. Optional.
	 *                                 See https://developers.google.com/web/tools/workbox/guides/using-plugins#workbox_plugins.
	 *     @type array  $fetch_options Fetch options. Not supported by cacheOnly strategy. Optional.
	 *     @type array  $match_options Match options. Not supported by networkOnly strategy. Optional.
	 * }
	 * @return string Script.
	 */
	protected function get_caching_for_routes_script( $route, $strategy, $strategy_args ) {
		$script = '{'; // Begin lexical scope.

		// Extract plugins since not JSON-serializable as-is.
		$plugins = array();
		if ( isset( $strategy_args['plugins'] ) ) {
			$plugins = $strategy_args['plugins'];
			unset( $strategy_args['plugins'] );
		}

		$exported_strategy_args = array();
		foreach ( $strategy_args as $strategy_arg_name => $strategy_arg_value ) {
			if ( false !== strpos( $strategy_arg_name, '_' ) ) {
				$strategy_arg_name = preg_replace_callback( '/_[a-z]/', array( $this, 'convert_snake_case_to_camel_case_callback' ), $strategy_arg_name );
			}
			$exported_strategy_args[ $strategy_arg_name ] = $strategy_arg_value;
		}

		$script .= sprintf( 'const strategyArgs = %s;', empty( $exported_strategy_args ) ? '{}' : $this->scripts->json_encode( $exported_strategy_args ) );

		if ( is_array( $plugins ) ) {

			$recognized_plugins = array(
				'backgroundSync',
				'broadcastUpdate',
				'cacheableResponse',
				'expiration',
				'rangeRequests',
			);

			$plugins_js = array();
			foreach ( $plugins as $plugin_name => $plugin_args ) {
				if ( false !== strpos( $plugin_name, '_' ) ) {
					$plugin_name = preg_replace_callback( '/_[a-z]/', array( $this, 'convert_snake_case_to_camel_case_callback' ), $plugin_name );
				}

				if ( ! in_array( $plugin_name, $recognized_plugins, true ) ) {
					_doing_it_wrong( 'WP_Service_Workers::register_cached_route', esc_html__( 'Unrecognized plugin', 'pwa' ), '0.2' );
				} else {
					$plugins_js[] = sprintf(
						'new wp.serviceWorker[ %s ].Plugin( %s )',
						$this->scripts->json_encode( $plugin_name ),
						empty( $plugin_args ) ? '{}' : $this->scripts->json_encode( $plugin_args )
					);
				}
			}

			$script .= sprintf( 'strategyArgs.plugins = [%s];', implode( ', ', $plugins_js ) );
		}

		$script .= sprintf(
			'wp.serviceWorker.routing.registerRoute( new RegExp( %s ), wp.serviceWorker.strategies[ %s ]( strategyArgs ) );',
			$this->scripts->json_encode( $route ),
			$this->scripts->json_encode( $strategy )
		);

		$script .= '}'; // End lexical scope.

		return $script;
	}

	/**
	 * Convert snake_case to camelCase.
	 *
	 * This is is used by `preg_replace_callback()` for the pattern /_[a-z]/.
	 *
	 * @see WP_Service_Workers::get_caching_for_routes_script()
	 * @param array $matches Matches.
	 * @return string Replaced string.
	 */
	protected function convert_snake_case_to_camel_case_callback( $matches ) {
		return strtoupper( ltrim( $matches[0], '_' ) );
	}

	/**
	 * Get service worker logic for scope.
	 *
	 * @see wp_service_worker_loaded()
	 * @param int $scope Scope of the Service Worker.
	 */
	public function serve_request( $scope ) {
		/*
		 * Per Workbox <https://developers.google.com/web/tools/workbox/guides/service-worker-checklist#cache-control_of_your_service_worker_file>:
		 * "Generally, most developers will want to set the Cache-Control header to no-cache,
		 * forcing browsers to always check the server for a new service worker file."
		 * Nevertheless, an ETag header is also sent with support for Conditional Requests
		 * to save on needlessly re-downloading the same service worker with each page load.
		 */
		@header( 'Cache-Control: no-cache' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged

		@header( 'Content-Type: text/javascript; charset=utf-8' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged

		if ( self::SCOPE_FRONT === $scope ) {
			wp_enqueue_scripts();

			/**
			 * Fires before serving the frontend service worker, when its scripts should be registered, caching routes established, and assets precached.
			 *
			 * The following integrations are hooked into this action by default: 'wp-site-icon', 'wp-custom-logo', 'wp-custom-header', 'wp-custom-background',
			 * 'wp-scripts', 'wp-styles', and 'wp-fonts'. This default behavior can be disabled with code such as the following, for disabling the
			 * 'wp-custom-header' integration:
			 *
			 *     add_filter( 'wp_service_worker_integrations', function( $integrations ) {
			 *         unset( $integrations['wp-custom-header'] );
			 *         return $integrations;
			 *     } );
			 *
			 * @since 0.2
			 *
			 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
			 */
			do_action( 'wp_front_service_worker', $this->scripts );
		} elseif ( self::SCOPE_ADMIN === $scope ) {
			/**
			 * Fires before serving the wp-admin service worker, when its scripts should be registered, caching routes established, and assets precached.
			 *
			 * @since 0.2
			 *
			 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
			 */
			do_action( 'wp_admin_service_worker', $this->scripts );
		}

		/**
		 * Fires before serving the service worker (both front and admin), when its scripts should be registered, caching routes established, and assets precached.
		 *
		 * @since 0.2
		 *
		 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
		 */
		do_action( 'wp_service_worker', $this->scripts );

		if ( self::SCOPE_FRONT !== $scope && self::SCOPE_ADMIN !== $scope ) {
			status_header( 400 );
			echo '/* invalid_scope_requested */';
			return;
		}

		printf( "/* PWA v%s */\n\n", esc_html( PWA_VERSION ) );

		$this->output  = '';
		$this->output .= $this->get_base_script();
		$this->output .= $this->get_error_response_handling_script();

		ob_start();
		$this->scripts->do_items( array_keys( $this->scripts->registered ) );
		$this->output .= ob_get_clean();

		$this->output .= $this->get_precaching_for_routes_script();

		$caching_routes = $this->scripts->cache_registry->get_cached_routes();
		foreach ( $caching_routes as $caching_route ) {
			$this->output .= $this->get_caching_for_routes_script( $caching_route['route'], $caching_route['strategy'], $caching_route['strategy_args'] );
		}

		$file_hash = md5( $this->output );
		@header( "ETag: $file_hash" ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged

		$etag_header = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( $_SERVER['HTTP_IF_NONE_MATCH'] ) : false;
		if ( $file_hash === $etag_header ) {
			status_header( 304 );
			return;
		}

		echo $this->output; // phpcs:ignore WordPress.XSS.EscapeOutput, WordPress.Security.EscapeOutput
	}
}
