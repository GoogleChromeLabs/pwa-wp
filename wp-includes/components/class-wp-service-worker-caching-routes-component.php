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
			// Ensure Workbox<=3 strategy factory names like "networkFirst" are converted to class names like "NetworkFirst".
			$strategy = ucfirst( $route_data['strategy'] );

			$script .= sprintf(
				'wp.serviceWorker.routing.registerRoute( new RegExp( %s ), new wp.serviceWorker.strategies[ %s ]( %s ) );',
				wp_service_worker_json_encode( $route_data['route'] ),
				wp_service_worker_json_encode( $strategy ),
				WP_Service_Worker_Caching_Routes::prepare_strategy_args_for_js_export( $route_data['strategy_args'] )
			);
		}

		return $script;
	}
}
