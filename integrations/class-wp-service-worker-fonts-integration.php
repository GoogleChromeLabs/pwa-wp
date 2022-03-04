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
 * @deprecated 0.7 Integrations will not be proposed for WordPress core merge.
 */
final class WP_Service_Worker_Fonts_Integration extends WP_Service_Worker_Base_Integration {

	/**
	 * Registers the integration functionality.
	 *
	 * @since 0.2
	 *
	 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
	 */
	public function register( WP_Service_Worker_Scripts $scripts ) {

		// Cache the Google Fonts stylesheets with a stale while revalidate strategy.
		$scripts->caching_routes()->register(
			'^https:\/\/fonts\.googleapis\.com',
			array(
				'strategy'  => WP_Service_Worker_Caching_Routes::STRATEGY_STALE_WHILE_REVALIDATE,
				'cacheName' => 'google-fonts-stylesheets',
			)
		);

		// Cache the Google Fonts webfont files with a cache first strategy for 1 year.
		$scripts->caching_routes()->register(
			'^https:\/\/fonts\.gstatic\.com',
			array(
				'strategy'  => WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_FIRST,
				'cacheName' => 'google-fonts-webfonts',
				'plugins'   => array(
					'cacheableResponse' => array(
						'statuses' => array( 0, 200 ),
					),
					'expiration'        => array(
						'maxAgeSeconds' => YEAR_IN_SECONDS,
						'maxEntries'    => 30,
					),
				),
			)
		);

		// Add deprecation warning in user's console when service worker is installed.
		$scripts->register(
			__CLASS__ . '-deprecation',
			array(
				'src' => static function () {
					return sprintf(
						'console.warn( %s );',
						wp_json_encode(
							sprintf(
								/* translators: %1$s: integration class name, %2$s: issue url */
								__( 'The %1$s integration in the PWA plugin is no longer being considered WordPress core merge. See %2$s', 'pwa' ),
								__CLASS__,
								'https://github.com/GoogleChromeLabs/pwa-wp/issues/403'
							)
						)
					);
				},
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
