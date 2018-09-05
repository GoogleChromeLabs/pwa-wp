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
	 * @param WP_Service_Worker_Registry $registry Instance to register service worker behavior with.
	 */
	public function register( WP_Service_Worker_Registry $registry ) {
		$registry->register_cached_route(
			'^https:\/\/fonts\.(?:googleapis|gstatic)\.com\/(.*)',
			WP_Service_Worker_Registry::STRATEGY_CACHE_FIRST,
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
}
