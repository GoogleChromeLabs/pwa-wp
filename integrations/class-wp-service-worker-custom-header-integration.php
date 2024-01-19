<?php
/**
 * WP_Service_Worker_Custom_Header_Integration class.
 *
 * @package PWA
 */

/**
 * Class representing the Custom Header service worker integration.
 *
 * @since 0.2
 * @deprecated 0.7 Integrations will not be proposed for WordPress core merge.
 */
final class WP_Service_Worker_Custom_Header_Integration extends WP_Service_Worker_Base_Integration {

	/**
	 * Registers the integration functionality.
	 *
	 * @since 0.2
	 *
	 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
	 */
	public function register( WP_Service_Worker_Scripts $scripts ) {
		if ( ! current_theme_supports( 'custom-header' ) || ! get_custom_header()->url ) {
			return;
		}

		if ( is_random_header_image() ) {
			// What follows comes fom _get_random_header_data().
			global $_wp_default_headers;
			$header_image_mod = get_theme_mod( 'header_image', '' );

			$headers = array();
			if ( 'random-uploaded-image' === $header_image_mod ) {
				$headers = get_uploaded_header_images();
			} elseif ( ! empty( $_wp_default_headers ) ) {
				if ( 'random-default-image' === $header_image_mod ) {
					$headers = $_wp_default_headers;
				} elseif ( current_theme_supports( 'custom-header', 'random-default' ) ) {
						$headers = $_wp_default_headers;
				}
			}

			foreach ( $headers as $header ) {
				$url      = sprintf( $header['url'], get_template_directory_uri(), get_stylesheet_directory_uri() );
				$file     = $scripts->get_validated_file_path( get_header_image() );
				$revision = null;
				if ( is_string( $file ) ) {
					$revision = md5( file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
				}
				$scripts->precaching_routes()->register( $url, compact( 'revision' ) );
			}
			return;
		}

		$header       = get_custom_header();
		$attachment   = ! empty( $header->attachment_id ) ? get_post( get_custom_header()->attachment_id ) : null;
		$header_image = get_header_image();

		if ( $attachment ) {
			foreach ( $this->get_attachment_image_urls( $attachment->ID, array( $header->width, $header->height ) ) as $image_url ) {
				$scripts->precaching_routes()->register( $image_url, array( 'revision' => $attachment->post_modified ) );
			}
		} elseif ( is_string( $header_image ) ) {
			$file     = $scripts->get_validated_file_path( $header_image );
			$revision = null;
			if ( is_string( $file ) ) {
				$revision = md5( file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
			}
			$scripts->precaching_routes()->register( $header_image, compact( 'revision' ) );
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
