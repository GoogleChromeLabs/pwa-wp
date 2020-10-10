<?php
/**
 * WP_Service_Worker_Uploaded_Image_Caching_Component class.
 *
 * @package PWA
 */

/**
 * Use a cache-first caching strategy for uploaded images.
 *
 * @since 0.6
 */
class WP_Service_Worker_Uploaded_Image_Caching_Component implements WP_Service_Worker_Component {

	/**
	 * Cache name.
	 *
	 * @var string
	 */
	const CACHE_NAME = 'uploaded-images';

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
		$types = wp_get_ext_types();
		if ( empty( $types['image'] ) ) {
			return;
		}

		$upload_dir       = wp_get_upload_dir();
		$image_extensions = $types['image'];

		/*
		 * Note that the path alone is used because CDN plugins may load from another domain. For example, given an
		 * uploaded image located at:
		 *   https://example.com/wp-content/uploads/2020/04/foo.png
		 * Jetpack can change rewrite the URL to be:
		 *   https://i2.wp.com/example.com/wp-content/uploads/2020/04/foo.png?fit=900%2C832&ssl=1
		 * Therefore, the following will include any URL ending in an image file extension which also is also
		 * preceded by '/wp-content/uploads/'.
		 */
		$scripts->caching_routes()->register(
			sprintf(
				'^(.*%s).*\.(%s)(\?.*)?$',
				preg_quote( wp_parse_url( $upload_dir['baseurl'], PHP_URL_PATH ), '/' ),
				implode(
					'|',
					array_map(
						static function ( $image_extension ) {
							return preg_quote( $image_extension, '/' );
						},
						$image_extensions
					)
				)
			),
			array(
				'strategy'  => WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_FIRST,
				'cacheName' => self::CACHE_NAME,
				'plugins'   => array(
					'expiration' => array(
						'maxAgeSeconds' => MONTH_IN_SECONDS,
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
