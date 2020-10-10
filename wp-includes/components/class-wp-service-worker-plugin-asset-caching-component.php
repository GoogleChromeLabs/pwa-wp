<?php
/**
 * WP_Service_Worker_Plugin_Asset_Caching_Component class.
 *
 * @package PWA
 */

/**
 * Use a network-first caching strategy for assets from the active plugins.
 *
 * @since 0.6
 */
class WP_Service_Worker_Plugin_Asset_Caching_Component implements WP_Service_Worker_Component {

	/**
	 * Cache name.
	 *
	 * @var string
	 */
	const CACHE_NAME = 'plugin-assets';

	/**
	 * Adds the component functionality to the service worker.
	 *
	 * @since 0.6
	 *
	 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
	 */
	public function serve( WP_Service_Worker_Scripts $scripts ) {
		if ( is_admin() ) {
			return;
		}

		$scripts->caching_routes()->register(
			'^' . preg_quote( trailingslashit( plugins_url() ), '/' ) . '.*',
			array(
				'strategy'  => WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_FIRST,
				'cacheName' => self::CACHE_NAME,
				'plugins'   => array(
					'expiration' => array(
						// @todo The number here should be validated based on the number of assets that an average site's plugins add to the page.
						'maxEntries' => 25, // Limit the cached entries to the number of files loaded over network, e.g. JS, CSS, and PNG.
					),
				),
			)
		);
	}

	/**
	 * Gets the priority this component should be hooked into the service worker action with.
	 *
	 * @since 0.6
	 *
	 * @return int Hook priority. A higher number means a lower priority.
	 */
	public function get_priority() {
		return 10;
	}
}
