<?php
/**
 * WP_Service_Worker_Precaching_Routes_Component class.
 *
 * @package PWA
 */

/**
 * Class representing the service worker core component for precaching routes.
 *
 * @since 0.2
 */
final class WP_Service_Worker_Precaching_Routes_Component implements WP_Service_Worker_Component {

	/**
	 * Precaching routes registry.
	 *
	 * @since 0.2
	 * @var WP_Service_Worker_Precaching_Routes
	 */
	protected $registry;

	/**
	 * Constructor.
	 *
	 * @since 0.2
	 *
	 * @param WP_Service_Worker_Precaching_Routes $registry Registry.
	 */
	public function __construct( WP_Service_Worker_Precaching_Routes $registry ) {
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
			'wp-precaching-routes',
			array(
				'src'  => array( $this, 'get_script' ),
				'deps' => array( 'wp-base-config' ),
			)
		);
	}

	/**
	 * Gets the priority this component should be hooked into the service worker action with.
	 *
	 * This must be low so that precaching precaching.addRoute() is called before routing.registerRoute().
	 * This is important because a the precaching fetch event handler needs to be added before the fetch
	 * handler for routing, as otherwise a caching strategy could prevent the precached resource from being
	 * served. See https://github.com/google/WebFundamentals/pull/6820/files#diff-a298f19f420180849fe4a3cde57504bfR72
	 *
	 * @since 0.2
	 *
	 * @return int Hook priority. A higher number means a lower priority.
	 */
	public function get_priority() {
		return -99999;
	}

	/**
	 * Gets the script that registers the precaching routes.
	 *
	 * @since 0.2
	 *
	 * @return string Script.
	 */
	public function get_script() {
		$routes = $this->registry->get_all();
		if ( empty( $routes ) ) {
			return '';
		}

		$replacements = array(
			'PRECACHE_ENTRIES' => wp_json_encode( $routes ),
		);

		$script = file_get_contents( PWA_PLUGIN_DIR . '/wp-includes/js/service-worker-precaching.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$script = preg_replace( '#/\*\s*global.+?\*/#', '', $script );

		return str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$script
		);
	}
}
