<?php
/**
 * WP_Service_Worker_Theme_Asset_Caching_Component class.
 *
 * @package PWA
 */

/**
 * Use a network-first caching strategy for assets in the active theme or parent theme.
 *
 * @since 0.6
 */
class WP_Service_Worker_Theme_Asset_Caching_Component implements WP_Service_Worker_Component {

	/**
	 * Cache name.
	 *
	 * @var string
	 */
	const CACHE_NAME = 'theme-assets';

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

		$theme_directory_uri_patterns = array(
			preg_quote( trailingslashit( get_template_directory_uri() ), '/' ),
		);
		if ( get_template() !== get_stylesheet() ) {
			$theme_directory_uri_patterns[] = preg_quote( trailingslashit( get_stylesheet_directory_uri() ), '/' );
		}

		$scripts->caching_routes()->register(
			'^(' . implode( '|', $theme_directory_uri_patterns ) . ').*',
			array(
				// Even though assets should have far-future expiration, network-first is still preferred for development purposes.
				'strategy'  => WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_FIRST,
				'cacheName' => self::CACHE_NAME,
				'plugins'   => array(
					'expiration' => array(
						// @todo The number here should be validated based what themes actually add to the page.
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
