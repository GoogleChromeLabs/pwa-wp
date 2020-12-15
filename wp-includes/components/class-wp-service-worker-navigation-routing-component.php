<?php
/**
 * WP_Service_Worker_Navigation_Routing_Component class.
 *
 * @package PWA
 */

/**
 * Class representing the service worker core component for handling navigation requests.
 *
 * @todo The component system needs to be instantiated even if the service worker is not being served.
 * @since 0.2
 */
final class WP_Service_Worker_Navigation_Routing_Component implements WP_Service_Worker_Component {

	/**
	 * Cache name.
	 *
	 * @var string
	 */
	const CACHE_NAME = 'navigations';

	/**
	 * Slug used to identify whether a theme supports service worker streaming.
	 *
	 * @since 0.2
	 * @since 0.4 Obsolete.
	 * @var string
	 * @deprecated Since 0.4 all streaming support was removed. See <https://github.com/GoogleChromeLabs/pwa-wp/issues/191>. Constant is left in place for the time to prevent fatal error if referenced.
	 */
	const STREAM_THEME_SUPPORT = 'service_worker_streaming';

	/**
	 * Internal storage for replacements to make in the error response handling script.
	 *
	 * @since 0.2
	 * @var array
	 */
	protected $replacements = array();

	/**
	 * Get hash of nav menu locations and their items.
	 *
	 * This is used to vary the cache of the navigation route, offline template route, and 500 error route.
	 *
	 * @since 0.2
	 *
	 * @return string Hash of nav menu items.
	 */
	protected function get_nav_menu_locations_hash() {
		$items = array();
		foreach ( get_nav_menu_locations() as $nav_menu_id ) {
			if ( $nav_menu_id ) {
				$items[ $nav_menu_id ] = wp_get_nav_menu_items( (int) $nav_menu_id );
			}
		}
		return md5( wp_json_encode( $items ) );
	}

