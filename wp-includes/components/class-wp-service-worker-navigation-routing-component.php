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
class WP_Service_Worker_Navigation_Routing_Component implements WP_Service_Worker_Component {

	/**
	 * Query var for requesting a stream fragment.
	 *
	 * @since 0.2
	 * @var string
	 */
	const STREAM_FRAGMENT_QUERY_VAR = 'wp_stream_fragment';

	/**
	 * Slug used to identify whether a theme supports service worker streaming.
	 *
	 * @since 0.2
	 * @var string
	 */
	const STREAM_THEME_SUPPORT = 'service_worker_streaming';

	/**
	 * ID for script element that contains the stream combine function definition.
	 *
	 * @since 0.2
	 * @var string
	 */
	const STREAM_COMBINE_DEFINE_SCRIPT_ID = 'wp-stream-define-combine-function';

	/**
	 * ID for script element that contains the stream combine function invocation.
	 *
	 * @since 0.2
	 * @var string
	 */
	const STREAM_COMBINE_INVOKE_SCRIPT_ID = 'wp-stream-invoke-combine-function';

	/**
	 * ID for the stream boundary element.
	 *
	 * @since 0.2
	 * @var string
	 */
	const STREAM_FRAGMENT_BOUNDARY_ELEMENT_ID = 'wp-stream-fragment-boundary';

	/**
	 * Internal storage for replacements to make in the error response handling script.
	 *
	 * @since 0.2
	 * @var array
	 */
	protected $replacements = array();

