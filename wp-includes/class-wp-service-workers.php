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
		$components = array(
			'configuration'  => new WP_Service_Worker_Configuration_Component(),
			'error_response' => new WP_Service_Worker_Error_Response_Component(),
		);

		$this->scripts = new WP_Service_Worker_Scripts( $components );
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
			'PRECACHE_ENTRIES' => wp_service_worker_json_encode( $precache_entries ),
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

		$script .= sprintf( 'const strategyArgs = %s;', empty( $exported_strategy_args ) ? '{}' : wp_service_worker_json_encode( $exported_strategy_args ) );

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
						wp_service_worker_json_encode( $plugin_name ),
						empty( $plugin_args ) ? '{}' : wp_service_worker_json_encode( $plugin_args )
					);
				}
			}

			$script .= sprintf( 'strategyArgs.plugins = [%s];', implode( ', ', $plugins_js ) );
		}

		$script .= sprintf(
			'wp.serviceWorker.routing.registerRoute( new RegExp( %s ), wp.serviceWorker.strategies[ %s ]( strategyArgs ) );',
			wp_service_worker_json_encode( $route ),
			wp_service_worker_json_encode( $strategy )
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

		if ( self::SCOPE_FRONT !== $scope && self::SCOPE_ADMIN !== $scope ) {
			status_header( 400 );
			echo '/* invalid_scope_requested */';
			return;
		}

		printf( "/* PWA v%s */\n\n", esc_html( PWA_VERSION ) );

		ob_start();
		$this->scripts->do_items( array_keys( $this->scripts->registered ) );
		$this->output = ob_get_clean();

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
