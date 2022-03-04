<?php
/**
 * WP_Service_Worker_Site_Icon_Integration class.
 *
 * @package PWA
 */

/**
 * Class representing the Site Icon service worker integration.
 *
 * @since 0.2
 * @deprecated 0.7 Integrations will not be proposed for WordPress core merge.
 */
final class WP_Service_Worker_Site_Icon_Integration extends WP_Service_Worker_Base_Integration {

	/**
	 * Registers the integration functionality.
	 *
	 * @since 0.2
	 *
	 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
	 */
	public function register( WP_Service_Worker_Scripts $scripts ) {
		if ( ! has_site_icon() || ! get_option( 'site_icon' ) ) {
			return;
		}

		$attachment = get_post( get_option( 'site_icon' ) );
		if ( ! $attachment ) {
			return;
		}

		// The URLs here are those which are used in wp_site_icon().
		// @todo There could be different icons actually used on the site due to the site_icon_meta_tags filter.
		$image_urls = array_unique(
			array(
				get_site_icon_url( 32 ),
				get_site_icon_url( 192 ),
				get_site_icon_url( 180 ),
				get_site_icon_url( 270 ),
			)
		);

		foreach ( $image_urls as $image_url ) {
			$scripts->precaching_routes()->register( $image_url, array( 'revision' => $attachment->post_modified ) );
		}

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
