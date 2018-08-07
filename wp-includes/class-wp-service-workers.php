<?php
/**
 * Dependencies API: WP_Service_Workers class
 *
 * @since 0.1
 *
 * @package PWA
 */

/**
 * Class used to register service workers.
 *
 * @since 0.1
 *
 * @see WP_Dependencies
 */
class WP_Service_Workers extends WP_Scripts {

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
	 * Stale while revalidate caching strategy.
	 *
	 * @var string
	 */
	const STRATEGY_STALE_WHILE_REVALIDATE = 'staleWhileRevalidate';

	/**
	 * Cache first caching strategy.
	 *
	 * @var string
	 */
	const STRATEGY_CACHE_FIRST = 'cacheFirst';

	/**
	 * Network first caching strategy.
	 *
	 * @var string
	 */
	const STRATEGY_NETWORK_FIRST = 'networkFirst';

	/**
	 * Cache only caching strategy.
	 *
	 * @var string
	 */
	const STRATEGY_CACHE_ONLY = 'cacheOnly';

	/**
	 * Network only caching strategy.
	 *
	 * @var string
	 */
	const STRATEGY_NETWORK_ONLY = 'networkOnly';

	/**
	 * Param for service workers.
	 *
	 * @var string
	 */
	public $query_var = 'wp_service_worker';

	/**
	 * Output for service worker scope script.
	 *
	 * @var string
	 */
	public $output = '';

	/**
	 * Registered caching routes and scripts.
	 *
	 * @var array
	 */
	public $registered_caching_routes = array();

	/**
	 * Registered routes and files for precaching.
	 *
	 * @var array
	 */
	public $registered_precaching_routes = array();

	/**
	 * Initialize the class.
	 */
	public function init() {

		$this->register(
			'workbox-sw',
			array( $this, 'get_workbox_script' ),
			array()
		);

		$this->register(
			'caching-utils-sw',
			PWA_PLUGIN_URL . '/wp-includes/js/service-worker.js',
			array( 'workbox-sw' )
		);

		/**
		 * Fires when the WP_Service_Workers instance is initialized.
		 *
		 * @param WP_Service_Workers $this WP_Service_Workers instance (passed by reference).
		 */
		do_action_ref_array( 'wp_default_service_workers', array( &$this ) );
	}

	/**
	 * Get workbox script.
	 *
	 * @return string Script.
	 */
	public function get_workbox_script() {

		$workbox_dir = 'wp-includes/js/workbox-v3.4.1/';

		$script = sprintf(
			"importScripts( %s );\n",
			wp_json_encode( PWA_PLUGIN_URL . $workbox_dir . 'workbox-sw.js', 64 /* JSON_UNESCAPED_SLASHES */ )
		);

		$options = array(
			'debug'            => WP_DEBUG,
			'modulePathPrefix' => PWA_PLUGIN_URL . $workbox_dir,
		);
		$script .= sprintf( "workbox.setConfig( %s );\n", wp_json_encode( $options, 64 /* JSON_UNESCAPED_SLASHES */ ) );

		/**
		 * Filters whether navigation preload is enabled.
		 *
		 * The filtered value will be sent as the Service-Worker-Navigation-Preload header value if a truthy string.
		 * This filter should be set to return false to disable navigation preload such as when a site is using
		 * the app shell model.
		 *
		 * @param bool|string $navigation_preload Whether to use navigation preload.
		 */
		$navigation_preload = apply_filters( 'service_worker_navigation_preload', true ); // @todo This needs to vary between admin and backend.
		if ( false !== $navigation_preload ) {
			if ( is_string( $navigation_preload ) ) {
				$script .= sprintf( "workbox.navigationPreload.enable( %s );\n", wp_json_encode( $navigation_preload ) );
			} else {
				$script .= "workbox.navigationPreload.enable();\n";
			}
		} else {
			$script .= "/* Navigation preload disabled. */\n";
		}
		return $script;
	}

