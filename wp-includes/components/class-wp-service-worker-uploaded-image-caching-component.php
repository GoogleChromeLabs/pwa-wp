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
final class WP_Service_Worker_Uploaded_Image_Caching_Component implements WP_Service_Worker_Component {

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
		$route = sprintf(
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
		);

		$config = array(
			'route'      => $route,
			'strategy'   => WP_Service_Worker_Caching_Routes::STRATEGY_STALE_WHILE_REVALIDATE,
			'cache_name' => self::CACHE_NAME,
			'expiration' => array(
				'max_age_seconds' => MONTH_IN_SECONDS,
				'max_entries'     => 100, // Guard against excessive images being cached.
			),
		);

		/**
		 * Filters service worker caching configuration for uploaded image requests.
		 *
		 * @since 0.6
		 *
		 * @param array {
		 *     Uploaded asset caching configuration. If array filtered to be empty, then caching is disabled.
		 *
		 *     @type string     $route      Route. Regular expression pattern to match. See <https://developers.google.com/web/tools/workbox/reference-docs/latest/module-workbox-routing#.registerRoute>.
		 *     @type string     $strategy   Strategy. Defaults to CacheFirst. See <https://developers.google.com/web/tools/workbox/reference-docs/latest/module-workbox-strategies>.
		 *     @type string     $cache_name Cache name. Defaults to 'uploaded-images'. This will get a site-specific prefix to prevent subdirectory multisite conflicts.
		 *     @type array|null $expiration {
		 *          Expiration plugin configuration. See <https://developers.google.com/web/tools/workbox/reference-docs/latest/module-workbox-expiration.ExpirationPlugin>.
		 *
		 *          @type int|null $max_age_seconds Max age seconds. Defaults to MONTH_IN_SECONDS.
		 *          @type int|null $max_entries     Max entries to cache. Defaults to null.
		 *     }
		 *     @type array|null $broadcast_update   Broadcast update plugin configuration. Not included by default. See <https://developers.google.com/web/tools/workbox/reference-docs/latest/module-workbox-broadcast-update.BroadcastUpdatePlugin>.
		 *     @type array|null $cacheable_response Cacheable response plugin configuration. Not included by default. See <https://developers.google.com/web/tools/workbox/reference-docs/latest/module-workbox-cacheable-response.CacheableResponsePlugin>.
		 * }
		 */
		$config = apply_filters( 'wp_service_worker_uploaded_image_caching', $config );

		if ( ! is_array( $config ) || ! isset( $config['route'], $config['strategy'] ) ) {
			return;
		}

		$route = $config['route'];
		unset( $config['route'] );

		$strategy = $config['strategy'];
		unset( $config['strategy'] );

		$scripts->caching_routes()->register( $route, $strategy, $config );
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
