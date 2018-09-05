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
class WP_Service_Worker_Custom_Background_Integration extends WP_Service_Worker_Base_Integration {

	/**
	 * Registers the integration functionality.
	 *
	 * @since 0.2
	 *
	 * @param WP_Service_Worker_Cache_Registry $cache_registry Instance to register service worker behavior with.
	 */
	public function register( WP_Service_Worker_Cache_Registry $cache_registry ) {
		if ( ! current_theme_supports( 'custom-background' ) || ! get_background_image() ) {
			return;
		}

		$url      = get_background_image();
		$file     = wp_service_workers()->get_validated_file_path( get_background_image() );
		$revision = null;
		if ( is_string( $file ) ) {
			$revision = md5( file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		$cache_registry->register_precached_route( $url, $revision );
	}
}