	/**
	 * Register service worker.
	 *
	 * Registers service worker if no item of that name already exists.
	 *
	 * @param string          $handle Name of the item. Should be unique.
	 * @param string|callable $src    URL to the source in the WordPress install, or a callback that returns the JS to include in the service worker.
	 * @param array           $deps   Optional. An array of registered item handles this item depends on. Default empty array.
	 * @param int             $scope  Scope for which service worker the script will be part of. Can be WP_Service_Workers::SCOPE_FRONT, WP_Service_Workers::SCOPE_ADMIN, or WP_Service_Workers::SCOPE_ALL. Default to WP_Service_Workers::SCOPE_ALL.
	 * @return bool Whether the item has been registered. True on success, false on failure.
	 */
	public function register( $handle, $src, $deps = array(), $scope = self::SCOPE_ALL ) {
		if ( ! in_array( $scope, array( self::SCOPE_FRONT, self::SCOPE_ADMIN, self::SCOPE_ALL ), true ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Scope must be either WP_Service_Workers::SCOPE_ALL, WP_Service_Workers::SCOPE_FRONT, or WP_Service_Workers::SCOPE_ADMIN.', 'pwa' ), '0.1' );
			$scope = self::SCOPE_ALL;
		}

		return parent::add( $handle, $src, $deps, false, compact( 'scope' ) );
	}

	/**
	 * Register route and caching strategy.
	 *
	 * @param string $route Route.
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
	 * @param bool   $is_regex If the route is regex or not. Defaults to false.
	 * @return string Script.
	 */
	public function register_cached_route( $route, $strategy, $strategy_args = array(), $is_regex = false ) {

		if ( ! in_array( $strategy, array(
			self::STRATEGY_STALE_WHILE_REVALIDATE,
			self::STRATEGY_CACHE_FIRST,
			self::STRATEGY_CACHE_ONLY,
			self::STRATEGY_NETWORK_FIRST,
			self::STRATEGY_NETWORK_ONLY,
		), true ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Strategy must be either WP_Service_Workers::STRATEGY_STALE_WHILE_REVALIDATE, WP_Service_Workers::STRATEGY_CACHE_FIRST,
	            WP_Service_Workers::STRATEGY_NETWORK_FIRST, WP_Service_Workers::STRATEGY_CACHE_ONLY, or WP_Service_Workers::STRATEGY_NETWORK_ONLY.', 'pwa' ), '0.2' );
			return;
		}

		if ( ! is_string( $route ) ) {
			/* translators: %s is caching strategy */
			$error = sprintf( __( 'Route for the caching strategy %s must be a string.', 'pwa' ), $strategy );
			_doing_it_wrong( __METHOD__, esc_html( $error, 'pwa' ), '0.2' );
		} else {

			$this->registered_caching_routes[] = array(
				'route'         => $route,
				'strategy'      => $strategy,
				'strategy_args' => $strategy_args,
				'is_regex'      => $is_regex,
			);
		}
	}

	/**
	 * Register routes / files for precaching.
	 *
	 * @param array $routes Array of routes, each route must be a string literal.
	 */
	public function register_precached_routes( $routes ) {
		if ( ! is_array( $routes ) || empty( $routes ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Routes must be an array consisting of string literals.', 'pwa' ), '0.2' );
			return;
		}
		$this->registered_precaching_routes = array_merge(
			$routes,
			$this->registered_precaching_routes
		);
	}

	/**
	 * Register precaching for routes. Gets the hashes of files' contents and adds as revision for each route.
	 *
	 * @param array $routes Array of routes.
	 * @return string Precaching logic.
	 */
	protected function register_precaching_for_routes( $routes ) {

		$routes_list = array();

		foreach ( $routes as $route ) {
			$validated_path = $this->get_validated_file_path( $route, false );
			if ( ! is_wp_error( $validated_path ) ) {
				$file_content = @file_get_contents( $validated_path ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
				if ( ! $file_content ) {
					continue;
				}

				$hash          = md5( $file_content );
				$routes_list[] = array(
					'url'      => $route,
					'revision' => $hash,
				);
			}
		}

		if ( empty( $routes_list ) ) {
			return '';
		}

		return sprintf( "wp.serviceWorker.precaching.precacheAndRoute( %s );\n", wp_json_encode( $routes_list ) );
	}

	/**
	 * Register caching strategy for route.
	 *
	 * @param string $route Route.
	 * @param int    $strategy Caching strategy.
	 * @param array  $strategy_args {
	 *     An array of strategy arguments.
	 *
	 *     @type string $cache_name Cache name.
	 *     @type array  $plugins    Array of plugins with configuration. The key of each plugin must match the plugins name.
	 *                              See https://developers.google.com/web/tools/workbox/guides/using-plugins#workbox_plugins.
	 * }
	 * @param bool   $is_regex If the route is regex.
	 * @return string Script.
	 */
	protected function register_caching_strategy_for_route( $route, $strategy, $strategy_args, $is_regex ) {
		$script = '';

		if ( isset( $strategy_args['cache_name'] ) ) {
			$script .= 'const args = {
	cacheName: ' . wp_json_encode( $strategy_args['cache_name'] ) . '
};';
		}

		if ( isset( $strategy_args['plugins'] ) && is_array( $strategy_args['plugins'] ) ) {

			$allowed_plugins = array(
				'backgroundSync',
				'broadcastUpdate',
				'cacheableResponse',
				'expiration',
				'rangeRequests',
			);

			if ( empty( $script ) ) {
				$script .= '
var args = {};';
			}
			$script .= '
args.plugins = [';
			$plugins = '';
			foreach ( $strategy_args['plugins'] as $name => $args ) {

				// Only allow existing plugins.
				if ( ! in_array( $name, $allowed_plugins, true ) ) {
					continue;
				}
				if ( ! empty( $plugins ) ) {
					$plugins .= ',';
				}
				$plugins .= '
		new wp.serviceWorker.' . $name . '.Plugin(' .
					wp_json_encode( $args ) . '
		)';
			}
			if ( ! empty( $plugins ) ) {
				$script .= $plugins . '
];';
			}
		}

		$args_script = $script;

		$script .= '
wp.serviceWorker.WPRouter.registerRoute(';

		if ( false === $is_regex ) {
			$script .= '
	' . wp_json_encode( $route );
		} else {
			$script .= '
	new RegExp( ' . wp_json_encode( $route ) . ' )';
		}
		$script .= ',
	wp.serviceWorker.strategies.' . $strategy . '(';

		$script .= ! empty( $args_script ) ? ' args ' : '';
		$script .= ")
);\n";
		return $script;
	}

	/**
	 * Get service worker logic for scope.
	 *
	 * @see wp_service_worker_loaded()
	 * @param int $scope Scope of the Service Worker.
	 */
	public function serve_request( $scope ) {
		@header( 'Content-Type: text/javascript; charset=utf-8' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged

		if ( self::SCOPE_FRONT !== $scope && self::SCOPE_ADMIN !== $scope ) {
			status_header( 400 );
			echo '/* invalid_scope_requested */';
			return;
		}

		// @todo If $scope is admin should this admin_enqueue_scripts, and if front should it wp_enqueue_scripts?
		$scope_items = array();

		// Get handles from the relevant scope only.
		foreach ( $this->registered as $handle => $item ) {
			if ( $item->args['scope'] & $scope ) { // Yes, Bitwise AND intended. SCOPE_ALL & SCOPE_FRONT == true. SCOPE_ADMIN & SCOPE_FRONT == false.
				$scope_items[] = $handle;
			}
		}

		$this->output = '';
		$this->do_items( $scope_items );
		$this->do_precaching_routes();
		$this->do_caching_routes();

		$file_hash = md5( $this->output );
		@header( "Etag: $file_hash" ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged

		$etag_header = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( $_SERVER['HTTP_IF_NONE_MATCH'] ) : false;
		if ( $file_hash === $etag_header ) {
			status_header( 304 );
			return;
		}

		echo $this->output; // phpcs:ignore WordPress.XSS.EscapeOutput, WordPress.Security.EscapeOutput
	}

	/**
	 * Add logic for precaching to the request output.
	 */
	protected function do_precaching_routes() {
		$routes_to_precache = array_unique( $this->registered_precaching_routes );
		$this->output      .= $this->register_precaching_for_routes( array_unique( $routes_to_precache ) );
	}

	/**
	 * Add logic for routes caching to the request output.
	 */
	protected function do_caching_routes() {
		foreach ( $this->registered_caching_routes as $caching_route ) {
			$this->output .= $this->register_caching_strategy_for_route( $caching_route['route'], $caching_route['strategy'], $caching_route['strategy_args'], $caching_route['is_regex'] );
		}
	}

	/**
	 * Process one registered script.
	 *
	 * @param string $handle Handle.
	 * @param bool   $group Group. Unused.
	 * @return void
	 */
	public function do_item( $handle, $group = false ) {
		$registered = $this->registered[ $handle ];
		$invalid    = false;

		if ( is_callable( $registered->src ) ) {
			$this->output .= sprintf( "\n/* Source %s: */\n", $handle );
			$this->output .= call_user_func( $registered->src ) . "\n";
		} elseif ( is_string( $registered->src ) ) {
			$validated_path = $this->get_validated_file_path( $registered->src );
			if ( is_wp_error( $validated_path ) ) {
				$invalid = true;
			} else {
				/* translators: %s is file URL */
				$this->output .= sprintf( "\n/* Source %s <%s>: */\n", $handle, $registered->src );
				$this->output .= @file_get_contents( $validated_path ) . "\n"; // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
			}
		} else {
			$invalid = true;
		}

		if ( $invalid ) {
			/* translators: %s is script handle */
			$error = sprintf( __( 'Service worker src is invalid for handle "%s".', 'pwa' ), $handle );
			_doing_it_wrong( 'WP_Service_Workers::register', esc_html( $error ), '0.1' );
			$this->output .= sprintf( "console.warn( %s );\n", wp_json_encode( $error ) );
		}
	}

	/**
	 * Remove URL scheme.
	 *
	 * @param string $schemed_url URL.
	 * @return string URL.
	 */
	protected function remove_url_scheme( $schemed_url ) {
		return preg_replace( '#^\w+:(?=//)#', '', $schemed_url );
	}

	/**
	 * Get validated path to file.
	 *
	 * @param string $url Relative path.
	 * @param bool   $allow_content_only If to allow path from content directory only. Defaults to true.
	 * @return null|string|WP_Error
	 */
	protected function get_validated_file_path( $url, $allow_content_only = true ) {
		if ( ! is_string( $url ) ) {
			return new WP_Error( 'incorrect_path_format', esc_html__( 'URL has to be a string', 'pwa' ) );
		}

		$needs_base_url = ! preg_match( '|^(https?:)?//|', $url );
		$base_url       = site_url();

		if ( $needs_base_url ) {
			$url = $base_url . $url;
		}

		// Strip URL scheme, query, and fragment.
		$url = $this->remove_url_scheme( preg_replace( ':[\?#].*$:', '', $url ) );

		$content_url  = $this->remove_url_scheme( content_url( '/' ) );
		$includes_url = $this->remove_url_scheme( includes_url( '/' ) );
		$admin_url    = $this->remove_url_scheme( get_admin_url( null, '/' ) );

		$allowed_hosts = array(
			wp_parse_url( $content_url, PHP_URL_HOST ),
		);

		if ( false === $allow_content_only ) {
			$allowed_hosts[] = wp_parse_url( $includes_url, PHP_URL_HOST );
			$allowed_hosts[] = wp_parse_url( $admin_url, PHP_URL_HOST );
		}
		$url_host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! in_array( $url_host, $allowed_hosts, true ) ) {
			/* translators: %s is file URL */
			return new WP_Error( 'external_file_url', sprintf( __( 'URL is located on an external domain: %s.', 'pwa' ), $url_host ) );
		}

		$file_path = null;
		if ( 0 === strpos( $url, $content_url ) ) {
			$file_path = WP_CONTENT_DIR . substr( $url, strlen( $content_url ) - 1 );
		} elseif ( false === $allow_content_only ) {
			if ( 0 === strpos( $url, $includes_url ) ) {
				$file_path = ABSPATH . WPINC . substr( $url, strlen( $includes_url ) - 1 );
			} elseif ( 0 === strpos( $url, $admin_url ) ) {
				$file_path = ABSPATH . 'wp-admin' . substr( $url, strlen( $admin_url ) - 1 );
			}
		}

		if ( ! $file_path || false !== strpos( '../', $file_path ) || 0 !== validate_file( $file_path ) || ! file_exists( $file_path ) ) {
			/* translators: %s is file URL */
			return new WP_Error( 'file_path_not_found', sprintf( __( 'Unable to locate filesystem path for %s.', 'pwa' ), $url ) );
		}

		return $file_path;
	}
}
