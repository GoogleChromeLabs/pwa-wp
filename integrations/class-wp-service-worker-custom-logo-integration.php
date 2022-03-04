<?php
/**
 * WP_Service_Worker_Custom_Logo_Integration class.
 *
 * @package PWA
 */

/**
 * Class representing the Custom Logo service worker integration.
 *
 * @since 0.2
 * @deprecated 0.7 Integrations will not be proposed for WordPress core merge.
 */
final class WP_Service_Worker_Custom_Logo_Integration extends WP_Service_Worker_Base_Integration {

	/**
	 * Registers the integration functionality.
	 *
	 * @since 0.2
	 *
	 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
	 */
	public function register( WP_Service_Worker_Scripts $scripts ) {
		if ( ! current_theme_supports( 'custom-logo' ) || ! get_theme_mod( 'custom_logo' ) ) {
			return;
		}

		$attachment = get_post( get_theme_mod( 'custom_logo' ) );
		if ( ! $attachment ) {
			return;
		}

		$image_urls = $this->get_attachment_image_urls( $attachment->ID, 'full' );
		$image_src  = wp_get_attachment_image_src( $attachment->ID, 'full' );
		if ( $image_src ) {
			$image_urls[] = $image_src[0];
		}

		foreach ( array_unique( $image_urls ) as $image_url ) {
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
		$this->scope = WP_Service_Workers::SCOPE_FRONT;
	}
}
