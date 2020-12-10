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
final class WP_Service_Worker_Caching_Routes {

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
	 * List of plugins in Workbox (in snake case).
	 *
	 * @since 0.6
	 * @var string[]
	 */
	const WORKBOX_CORE_PLUGINS = array(
		'background_sync',
		'broadcast_update',
		'cacheable_response',
		'expiration',
		'range_requests',
	);

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
	 * @return bool Whether the registration was successful.
	 * }
	 */
	public function register( $route, $strategy, $args = array() ) {

		// In versions prior to 0.6, this argument could either be a strategy string or an array of strategy args containing the strategy name.
		if ( is_array( $strategy ) ) {
			$args     = $strategy;
			$strategy = isset( $args['strategy'] ) ? $args['strategy'] : null;
			unset( $args['strategy'] );
		}

		if ( empty( $strategy ) || ! is_string( $strategy ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Strategy must be supplied.', 'pwa' ),
				'0.6'
			);
			return false;
		}
		$args['strategy'] = $strategy;

		if ( empty( $route ) || ! is_string( $route ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Route must be a non-empty string.', 'pwa' ),
				'0.2'
			);
			return false;
		}

		$errors = new WP_Error();
		$args   = self::normalize_configuration( $args, $errors );
		if ( ! empty( $errors->errors ) ) {
			foreach ( $errors->errors as $error_messages ) {
				_doing_it_wrong( __METHOD__, esc_html( implode( ' ', $error_messages ) ), '0.6' );
			}

			// The strategy is the only hard error.
			if ( isset( $errors->errors['missing_strategy'] ) || isset( $errors->errors['invalid_strategy'] ) ) {
				return false;
			}
		}

		$args['route']  = $route;
		$this->routes[] = $args;
		return true;
	}

	/**
	 * Normalize configuration.
	 *
	 * @since 0.6
	 *
	 * @param array    $config Configuration.
	 * @param WP_Error $errors Errors object. Issues with the config are added to this collection.
	 * @return array Normalized configuration.
	 */
	public static function normalize_configuration( $config, WP_Error $errors ) {
		$valid_strategies = array(
			self::STRATEGY_STALE_WHILE_REVALIDATE,
			self::STRATEGY_CACHE_FIRST,
			self::STRATEGY_CACHE_ONLY,
			self::STRATEGY_NETWORK_FIRST,
			self::STRATEGY_NETWORK_ONLY,
		);

		if ( empty( $config['strategy'] ) ) {
			$errors->add(
				'missing_strategy',
				sprintf(
					/* translators: %s is a comma-separated list of valid strategies */
					__( 'Strategy must be one out of %s.', 'pwa' ),
					implode( ', ', $valid_strategies )
				)
			);
		} else {
			// Ensure Workbox<=3 strategy factory names like "networkFirst" are converted to class names like "NetworkFirst".
			$config['strategy'] = ucfirst( $config['strategy'] );

			if ( ! in_array( $config['strategy'], $valid_strategies, true ) ) {
				$errors->add(
					'invalid_strategy',
					sprintf(
						/* translators: %s is a comma-separated list of valid strategies */
						__( 'Strategy must be one out of %s.', 'pwa' ),
						implode( ', ', $valid_strategies )
					)
				);
			}
		}

		// Merge plugins into top-level.
		if ( isset( $config['plugins'] ) ) {
			$errors->add(
				'obsolete_plugins_key',
				__( 'The plugins configuration key is obsolete. Define Workbox plugin configuration at the top level.', 'pwa' )
			);
			$config = array_merge( $config, $config['plugins'] );
			unset( $config['plugins'] );
		}

		// Normalization to the snake_case convention is done in PHP, with convention camelCase
		// done when exporting the data to JS to pass to Workbox.
		$config = self::convert_camel_case_array_keys_to_snake_case( $config );

		$unexpected_keys = array_diff(
			array_keys( $config ),
			array_merge(
				self::WORKBOX_CORE_PLUGINS,
				array(
					'strategy',
					'cache_name',
					'network_timeout_seconds',
				)
			)
		);
		if ( ! empty( $unexpected_keys ) ) {
			$errors->add(
				'unexpected_keys',
				sprintf(
					/* translators: %s is a comma-separated list of valid strategies */
					__( 'Unexpected caching strategy keys: %s.', 'pwa' ),
					implode( ', ', $unexpected_keys )
				),
				array(
					'keys' => $unexpected_keys,
				)
			);
		}

		foreach ( self::WORKBOX_CORE_PLUGINS as $plugin_name ) {
			if ( ! array_key_exists( $plugin_name, $config ) ) {
				continue;
			}
			$plugin_config = $config[ $plugin_name ];
			if ( false === $plugin_config || null === $plugin_config ) {
				// Skip plugin if it was explicitly disabled.
				unset( $config[ $plugin_name ] );
			} elseif ( ! is_array( $plugin_config ) ) {
				$errors->add(
					'unexpected_plugin_config',
					/* translators: %s is plugin name */
					sprintf( __( 'Non-array configuration for %s. Normalized to empty array.', 'pwa' ), $plugin_name )
				);
				$config[ $plugin_name ] = array();
			}
		}

		return $config;
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
	 * @param array $strategy_args Strategy args. Already normalized with snake_case keys.
	 * @return string JS IIFE which returns object for passing to registerRoute.
	 */
	public static function prepare_strategy_args_for_js_export( $strategy_args ) {
		$exported = '( function() {';

		// Pluck out plugins defined at the top-level.
		$plugins = array();
		foreach ( self::WORKBOX_CORE_PLUGINS as $plugin_name ) {
			if ( array_key_exists( $plugin_name, $strategy_args ) ) {
				$plugin_config = $strategy_args[ $plugin_name ];
				unset( $strategy_args[ $plugin_name ] );
				$plugins[ $plugin_name ] = $plugin_config;
			}
		}

		// Extract plugins since not JSON-serializable as-is.
		$plugins_js = array();
		foreach ( $plugins as $plugin_name => $plugin_config ) {
			$camel_case_plugin_name = self::convert_snake_case_to_camel_case( $plugin_name );

			$plugins_js[] = sprintf(
				'new wp.serviceWorker[ %s ][ %s ]( %s )',
				wp_json_encode( $camel_case_plugin_name ),
				wp_json_encode( ucfirst( $camel_case_plugin_name ) . 'Plugin' ),
				wp_json_encode( self::convert_snake_case_array_keys_to_camel_case( $plugin_config ), empty( $plugin_config ) ? JSON_FORCE_OBJECT : 0 )
			);
		}

		$strategy_args = self::convert_snake_case_array_keys_to_camel_case( $strategy_args );

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
	 * Convert array keys from camelCase to snake_case.
	 *
	 * @since 0.6
	 * @see WP_Service_Worker_Caching_Routes_Component::get_script()
	 *
	 * @param array $original Original array.
	 * @return array Array with camelCased-array keys.
	 */
	public static function convert_camel_case_array_keys_to_snake_case( $original ) {
		$camel_case = array();
		foreach ( $original as $key => $value ) {
			if ( is_array( $value ) && ! isset( $value[0] ) ) {
				$value = self::convert_camel_case_array_keys_to_snake_case( $value );
			}
			$camel_case[ self::convert_camel_case_to_snake_case( $key ) ] = $value;
		}
		return $camel_case;
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
	public static function convert_snake_case_array_keys_to_camel_case( $original ) {
		$camel_case = array();
		foreach ( $original as $key => $value ) {
			if ( is_array( $value ) && ! isset( $value[0] ) ) {
				$value = self::convert_snake_case_array_keys_to_camel_case( $value );
			}
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
