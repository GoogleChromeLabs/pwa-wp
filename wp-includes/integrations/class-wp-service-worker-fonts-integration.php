<?php
/**
 * WP_Service_Worker_Fonts_Integration class.
 *
 * @package PWA
 */

/**
 * Class representing the Fonts service worker integration.
 *
 * @since 0.2
 */
class WP_Service_Worker_Fonts_Integration extends WP_Service_Worker_Base_Integration {

	/**
	 * Registers the integration functionality.
	 *
	 * @since 0.2
	 *
	 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
	 */
	public function register( WP_Service_Worker_Scripts $scripts ) {
		$scripts->cache_registry->register_cached_route(
			'^https:\/\/fonts\.(?:googleapis|gstatic)\.com\/(.*)',
			WP_Service_Worker_Cache_Registry::STRATEGY_CACHE_FIRST,
			array(
				'cacheName' => 'googleapis',
				'plugins'   => array(
					'cacheableResponse' => array(
						'statuses' => array( 0, 200 ),
					),
					'expiration'        => array(
						'maxEntries' => 30,
					),
				),
			)
		);
	}

	/**
	 * Defines the scope of this integration by setting `$this->scope`.
	 *
	 * @since 0.2
	 */
	protected function define_scope() {
		$this->scope = WP_Service_Workers::SCOPE_ALL;
	}
}
