<?php
/**
 * WP_Service_Worker_Caching_Routes class.
 *
 * @package PWA
 */

/**
 * Class representing a registry for caching routes.
 *
 * @since 0.2
 */
class WP_Service_Worker_Caching_Routes implements WP_Service_Worker_Registry {

	/**
	 * Stale while revalidate caching strategy.
	 *
	 * @since 0.2
	 * @var string
	 */
	const STRATEGY_STALE_WHILE_REVALIDATE = 'StaleWhileRevalidate';

	/**
	 * Cache first caching strategy.
	 *
	 * @since 0.2
	 * @var string
	 */
	const STRATEGY_CACHE_FIRST = 'CacheFirst';

	/**
	 * Network first caching strategy.
	 *
	 * @since 0.2
	 * @var string
	 */
	const STRATEGY_NETWORK_FIRST = 'NetworkFirst';

	/**
	 * Cache only caching strategy.
	 *
	 * @since 0.2
	 * @var string
	 */
	const STRATEGY_CACHE_ONLY = 'CacheOnly';

	/**
	 * Network only caching strategy.
	 *
	 * @since 0.2
	 * @var string
	 */
	const STRATEGY_NETWORK_ONLY = 'NetworkOnly';

	/**
	 * Registered caching routes.
	 *
	 * @since 0.2
	 * @var array
	 */
	protected $routes = array();

	/**
	 * Registers a route.
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
	public function register( $route, $args = array() ) {
		if ( ! is_array( $args ) ) {
			$args = array( 'strategy' => $args );
		}

		$valid_strategies = array(
			self::STRATEGY_STALE_WHILE_REVALIDATE,
			self::STRATEGY_CACHE_FIRST,
			self::STRATEGY_CACHE_ONLY,
			self::STRATEGY_NETWORK_FIRST,
			self::STRATEGY_NETWORK_ONLY,
		);

		if ( empty( $args['strategy'] ) || ! in_array( $args['strategy'], $valid_strategies, true ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s is a comma-separated list of valid strategies */
					esc_html__( 'Strategy must be one out of %s.', 'pwa' ),
					esc_html( implode( ', ', $valid_strategies ) )
				),
				'0.2'
			);
			return;
		}

		if ( ! is_string( $route ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: caching strategy */
					esc_html__( 'Route for the caching strategy %s must be a string.', 'pwa' ),
					esc_html( $args['strategy'] )
				),
				'0.2'
			);
			return;
		}

		$strategy = $args['strategy'];
		unset( $args['strategy'] );

		$this->routes[] = array(
			'route'         => $route,
			'strategy'      => $strategy,
			'strategy_args' => $args,
		);
	}

	/**
	 * Gets all registered routes.
	 *
	 * @since 0.2
	 *
	 * @return array List of registered routes.
	 */
	public function get_all() {
		return $this->routes;
	}


	/**
	 * Prepare caching strategy args for export to JS.
	 *
	 * @since 0.2
	 *
	 * @param array $strategy_args Strategy args.
	 * @return string JS IIFE which returns object for passing to registerRoute.
	 */
	public static function prepare_strategy_args_for_js_export( $strategy_args ) {
		$exported = '( function() {';

		// Extract plugins since not JSON-serializable as-is.
		$plugins = array();
		if ( isset( $strategy_args['plugins'] ) ) {
			$plugins = $strategy_args['plugins'];
			unset( $strategy_args['plugins'] );
		}

		foreach ( $strategy_args as $strategy_arg_name => $strategy_arg_value ) {
			if ( false !== strpos( $strategy_arg_name, '_' ) ) {
				$strategy_arg_name = preg_replace_callback( '/_[a-z]/', array( __CLASS__, 'convert_snake_case_to_camel_case_callback' ), $strategy_arg_name );
			}
			$exported_strategy_args[ $strategy_arg_name ] = $strategy_arg_value;
		}

		$exported .= sprintf( 'const strategyArgs = %s;', empty( $exported_strategy_args ) ? '{}' : wp_service_worker_json_encode( $exported_strategy_args ) );

		// Prefix the cache to prevent collision with other subdirectory installs.
		$exported .= 'if ( strategyArgs.cacheName && wp.serviceWorker.core.cacheNames.prefix ) { strategyArgs.cacheName = `${wp.serviceWorker.core.cacheNames.prefix}-${strategyArgs.cacheName}`; }';

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
					$plugin_name = preg_replace_callback( '/_[a-z]/', array( __CLASS__, 'convert_snake_case_to_camel_case_callback' ), $plugin_name );
				}

				if ( ! in_array( $plugin_name, $recognized_plugins, true ) ) {
					/* translators: %s is plugin name */
					_doing_it_wrong( 'WP_Service_Workers::register_cached_route', esc_html( sprintf( __( 'Unrecognized plugin: %s', 'pwa' ), $plugin_name ) ), '0.2' );
				} else {
					$plugins_js[] = sprintf(
						'new wp.serviceWorker[ %s ].Plugin( %s )',
						wp_service_worker_json_encode( $plugin_name ),
						empty( $plugin_args ) ? '{}' : wp_service_worker_json_encode( $plugin_args )
					);
				}
			}

			$exported .= sprintf( 'strategyArgs.plugins = [%s];', implode( ', ', $plugins_js ) );
		}

		$exported .= 'return strategyArgs;';
		$exported .= '} )()';

		return $exported;
	}

	/**
	 * Convert snake_case to camelCase.
	 *
	 * This is is used by `preg_replace_callback()` for the pattern /_[a-z]/.
	 *
	 * @since 0.2
	 * @see WP_Service_Worker_Caching_Routes_Component::get_script()
	 *
	 * @param array $matches Matches.
	 * @return string Replaced string.
	 */
	protected static function convert_snake_case_to_camel_case_callback( $matches ) {
		return strtoupper( ltrim( $matches[0], '_' ) );
	}
}