	/**
	 * Get stream fragment query var.
	 *
	 * @since 0.2
	 *
	 * @return string|null Stream fragment name or null if not requested.
	 */
	public static function get_stream_fragment_query_var() {
		if ( ! isset( $_GET[ self::STREAM_FRAGMENT_QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return null;
		}
		$stream_fragment = wp_unslash( $_GET[ self::STREAM_FRAGMENT_QUERY_VAR ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $stream_fragment, array( 'header', 'body' ), true ) ) {
			return $stream_fragment;
		}
		return null;
	}

	/**
	 * Start output buffering for obtaining a stream fragment.
	 *
	 * This runs at template_redirect. If the theme does not support streaming or the body fragment is not requested,
	 * then this function does nothing.
	 *
	 * @since 0.2
	 * @see WP_Service_Worker_Navigation_Routing_Component::do_stream_boundary() Which reads the output buffer.
	 */
	public static function start_output_buffering_stream_fragment() {
		if ( current_theme_supports( self::STREAM_THEME_SUPPORT ) && 'body' === self::get_stream_fragment_query_var() ) {
			ob_start();
		}
	}

	/**
	 * Determine whether the streaming header is being served.
	 *
	 * This is useful to conditionally output styles that are specific to the header fragment (such as a loading progress bar).
	 *
	 * @since 0.2
	 *
	 * @return bool Whether streaming header is being served.
	 */
	public static function is_streaming_header() {
		return current_theme_supports( self::STREAM_THEME_SUPPORT ) && 'header' === self::get_stream_fragment_query_var();
	}

	/**
	 * Filter the title for the streaming header.
	 *
	 * @since 0.2
	 *
	 * @param string $title Title.
	 * @return string Title.
	 */
	public static function filter_title_for_streaming_header( $title ) {
		if ( self::is_streaming_header() ) {
			$title = __( 'Loading...', 'pwa' );
		}
		return $title;
	}

	/**
	 * Add loading indicator for responses streamed from the service worker.
	 *
	 * This this function should generally be called at the end of a theme's header.php template.
	 * A theme that uses this must also declare 'service_worker_streaming' theme support
	 * This element is also used to demarcate the header (head) from the body (tail).
	 *
	 * @since 2.0
	 *
	 * @param string $loading_content Content to display in the boundary. By default it is "Loading" but it could also be a skeleton placeholder. May contain markup.
	 */
	public static function do_stream_boundary( $loading_content = '' ) {
		if ( ! current_theme_supports( self::STREAM_THEME_SUPPORT ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Failed to add "service_worker_streaming" theme support.', 'pwa' ), '0.2' );
			return;
		}
		$stream_fragment = self::get_stream_fragment_query_var();
		if ( ! $stream_fragment ) {
			return;
		}

		$is_header = 'header' === $stream_fragment;

		printf( '<div id="%s">', esc_attr( self::STREAM_FRAGMENT_BOUNDARY_ELEMENT_ID ) );
		if ( 'header' === $stream_fragment ) {
			if ( ! $loading_content ) {
				$loading_content = esc_html__( 'Loading&hellip;', 'pwa' );
			}
			echo $loading_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';

		if ( $is_header ) {
			$script = file_get_contents( PWA_PLUGIN_DIR . '/wp-includes/js/service-worker-stream-combiner.js' ); // phpcs:ignore
			$vars   = array(
				'STREAM_COMBINE_INVOKE_SCRIPT_ID'     => wp_json_encode( self::STREAM_COMBINE_INVOKE_SCRIPT_ID ),
				'STREAM_COMBINE_DEFINE_SCRIPT_ID'     => wp_json_encode( self::STREAM_COMBINE_DEFINE_SCRIPT_ID ),
				'STREAM_FRAGMENT_BOUNDARY_ELEMENT_ID' => wp_json_encode( self::STREAM_FRAGMENT_BOUNDARY_ELEMENT_ID ),
			);
			$script = str_replace(
				array_keys( $vars ),
				array_values( $vars ),
				$script
			);

			printf(
				'<script id="%s">%s</script>',
				esc_attr( self::STREAM_COMBINE_DEFINE_SCRIPT_ID ),
				$script // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}

		// Short-circuit the response when requesting the header since there is nothing left to stream.
		if ( $is_header ) {
			exit;
		}

		// Handle serving the body. Normally it is output-buffered here. A plugin can disable the default output buffering to handle it later.
		$is_header_buffered = (
			false !== has_action( 'template_redirect', 'WP_Service_Worker_Navigation_Routing_Component::start_output_buffering_stream_fragment' )
			&&
			ob_get_level() > 0
		);
		if ( $is_header_buffered ) {
			$header_html       = ob_get_clean();
			$libxml_use_errors = libxml_use_internal_errors( true );
			$header_html       = preg_replace( '#<noscript.+?</noscript>#s', '', $header_html ); // Some libxml versions croak at noscript in head.
			$dom               = new DOMDocument();
			$result            = $dom->loadHTML( $header_html );
			libxml_clear_errors();
			libxml_use_internal_errors( $libxml_use_errors );
			if ( ! $result ) {
				wp_die( esc_html__( 'Failed to turn header into document.', 'pwa' ) );
			}
			$response = self::get_header_combine_invoke_script( $dom, true );
			echo $response; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Get script for adding to the beginning of the body fragment to combine it with the header.
	 *
	 * @since 0.2
	 *
	 * @param DOMDocument $dom        Document.
	 * @param bool        $serialized Whether to return the script as HTML (true) or a DOM element (false).
	 * @return DOMElement|string DOM element or HTML string for script element containing header data.
	 */
	public static function get_header_combine_invoke_script( $dom, $serialized = true ) {
		$data = array(
			// @todo Add html_attributes?
			'head_nodes'      => array(),
			'body_attributes' => array(),
		);
		$head = $dom->getElementsByTagName( 'head' )->item( 0 );
		if ( $head ) {
			foreach ( $head->childNodes as $node ) { // phpcs:ignore  WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				if ( $node instanceof DOMElement ) {
					if ( 'noscript' === $node->nodeName ) { // phpcs:ignore  WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						continue; // Obviously noscript will never be relevant to synchronize since it will never be evaluated.
					}
					$element = array(
						$node->nodeName, // phpcs:ignore  WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						null,
					);
					if ( $node->hasAttributes() ) {
						$element[1] = array();
						foreach ( $node->attributes as $attribute ) {
							$element[1][ $attribute->nodeName ] = $attribute->nodeValue; // phpcs:ignore  WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						}
					}
					if ( $node->firstChild instanceof DOMText ) { // phpcs:ignore  WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						$element[] = $node->firstChild->nodeValue; // phpcs:ignore  WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					}
					$data['head_nodes'][] = $element;
				} elseif ( $node instanceof DOMComment ) {
					$data['head_nodes'][] = array(
						'#comment',
						$node->nodeValue, // phpcs:ignore  WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					);
				}
			}
		}

		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( $body ) {
			foreach ( $body->attributes as $attribute ) {
				$data['body_attributes'][ $attribute->nodeName ] = $attribute->nodeValue; // phpcs:ignore  WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}
		}

		if ( $serialized ) {
			return sprintf(
				'<script id="%s">wpStreamCombine( %s );</script>',
				esc_attr( self::STREAM_COMBINE_INVOKE_SCRIPT_ID ),
				wp_json_encode( $data, JSON_PRETTY_PRINT ) // phpcs:ignore PHPCompatibility.PHP.NewConstants.json_pretty_printFound -- Defined in core.
			);
		}

		$script = $dom->createElement( 'script' );
		$script->setAttribute( 'id', self::STREAM_COMBINE_INVOKE_SCRIPT_ID );
		$script->appendChild(
			$dom->createTextNode(
				sprintf( 'wpStreamCombine( %s )', wp_json_encode( $data, JSON_PRETTY_PRINT ) ) // phpcs:ignore PHPCompatibility.PHP.NewConstants.json_pretty_printFound -- Defined in core.
			)
		);
		return $script;
	}

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

		$should_stream_response   = ! is_admin() && current_theme_supports( self::STREAM_THEME_SUPPORT );
		$stream_combiner_revision = '';
		if ( $should_stream_response ) {
			$stream_combiner_revision = md5( file_get_contents( PWA_PLUGIN_DIR . '/wp-includes/js/service-worker-stream-combiner.js' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		$revision = PWA_VERSION;

		$revision .= sprintf( ';%s=%s', $template, wp_get_theme( $template )->Version );
		if ( $template !== $stylesheet ) {
			$revision .= sprintf( ';%s=%s', $stylesheet, wp_get_theme( $stylesheet )->Version );
		}

		// Ensure the user-specific offline/500 pages are precached, and that they update when user logs out or switches to another user.
		$revision .= sprintf( ';user=%d', get_current_user_id() );

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
			 * @see WP_Service_Worker_Caching_Routes::register()
			 *
			 * @param string $caching_strategy Caching strategy to use for frontend navigation requests.
			 */
			$caching_strategy = apply_filters( 'wp_service_worker_navigation_caching_strategy', WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_ONLY );

			/**
			 * Filters the caching strategy args used for frontend navigation requests.
			 *
			 * @since 0.2
			 * @see WP_Service_Worker_Caching_Routes::register()
			 *
			 * @param array $caching_strategy_args Caching strategy args.
			 */
			$caching_strategy_args = apply_filters( 'wp_service_worker_navigation_caching_strategy_args', array() );

			$caching_strategy_args_js = WP_Service_Worker_Caching_Routes::prepare_strategy_args_for_js_export( $caching_strategy_args );

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
			if ( WP_DEBUG ) {
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
			if ( $should_stream_response ) {
				$scripts->precaching_routes()->register(
					add_query_arg( self::STREAM_FRAGMENT_QUERY_VAR, 'body', $offline_error_precache_entry['url'] ),
					( isset( $offline_error_precache_entry['revision'] ) ? $offline_error_precache_entry['revision'] : '' ) . $stream_combiner_revision
				);
			}
		}
		if ( $server_error_precache_entry ) {
			$scripts->precaching_routes()->register( $server_error_precache_entry['url'], isset( $server_error_precache_entry['revision'] ) ? $server_error_precache_entry['revision'] : null );
			if ( $should_stream_response ) {
				$scripts->precaching_routes()->register(
					add_query_arg( self::STREAM_FRAGMENT_QUERY_VAR, 'body', $server_error_precache_entry['url'] ),
					( isset( $server_error_precache_entry['revision'] ) ? $server_error_precache_entry['revision'] : '' ) . $stream_combiner_revision
				);
			}
		}
		if ( ! empty( $navigation_route_precache_entry['url'] ) ) {
			$scripts->precaching_routes()->register(
				$navigation_route_precache_entry['url'],
				isset( $navigation_route_precache_entry['revision'] ) ? $navigation_route_precache_entry['revision'] : null
			);
		}

		// Streaming.
		$streaming_header_precache_entry = null;
		if ( $should_stream_response ) {
			$header_template_file            = locate_template( array( 'header.php' ) );
			$streaming_header_precache_entry = array(
				'url'      => add_query_arg( self::STREAM_FRAGMENT_QUERY_VAR, 'header', home_url( '/' ) ),
				'revision' => $revision . ';' . md5( $header_template_file . file_get_contents( $header_template_file ) ) . $stream_combiner_revision, // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			);

			/**
			 * Filters what is precached to serve as the streaming header.
			 *
			 * @since 0.2
			 *
			 * @param array|false $entry {
			 *     Offline error precache entry.
			 *
			 *     @type string $url      URL to streaming header fragment.
			 *     @type string $revision Revision for the entry. Care must be taken to keep this updated based on the content that is output before the stream boundary.
			 * }
			 */
			$streaming_header_precache_entry = apply_filters( 'wp_streaming_header_precache_entry', $streaming_header_precache_entry );

			if ( ! empty( $streaming_header_precache_entry['url'] ) ) {
				$scripts->precaching_routes()->register( $streaming_header_precache_entry['url'], isset( $streaming_header_precache_entry['revision'] ) ? $streaming_header_precache_entry['revision'] : null );
			}
		}

		/*
		 * App shell is mutually exclusive with navigation preload.
		 * Likewise, navigation preload doesn't mix with streaming.
		 */
		$navigation_preload = empty( $streaming_header_precache_entry['url'] ) && empty( $navigation_route_precache_entry['url'] );

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
			'NAVIGATION_PRELOAD'               => wp_service_worker_json_encode( $navigation_preload ),
			'CACHING_STRATEGY'                 => wp_service_worker_json_encode( $caching_strategy ),
			'CACHING_STRATEGY_ARGS'            => isset( $caching_strategy_args_js ) ? $caching_strategy_args_js : '{}',
			'NAVIGATION_ROUTE_ENTRY'           => wp_service_worker_json_encode( $navigation_route_precache_entry ),
			'ERROR_OFFLINE_URL'                => wp_service_worker_json_encode( isset( $offline_error_precache_entry['url'] ) ? $offline_error_precache_entry['url'] : null ),
			'ERROR_OFFLINE_BODY_FRAGMENT_URL'  => wp_service_worker_json_encode( isset( $offline_error_precache_entry['url'] ) ? add_query_arg( self::STREAM_FRAGMENT_QUERY_VAR, 'body', $offline_error_precache_entry['url'] ) : null ),
			'ERROR_500_URL'                    => wp_service_worker_json_encode( isset( $server_error_precache_entry['url'] ) ? $server_error_precache_entry['url'] : null ),
			'ERROR_500_BODY_FRAGMENT_URL'      => wp_service_worker_json_encode( isset( $server_error_precache_entry['url'] ) ? add_query_arg( self::STREAM_FRAGMENT_QUERY_VAR, 'body', $server_error_precache_entry['url'] ) : null ),
			'STREAM_HEADER_FRAGMENT_URL'       => wp_service_worker_json_encode( isset( $streaming_header_precache_entry['url'] ) ? $streaming_header_precache_entry['url'] : null ),
			'NAVIGATION_BLACKLIST_PATTERNS'    => wp_service_worker_json_encode( $this->get_navigation_route_blacklist_patterns() ),
			'SHOULD_STREAM_RESPONSE'           => wp_service_worker_json_encode( $should_stream_response ),
			'STREAM_HEADER_FRAGMENT_QUERY_VAR' => wp_service_worker_json_encode( self::STREAM_FRAGMENT_QUERY_VAR ),
			'ERROR_MESSAGES'                   => wp_service_worker_json_encode( wp_service_worker_get_error_messages() ),
		);
	}

	/**
	 * Get blacklist patterns for routes to exclude from navigation route handling.
	 *
	 * @since 0.2
	 *
	 * @return array Route regular expressions.
	 */
	public function get_navigation_route_blacklist_patterns() {
		$blacklist_patterns = array();

		if ( ! is_admin() ) {
			/**
			 * Filter list of URL patterns to blacklist from handling from the navigation router.
			 *
			 * @since 0.2
			 *
			 * @param array $blacklist_patterns Blacklist patterns.
			 */
			$blacklist_patterns = apply_filters( 'wp_service_worker_navigation_route_blacklist_patterns', $blacklist_patterns );

			// Exclude admin URLs, if not in the admin.
			$blacklist_patterns[] = '^' . preg_quote( untrailingslashit( wp_parse_url( admin_url(), PHP_URL_PATH ) ), '/' ) . '($|\?.*|/.*)';

			// Exclude PHP files (e.g. wp-login.php).
			$blacklist_patterns[] = '[^\?]*.\.php($|\?.*)';

			// Exclude service worker and stream fragment requests (to ease debugging).
			$blacklist_patterns[] = '.*\?(.*&)?(' . join( '|', array( self::STREAM_FRAGMENT_QUERY_VAR, WP_Service_Workers::QUERY_VAR ) ) . ')=';

			// Exclude feed requests.
			$blacklist_patterns[] = '[^\?]*\/feed\/(\w+\/)?$';

			// Exclude Customizer preview.
			$blacklist_patterns[] = '\?(.+&)*wp_customize=';
			$blacklist_patterns[] = '\?(.+&)*customize_changeset_uuid=';
		}

		// Exclude REST API (this only matters if you directly access the REST API in browser).
		$blacklist_patterns[] = '^' . preg_quote( wp_parse_url( get_rest_url(), PHP_URL_PATH ), '/' ) . '.*';

		return $blacklist_patterns;
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
