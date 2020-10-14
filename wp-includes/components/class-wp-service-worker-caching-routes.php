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
	 * Cache name for responses for navigation requests.
	 *
	 * @since 0.6
	 * @var string
	 */
	const NAVIGATIONS_CACHE_NAME = 'navigations';

	/**
	 * List of plugins in Workbox.
	 *
	 * @since 0.6
	 * @var string[]
	 */
	const WORKBOX_CORE_PLUGINS = [
		'backgroundSync',
		'broadcastUpdate',
		'cacheableResponse',
		'expiration',
		'rangeRequests',
	];

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
	 * @param array $args {
	 *     Additional route arguments.
	 *
	 *     @type string $strategy   Required. Strategy, can be WP_Service_Worker_Caching_Routes::STRATEGY_STALE_WHILE_REVALIDATE, WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_FIRST,
	 *                                  WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_FIRST, WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_ONLY,
	 *                                  WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_ONLY.
	 *     @type string $cache_name Name to use for the cache.
	 *     @type array  $plugins    Array of plugins with configuration. The key of each plugin in the array must match the plugin's name.
	 *                                  See <https://developers.google.com/web/tools/workbox/guides/using-plugins#workbox_plugins>.
	 *                                  @todo Eliminate plugins from being primary means of providing Workbox plugin configuration. Flatten the array to promote the plugin keys to the top level.
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
	 * @return array List of registered routes.
	 * @since 0.2
	 *
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

		$plugins = array();
		if ( isset( $strategy_args['plugins'] ) ) {
			if ( is_array( $strategy_args['plugins'] ) ) {
				$plugins = $strategy_args['plugins'];
			}
			unset( $strategy_args['plugins'] );
		}

		// Pluck out plugins defined at the top-level.
		foreach ( self::WORKBOX_CORE_PLUGINS as $plugin_name ) {
			$snake_case_plugin_name = self::convert_camel_case_to_snake_case( $plugin_name );
			if ( array_key_exists( $plugin_name, $strategy_args ) ) {
				$plugin_config = $strategy_args[ $plugin_name ];
				unset( $strategy_args[ $plugin_name ] );
				$plugins[ $snake_case_plugin_name ] = $plugin_config;
			} elseif ( array_key_exists( $snake_case_plugin_name, $strategy_args ) ) {
				$plugin_config = $strategy_args[ $snake_case_plugin_name ];
				unset( $strategy_args[ $snake_case_plugin_name ] );
				$plugins[ $snake_case_plugin_name ] = $plugin_config;
			}
		}

		// Extract plugins since not JSON-serializable as-is.
		$plugins_js = array();
		foreach ( $plugins as $plugin_name => $plugin_config ) {
			$plugin_name = self::convert_snake_case_to_camel_case( $plugin_name );

			// Skip plugin if it was explicitly disabled.
			if ( false === $plugin_config || null === $plugin_config ) {
				continue;
			} elseif ( ! is_array( $plugin_config ) ) {
				/* translators: %s is plugin name */
				_doing_it_wrong( 'WP_Service_Workers::register_cached_route', esc_html( sprintf( __( 'Non-array plugin configuration for %s', 'pwa' ), $plugin_name ) ), '0.6' );
				$plugin_config = array();
			}

			if ( ! in_array( $plugin_name, self::WORKBOX_CORE_PLUGINS, true ) ) {
				/* translators: %s is plugin name */
				_doing_it_wrong( 'WP_Service_Workers::register_cached_route', esc_html( sprintf( __( 'Unrecognized plugin: %s', 'pwa' ), $plugin_name ) ), '0.2' );
			} else {
				$plugins_js[] = sprintf(
					'new wp.serviceWorker[ %s ][ %s ]( %s )',
					wp_json_encode( $plugin_name ),
					wp_json_encode( ucfirst( $plugin_name ) . 'Plugin' ),
					wp_json_encode( self::camel_case_array_keys( $plugin_config ), JSON_FORCE_OBJECT )
				);
			}
		}

		$strategy_args = self::camel_case_array_keys( $strategy_args );

		$exported .= sprintf( 'const strategyArgs = %s;', wp_json_encode( $strategy_args, JSON_FORCE_OBJECT ) );

		// Prefix the cache to prevent collision with other subdirectory installs.
		$exported .= 'if ( strategyArgs.cacheName && wp.serviceWorker.core.cacheNames.prefix ) { strategyArgs.cacheName = `${wp.serviceWorker.core.cacheNames.prefix}-${strategyArgs.cacheName}`; }';

		if ( ! empty( $plugins_js ) ) {
			$exported .= sprintf( 'strategyArgs.plugins = [%s];', implode( ', ', $plugins_js ) );
		}

		$exported .= 'return strategyArgs;';
		$exported .= '} )()';

		return $exported;
	}

	/**
	 * Convert array keys from snake_case to camelCase.
	 *
	 * @since 0.6
	 * @see WP_Service_Worker_Caching_Routes_Component::get_script()
	 *
	 * @param array $original Original array.
	 * @return array Array with camelCased-array keys.
	 */
	protected static function camel_case_array_keys( $original ) {
		$camel_case = array();
		foreach ( $original as $key => $value ) {
			$camel_case[ self::convert_snake_case_to_camel_case( $key ) ] = $value;
		}
		return $camel_case;
	}

	/**
	 * Convert snake_case string to camelCase.
	 *
	 * @since 0.6
	 *
	 * @param string $string Possibly snake_case string.
	 * @return string CamelCase string.
	 */
	protected static function convert_snake_case_to_camel_case( $string ) {
		return preg_replace_callback(
			'/_[a-z]/',
			static function ( $matches ) {
				return strtoupper( ltrim( $matches[0], '_' ) );
			},
			$string
		);
	}

	/**
	 * Convert camelCase string to snake_case.
	 *
	 * @since 0.6
	 *
	 * @param string $string Possibly snake_case string.
	 * @return string CamelCase string.
	 */
	protected static function convert_camel_case_to_snake_case( $string ) {
		return preg_replace_callback(
			'/(?<=.)([A-Z])/',
			static function ( $matches ) {
				return '_' . strtolower( $matches[0] );
			},
			$string
		);
	}
}
