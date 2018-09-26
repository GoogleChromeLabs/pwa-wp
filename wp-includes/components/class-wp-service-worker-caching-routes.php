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
	const STRATEGY_STALE_WHILE_REVALIDATE = 'staleWhileRevalidate';

	/**
	 * Cache first caching strategy.
	 *
	 * @since 0.2
	 * @var string
	 */
	const STRATEGY_CACHE_FIRST = 'cacheFirst';

	/**
	 * Network first caching strategy.
	 *
	 * @since 0.2
	 * @var string
	 */
	const STRATEGY_NETWORK_FIRST = 'networkFirst';

	/**
	 * Cache only caching strategy.
	 *
	 * @since 0.2
	 * @var string
	 */
	const STRATEGY_CACHE_ONLY = 'cacheOnly';

	/**
	 * Network only caching strategy.
	 *
	 * @since 0.2
	 * @var string
	 */
	const STRATEGY_NETWORK_ONLY = 'networkOnly';

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
}
