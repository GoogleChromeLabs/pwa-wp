<?php
/**
 * WP_Service_Worker_Core_Asset_Caching_Component class.
 *
 * @package PWA
 */

/**
 * Use a network-first caching strategy for assets from core (in wp-includes).
 *
 * @since 0.6
 */
class WP_Service_Worker_Core_Asset_Caching_Component implements WP_Service_Worker_Component {

	/**
	 * Cache name.
	 *
	 * @var string
	 */
	const CACHE_NAME = 'core-assets';

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

		$cached_base_url_patterns = array(
			preg_quote( trailingslashit( includes_url() ), '/' ) . '.*',
			'https?:\/\/s\.w\.org\/images\/core\/emoji\/.*',
		);

		$scripts->caching_routes()->register(
			'^(' . implode( '|', $cached_base_url_patterns ) . ')',
			array(
				// Even though assets should have far-future expiration, network-first is still preferred for development purposes.
				'strategy'  => WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_FIRST,
				'cacheName' => self::CACHE_NAME,
				'plugins'   => array(
					'expiration' => array(

						/*
						 * Limit the cached entries to the number of files loaded over network, e.g. JS, CSS, and PNG.
						 * The number 14 is derived from the 75th percentile of plugin assets used on pages served from
						 * WordPress sites, as indexed by HTTP Archive.
						 * See https://github.com/GoogleChromeLabs/pwa-wp/issues/265#issuecomment-706612536.
						 */
						'maxEntries' => 14,
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
