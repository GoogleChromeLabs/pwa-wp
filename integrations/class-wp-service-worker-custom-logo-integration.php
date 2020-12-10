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
			$scripts->precaching_routes()->register( $image_url, $attachment->post_modified );
		}
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
