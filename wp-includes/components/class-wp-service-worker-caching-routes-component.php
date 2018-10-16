<?php
/**
 * WP_Service_Worker_Caching_Routes_Component class.
 *
 * @package PWA
 */

/**
 * Class representing the service worker core component for caching routes.
 *
 * @since 0.2
 */
class WP_Service_Worker_Caching_Routes_Component implements WP_Service_Worker_Component, WP_Service_Worker_Registry_Aware {

	/**
	 * Caching routes registry.
	 *
	 * @since 0.2
	 * @var WP_Service_Worker_Caching_Routes
	 */
	protected $registry;

	/**
	 * Constructor.
	 *
	 * Instantiates the registry.
	 *
	 * @since 0.2
	 */
	public function __construct() {
		$this->registry = new WP_Service_Worker_Caching_Routes();
	}

	/**
	 * Adds the component functionality to the service worker.
	 *
	 * @since 0.2
	 *
	 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
	 */
	public function serve( WP_Service_Worker_Scripts $scripts ) {
		$scripts->register(
			'wp-caching-routes',
			array(
				'src'  => array( $this, 'get_script' ),
				'deps' => array( 'wp-base-config' ),
			)
		);
	}

	/**
	 * Gets the priority this component should be hooked into the service worker action with.
	 *
	 * @since 0.2
	 *
	 * @return int Hook priority. A higher number means a lower priority.
	 */
	public function get_priority() {
		return 999999;
	}

	/**
	 * Gets the registry.
	 *
	 * @return WP_Service_Worker_Caching_Routes Caching routes registry instance.
	 */
	public function get_registry() {
		return $this->registry;
	}

	/**
	 * Gets the script that registers the caching routes.
	 *
	 * @since 0.2
	 *
	 * @return string Script.
	 */
	public function get_script() {
		$routes = $this->registry->get_all();

		$script = '';
		foreach ( $routes as $route_data ) {
			$script .= sprintf(
				'wp.serviceWorker.routing.registerRoute( new RegExp( %s ), wp.serviceWorker.strategies[ %s ]( %s ) );',
				wp_service_worker_json_encode( $route_data['route'] ),
				wp_service_worker_json_encode( $route_data['strategy'] ),
				self::prepare_strategy_args_for_js_export( $route_data['strategy_args'] )
			);
		}

		return $script;
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
					_doing_it_wrong( 'WP_Service_Workers::register_cached_route', esc_html__( 'Unrecognized plugin', 'pwa' ), '0.2' );
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
