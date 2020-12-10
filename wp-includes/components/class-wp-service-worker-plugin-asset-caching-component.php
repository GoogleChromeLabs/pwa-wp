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
final class WP_Service_Worker_Plugin_Asset_Caching_Component implements WP_Service_Worker_Component {

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

		$config = array(
			'route'      => '^' . preg_quote( trailingslashit( plugins_url() ), '/' ) . '.*',
			'strategy'   => WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_FIRST,
			'cache_name' => self::CACHE_NAME,
			'expiration' => array(
				'max_entries' => 44,
			),
		);

		/**
		 * Filters service worker caching configuration for plugin asset requests.
		 *
		 * @since 0.6
		 *
		 * @param array {
		 *     Plugin asset caching configuration. If array filtered to be empty, then caching is disabled.
		 *
		 *     @type string     $route      Route. Regular expression pattern to match. See <https://developers.google.com/web/tools/workbox/reference-docs/latest/module-workbox-routing#.registerRoute>.
		 *     @type string     $strategy   Strategy. Defaults to NetworkFirst.
		 *                                  Even though assets should have far-future expiration, network-first is still preferred for development purposes.
		 *                                  See <https://developers.google.com/web/tools/workbox/reference-docs/latest/module-workbox-strategies>.
		 *     @type string     $cache_name Cache name. Defaults to 'uploaded-images'. This will get a site-specific prefix to prevent subdirectory multisite conflicts.
		 *     @type array|null $expiration {
		 *          Expiration plugin configuration. See <https://developers.google.com/web/tools/workbox/reference-docs/latest/module-workbox-expiration.ExpirationPlugin>.
		 *
		 *          @type int|null $max_entries     Max entries to cache. Defaults to 44.
		 *                                          This limit the cached entries to the number of files loaded over network, e.g. JS, CSS, and PNG.
		 *                                          The number 34 is derived from the 75th percentile of plugin assets used on pages served from
		 *                                          WordPress sites, as indexed by HTTP Archive.
		 *                                          See https://github.com/GoogleChromeLabs/pwa-wp/issues/265#issuecomment-706612536.
		 *          @type int|null $max_age_seconds Max age seconds. Defaults to null.
		 *     }
		 *     @type array|null $broadcast_update   Broadcast update plugin configuration. Not included by default. See <https://developers.google.com/web/tools/workbox/reference-docs/latest/module-workbox-broadcast-update.BroadcastUpdatePlugin>.
		 *     @type array|null $cacheable_response Cacheable response plugin configuration. Not included by default. See <https://developers.google.com/web/tools/workbox/reference-docs/latest/module-workbox-cacheable-response.CacheableResponsePlugin>.
		 * }
		 */
		$config = apply_filters( 'wp_service_worker_plugin_asset_caching', $config );

		if ( ! is_array( $config ) || ! isset( $config['route'], $config['strategy'] ) ) {
			return;
		}

		$route = $config['route'];
		unset( $config['route'] );

		$strategy = $config['strategy'];
		unset( $config['strategy'] );

		$scripts->caching_routes()->register( $route, $strategy, $config );
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
