<?php
/**
 * WP_Service_Worker_Custom_Background_Integration class.
 *
 * @package PWA
 */

/**
 * Class representing the Custom Background service worker integration.
 *
 * @since 0.2
 */
final class WP_Service_Worker_Custom_Background_Integration extends WP_Service_Worker_Base_Integration {

	/**
	 * Registers the integration functionality.
	 *
	 * @since 0.2
	 *
	 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
	 */
	public function register( WP_Service_Worker_Scripts $scripts ) {
		if ( ! current_theme_supports( 'custom-background' ) || ! get_background_image() ) {
			return;
		}

		$url      = get_background_image();
		$file     = $scripts->get_validated_file_path( get_background_image() );
		$revision = null;
		if ( is_string( $file ) ) {
			$revision = md5( file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		$scripts->precaching_routes()->register( $url, $revision );
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
