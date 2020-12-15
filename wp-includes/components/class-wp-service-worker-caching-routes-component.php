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
final class WP_Service_Worker_Caching_Routes_Component implements WP_Service_Worker_Component {

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
	 *
	 * @param WP_Service_Worker_Caching_Routes $registry Registry.
	 */
	public function __construct( WP_Service_Worker_Caching_Routes $registry ) {
		$this->registry = $registry;
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
			$strategy = $route_data['strategy'];
			unset( $route_data['strategy'] );

			$route = $route_data['route'];
			unset( $route_data['route'] );

			$script .= sprintf(
				'wp.serviceWorker.routing.registerRoute( new RegExp( %s ), new wp.serviceWorker.strategies[ %s ]( %s ) );',
				wp_json_encode( $route ),
				wp_json_encode( $strategy ),
				WP_Service_Worker_Caching_Routes::prepare_strategy_args_for_js_export( $route_data )
			);
		}

		return $script;
	}
}