	/**
	 * Adds the component functionality to the service worker.
	 *
	 * @since 0.2
	 *
	 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
	 */
	public function serve( WP_Service_Worker_Scripts $scripts ) {
		$template   = get_template();
		$stylesheet = get_stylesheet();

		$revision = PWA_VERSION;

		$revision .= sprintf( ';%s=%s', $template, wp_get_theme( $template )->version );
		if ( $template !== $stylesheet ) {
			$revision .= sprintf( ';%s=%s', $stylesheet, wp_get_theme( $stylesheet )->version );
		}

		if ( ! is_admin() ) {
			// Note that themes will need to vary the revision further by whatever is contained in the app shell.
			$revision .= ';options=' . md5(
				wp_json_encode(
					array(
						'blogname'        => get_option( 'blogname' ),
						'blogdescription' => get_option( 'blogdescription' ),
						'site_icon_url'   => get_site_icon_url(),
						'theme_mods'      => get_theme_mods(),
					)
				)
			);

			// Vary the precaches by the nav menus.
			$revision .= ';nav=' . $this->get_nav_menu_locations_hash();

			// Include all scripts and styles in revision.
			$revision .= ';deps=' . md5( wp_json_encode( array( wp_scripts()->queue, wp_styles()->queue ) ) );

			// @todo Allow different routes to have varying caching strategies?

			/**
			 * Filters caching strategy used for frontend navigation requests.
			 *
			 * @since 0.2
			 * @deprecated Use wp_service_worker_navigation_caching instead.
			 * @todo Use apply_filters_deprecated() in subsequent release.
			 * @see WP_Service_Worker_Caching_Routes::register()
			 *
			 * @param string $caching_strategy Caching strategy to use for frontend navigation requests.
			 */
			$caching_strategy = apply_filters( 'wp_service_worker_navigation_caching_strategy', '' );
			if ( empty( $caching_strategy ) ) {
				if ( get_option( 'offline_browsing' ) ) {
					$caching_strategy = WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_FIRST;
				} else {
					$caching_strategy = WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_ONLY;
				}
			}

			/**
			 * Filters the caching strategy args used for frontend navigation requests.
			 *
			 * @since 0.2
			 * @deprecated Use wp_service_worker_navigation_caching instead.
			 * @todo Use apply_filters_deprecated() in subsequent release.
			 * @see WP_Service_Worker_Caching_Routes::register()
			 *
			 * @param array $caching_strategy_args Caching strategy args.
			 */
			$config = apply_filters( 'wp_service_worker_navigation_caching_strategy_args', array() );

			// Provide default config if no config was already provided via deprecated filters above.
			if ( empty( $config ) ) {
				$config = array(
					'strategy'   => $caching_strategy,
					'cache_name' => self::CACHE_NAME,
				);

				if ( WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_FIRST === $caching_strategy ) {
					/*
					 * The value of 2 seconds is informed by the Largest Contentful Paint (LCP) metric, of which Time to
					 * First Byte (TTFB) is a major component. As long as all assets on a page are cached, then this allows
					 * for the service worker to serve a previously-cached page and then for LCP to occur before 2.5s and
					 * so remain within the good threshold.
					 */
					$config['network_timeout_seconds'] = 2;
				}

				/*
				 * By default cache only the last 10 pages visited. This may end up being too high as it seems likely that
				 * most site visitors will view one page and then maybe a couple others.
				 */
				$config['expiration']['max_entries'] = 10;
			} else {
				// Migrate legacy format to normalized format to pass into wp_service_worker_navigation_caching filter.
				// The WP_Error is not stored since this filter is deprecated.
				$config = WP_Service_Worker_Caching_Routes::normalize_configuration( $config, new WP_Error() );

				$config['strategy'] = $caching_strategy;
			}

			/**
			 * Filters service worker caching configuration for navigation requests.
			 *
			 * @since 0.6
			 *
			 * @param array {
			 *     Navigation caching configuration. If array filtered to be empty, then caching is disabled.
			 *     Use snake_case convention instead of camelCase (where the latter will automatically convert to former).
			 *
			 *     @type string     $strategy                Strategy. Defaults to NetworkFirst. See <https://developers.google.com/web/tools/workbox/reference-docs/latest/module-workbox-strategies>.
			 *     @type int        $network_timeout_seconds Network timeout seconds. Only applies to NetworkFirst strategy.
			 *     @type string     $cache_name              Cache name. Defaults to 'navigations'. This will get a site-specific prefix to prevent subdirectory multisite conflicts.
			 *     @type array|null $expiration {
			 *          Expiration plugin configuration. See <https://developers.google.com/web/tools/workbox/reference-docs/latest/module-workbox-expiration.ExpirationPlugin>.
			 *
			 *          @type int|null $max_entries     Max entries to cache.
			 *          @type int|null $max_age_seconds Max age seconds.
			 *     }
			 *     @type array|null $broadcast_update   Broadcast update plugin configuration. Not included by default. See <https://developers.google.com/web/tools/workbox/reference-docs/latest/module-workbox-broadcast-update.BroadcastUpdatePlugin>.
			 *     @type array|null $cacheable_response Cacheable response plugin configuration. Not included by default. See <https://developers.google.com/web/tools/workbox/reference-docs/latest/module-workbox-cacheable-response.CacheableResponsePlugin>.
			 * }
			 */
			$config = apply_filters( 'wp_service_worker_navigation_caching', $config );

			if ( is_array( $config ) ) {
				// Validate and normalize configuration.
				$errors = new WP_Error();
				$config = WP_Service_Worker_Caching_Routes::normalize_configuration( $config, $errors );
				foreach ( $errors->errors as $error_messages ) {
					_doing_it_wrong( __METHOD__, esc_html( current( $error_messages ) ), '0.6' );
				}
				if ( isset( $errors->errors['missing_strategy'] ) || isset( $errors->errors['invalid_strategy'] ) ) {
					$config['strategy'] = WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_ONLY;
				}
			} else {
				$config = array(
					'strategy' => WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_ONLY,
				);
			}

			$caching_strategy = $config['strategy'];
			unset( $config['strategy'] );

			$caching_strategy_args_js = WP_Service_Worker_Caching_Routes::prepare_strategy_args_for_js_export( $config );

			$offline_error_template_file  = pwa_locate_template( array( 'offline.php', 'error.php' ) );
			$offline_error_precache_entry = array(
				'url'      => add_query_arg( 'wp_error_template', 'offline', home_url( '/' ) ),
				'revision' => $revision . ';' . md5( $offline_error_template_file . file_get_contents( $offline_error_template_file ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			);
			$server_error_template_file   = pwa_locate_template( array( '500.php', 'error.php' ) );
			$server_error_precache_entry  = array(
				'url'      => add_query_arg( 'wp_error_template', '500', home_url( '/' ) ),
				'revision' => $revision . ';' . md5( $server_error_template_file . file_get_contents( $server_error_template_file ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			);

			/**
			 * Filters what is precached to serve as the offline error response on the frontend.
			 *
			 * The URL returned in this array will be precached by the service worker and served as the response when
			 * the client is offline or their connection fails. To prevent this behavior, this value can be filtered
			 * to return false. When a theme or plugin makes a change to the response, the revision value in the array
			 * must be incremented to ensure the URL is re-fetched to store in the precache.
			 *
			 * @since 0.2
			 * @todo Rename this filter to wp_offline_error_route.
			 *
			 * @param array|false $entry {
			 *     Offline error precache entry.
			 *
			 *     @type string $url      URL to page that shows the offline error template.
			 *     @type string $revision Revision for the template. This defaults to the template and stylesheet names, with their respective theme versions.
			 * }
			 */
			$offline_error_precache_entry = apply_filters( 'wp_offline_error_precache_entry', $offline_error_precache_entry );

			/**
			 * Filters what is precached to serve as the internal server error response on the frontend.
			 *
			 * The URL returned in this array will be precached by the service worker and served as the response when
			 * the server returns a 500 internal server error . To prevent this behavior, this value can be filtered
			 * to return false. When a theme or plugin makes a change to the response, the revision value in the array
			 * must be incremented to ensure the URL is re-fetched to store in the precache.
			 *
			 * @since 0.2
			 * @todo Rename this filter to wp_server_error_route.
			 *
			 * @param array $entry {
			 *     Server error precache entry.
			 *
			 *     @type string $url      URL to page that shows the server error template.
			 *     @type string $revision Revision for the template. This defaults to the template and stylesheet names, with their respective theme versions.
			 * }
			 */
			$server_error_precache_entry = apply_filters( 'wp_server_error_precache_entry', $server_error_precache_entry );

			/**
			 * Filters the entry that is used for serving as app shell.
			 *
			 * @since 0.2
			 *
			 * @param array $entry {
			 *     Navigation route entry.
			 *
			 *     @type string|null  $url      URL to page that serves the app shell. By default this is null which means no navigation route is registered.
			 *     @type string       $revision Revision for the the app shell template.
			 * }
			 */
			$navigation_route_precache_entry = apply_filters(
				'wp_service_worker_navigation_route',
				array(
					'url'      => null,
					'revision' => $revision,
				)
			);
		} else {
			// Only network strategy for admin (for now).
			$caching_strategy = WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_ONLY;

			$revision = PWA_VERSION;

			// Force revision to be extra fresh during development (e.g. when PWA_VERSION is x.y-alpha).
			if ( false !== strpos( PWA_VERSION, '-' ) ) {
				$revision .= filemtime( PWA_PLUGIN_DIR . '/wp-admin/error.php' );
				$revision .= filemtime( PWA_PLUGIN_DIR . '/wp-includes/service-workers.php' );
			}

			$offline_error_precache_entry = array(
				'url'      => add_query_arg( 'code', 'offline', admin_url( 'admin-ajax.php?action=wp_error_template' ) ), // Upon core merge, this would use admin_url( 'error.php' ).
				'revision' => $revision, // Upon core merge, this should be the core version.
			);
			$server_error_precache_entry  = array(
				'url'      => add_query_arg( 'code', '500', admin_url( 'admin-ajax.php?action=wp_error_template' ) ), // Upon core merge, this would use admin_url( 'error.php' ).
				'revision' => $revision, // Upon core merge, this should be the core version.
			);

			$navigation_route_precache_entry = false;
		}

		$scripts->register(
			'wp-offline-commenting',
			array(
				'src'  => array( $this, 'get_offline_commenting_script' ),
				'deps' => array( 'wp-base-config' ),
			)
		);

		$scripts->register(
			'wp-navigation-routing',
			array(
				'src'  => array( $this, 'get_script' ),
				'deps' => array( 'wp-base-config', 'wp-offline-commenting' ),
			)
		);

		if ( $offline_error_precache_entry ) {
			$scripts->precaching_routes()->register( $offline_error_precache_entry['url'], isset( $offline_error_precache_entry['revision'] ) ? $offline_error_precache_entry['revision'] : null );
		}
		if ( $server_error_precache_entry ) {
			$scripts->precaching_routes()->register( $server_error_precache_entry['url'], isset( $server_error_precache_entry['revision'] ) ? $server_error_precache_entry['revision'] : null );
		}
		if ( ! empty( $navigation_route_precache_entry['url'] ) ) {
			$scripts->precaching_routes()->register(
				$navigation_route_precache_entry['url'],
				isset( $navigation_route_precache_entry['revision'] ) ? $navigation_route_precache_entry['revision'] : null
			);
		}

		// App shell is mutually exclusive with navigation preload.
		$navigation_preload = empty( $navigation_route_precache_entry['url'] );

		if ( $navigation_preload ) {
			/**
			 * Filters whether navigation preload is enabled.
			 *
			 * The filtered value will be sent as the Service-Worker-Navigation-Preload header value if a truthy string.
			 * This filter should be set to return false to disable navigation preload such as when a site is using
			 * the app shell model, but in practice this filter does not need to be used because by supplying a
			 * navigation route via the wp_service_worker_navigation_route filter, then the navigation preload will
			 * automatically be set to false. Take care of the current scope when setting this, as it is unlikely that the admin
			 * should have navigation preload disabled until core has an admin single-page app. To disable navigation preload on
			 * the frontend only, you may do:
			 *
			 *     add_filter( 'wp_front_service_worker', function() {
			 *         add_filter( 'wp_service_worker_navigation_preload', '__return_false' );
			 *     } );
			 *
			 * Alternatively, you should check the `$current_scope` for example:
			 *
			 *     add_filter( 'wp_service_worker_navigation_preload', function( $preload, $current_scope ) {
			 *         if ( WP_Service_Workers::SCOPE_FRONT === $current_scope ) {
			 *             $preload = false;
			 *         }
			 *         return $preload;
			 *     }, 10, 2 );
			 *
			 * @param bool|string $navigation_preload Whether to use navigation preload. Returning a string will cause it it to populate the Service-Worker-Navigation-Preload header.
			 * @param int $current_scope The current scope. Either 1 (WP_Service_Workers::SCOPE_FRONT) or 2 (WP_Service_Workers::SCOPE_ADMIN).
			 */
			$navigation_preload = apply_filters( 'wp_service_worker_navigation_preload', $navigation_preload, wp_service_workers()->get_current_scope() );

			if ( false === $navigation_preload && WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_ONLY !== $caching_strategy ) {
				$navigation_preload = true;
				_doing_it_wrong(
					'add_filter',
					sprintf(
						/* translators: %s is the wp_service_worker_navigation_preload filter name */
						esc_html__( 'PWA: Navigation preload should not be disabled (via the %s filter) when using a navigation caching strategy (and app shell is not being used). It is being forcibly re-enabled.', 'pwa' ),
						'wp_service_worker_navigation_preload'
					),
					'0.3'
				);
			}
		}

		$this->replacements = array(
			'NAVIGATION_PRELOAD'           => wp_json_encode( $navigation_preload ),
			'CACHING_STRATEGY'             => wp_json_encode( $caching_strategy ),
			'CACHING_STRATEGY_ARGS'        => isset( $caching_strategy_args_js ) ? $caching_strategy_args_js : '{}',
			'NAVIGATION_ROUTE_ENTRY'       => wp_json_encode( $navigation_route_precache_entry ),
			'ERROR_OFFLINE_URL'            => wp_json_encode( isset( $offline_error_precache_entry['url'] ) ? $offline_error_precache_entry['url'] : null ),
			'ERROR_500_URL'                => wp_json_encode( isset( $server_error_precache_entry['url'] ) ? $server_error_precache_entry['url'] : null ),
			'NAVIGATION_DENYLIST_PATTERNS' => wp_json_encode( $this->get_navigation_route_denylist_patterns() ),
			'ERROR_MESSAGES'               => wp_json_encode( wp_service_worker_get_error_messages() ),
		);
	}

	/**
	 * Get denylist patterns for routes to exclude from navigation route handling.
	 *
	 * @since 0.2
	 *
	 * @return array Route regular expressions.
	 */
	public function get_navigation_route_denylist_patterns() {
		$denylist_patterns = array();

		if ( ! is_admin() ) {
			/**
			 * Filter list of URL patterns to denylist from handling from the navigation router.
			 *
			 * @since 0.2
			 * @deprecated Use wp_service_worker_navigation_route_denylist_patterns filter instead.
			 *
			 * @param array $denylist_patterns Denylist patterns.
			 */
			$denylist_patterns = apply_filters_deprecated( 'wp_service_worker_navigation_route_blacklist_patterns', array( $denylist_patterns ), '0.4', 'wp_service_worker_navigation_route_denylist_patterns' );

			/**
			 * Filter list of URL patterns to denylist from handling from the navigation router.
			 *
			 * @since 0.4
			 *
			 * @param array $denylist_patterns Denylist patterns.
			 */
			$denylist_patterns = apply_filters( 'wp_service_worker_navigation_route_denylist_patterns', $denylist_patterns );

			// Exclude admin URLs, if not in the admin.
			$denylist_patterns[] = '^' . preg_quote( untrailingslashit( wp_parse_url( admin_url(), PHP_URL_PATH ) ), '/' ) . '($|\?.*|/.*)';

			// Exclude PHP files (e.g. wp-login.php).
			$denylist_patterns[] = '[^\?]*.\.php($|\?.*)';

			// Exclude service worker requests (to ease debugging).
			$denylist_patterns[] = '.*\?(.*&)?(' . join( '|', array( WP_Service_Workers::QUERY_VAR ) ) . ')=';
			$denylist_patterns[] = '.*/wp\.serviceworker(\?.*)?$';

			// Exclude feed requests.
			$denylist_patterns[] = '[^\?]*\/feed\/(\w+\/)?$';

			// Exclude Customizer preview.
			$denylist_patterns[] = '\?(.+&)*wp_customize=';
			$denylist_patterns[] = '\?(.+&)*customize_changeset_uuid=';
		}

		// Exclude REST API (this only matters if you directly access the REST API in browser).
		$denylist_patterns[] = '^' . preg_quote( wp_parse_url( get_rest_url(), PHP_URL_PATH ), '/' ) . '.*';

		return $denylist_patterns;
	}

	/**
	 * Gets the priority this component should be hooked into the service worker action with.
	 *
	 * @since 0.2
	 *
	 * @return int Hook priority. A higher number means a lower priority.
	 */
	public function get_priority() {
		return 99;
	}

	/**
	 * Get script for routing navigation requests.
	 *
	 * @return string Script.
	 */
	public function get_script() {
		$script = file_get_contents( PWA_PLUGIN_DIR . '/wp-includes/js/service-worker-navigation-routing.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$script = preg_replace( '#/\*\s*global.+?\*/#s', '', $script );

		return preg_replace_callback(
			'/\b(' . implode( '|', array_keys( $this->replacements ) ) . ')\b/',
			array( $this, 'replace_exported_variable' ),
			$script
		);
	}

	/**
	 * Get script for offline commenting requests.
	 *
	 * @return string Script.
	 */
	public function get_offline_commenting_script() {
		$script = file_get_contents( PWA_PLUGIN_DIR . '/wp-includes/js/service-worker-offline-commenting.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$script = preg_replace( '#/\*\s*global.+?\*/#', '', $script );

		return str_replace(
			array_keys( $this->replacements ),
			array_values( $this->replacements ),
			$script
		);
	}

	/**
	 * Replace exported variable.
	 *
	 * @param array $matches Matches.
	 * @return string Replacement.
	 */
	protected function replace_exported_variable( $matches ) {
		if ( isset( $this->replacements[ $matches[0] ] ) ) {
			return $this->replacements[ $matches[0] ];
		}
		return 'null';
	}
}
