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
		 * When a CDN is being used, it is up to the CDN plugin to register a caching strategy for images served from
		 * the CDN. This is because when resources are loaded from another domain which lacks CORS headers, that is
		 * `Access-Control-Allow-Origin: *`, Workbox will by default refuse to cache them when a CacheFirst strategy
		 * is being used. This is because the responses are opaque and it can't determine whether the response was
		 * successful. In such cases, it is up to the CDN to enable CORS and then for its plugin to explicitly register
		 * its own caching strategy for the assets that it serves.
		 *
		 * See https://developers.google.com/web/tools/workbox/guides/handle-third-party-requests
		 */
		$scripts->caching_routes()->register(
			sprintf(
				'^%s.*\.(%s)(\?.*)?$',
				preg_quote( trailingslashit( $upload_dir['baseurl'] ), '/' ),
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
