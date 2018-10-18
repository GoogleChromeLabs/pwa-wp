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
	const STREAM_COMBINE_DEFINE_SCRIPT_ID = 'wp-stream-combine-function';

	/**
	 * ID for script element that contains the stream combine function invocation.
	 *
	 * @since 0.2
	 * @var string
	 */
	const STREAM_COMBINE_INVOKE_SCRIPT_ID = 'wp-stream-combine-function';

	/**
	 * ID for the stream boundary element.
	 *
	 * @since 0.2
	 * @var string
	 */
	const STREAM_FRAGMENT_BOUNDARY_ELEMENT_ID = 'wp-stream-fragment-boundary';

	/**
	 * Start stream boundary.
	 *
	 * @var string
	 */
	const START_STREAM_BOUNDARY_COMMENT = 'WP_BEGIN_STREAM_BOUNDARY';

	/**
	 * End stream boundary comment.
	 *
	 * @var string
	 */
	const END_STREAM_BOUNDARY_COMMENT = 'WP_END_STREAM_BOUNDARY';

	/**
	 * Internal storage for replacements to make in the error response handling script.
	 *
	 * @since 0.2
	 * @var array
	 */
	protected $replacements = array();

	/**
	 * Determine whether the streaming header is being served.
	 *
	 * @return bool Whether streaming header is being served.
	 */
	public static function is_streaming_header() {
		return current_theme_supports( self::STREAM_THEME_SUPPORT ) && 'header' === get_query_var( self::STREAM_FRAGMENT_QUERY_VAR );
	}

	/**
	 * Add loading indicator for responses streamed from the service worker.
	 *
	 * This this function should generally be called at the end of a theme's header.php template.
	 * A theme that uses this must also declare 'streaming' among the amp theme support.
	 * This element is also used to demarcate the header (head) from the body (tail).
	 *
	 * @since 2.0
	 * @todo Consider adding a comment before and after the boundary to make it easier for non-DOM location. Do we need this?
	 *
	 * @param string $loading_content Content to display in the boundary. By default it is "Loading" but it could also be a placeholder.
	 */
	public static function do_stream_boundary( $loading_content = '' ) {
		if ( ! current_theme_supports( self::STREAM_THEME_SUPPORT ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Failed to add "service_worker_streaming" theme support.', 'pwa' ), '0.2' );
			return;
		}
		$stream_fragment = get_query_var( self::STREAM_FRAGMENT_QUERY_VAR );
		if ( ! $stream_fragment ) {
			return;
		}

		$is_header = 'header' === $stream_fragment;

//		printf( '<!--%s-->', self::START_STREAM_BOUNDARY_COMMENT ); // WPCS: XSS OK.
		if ( 'header' === $stream_fragment ) {
			printf( '<div id="%s">', esc_attr( self::STREAM_FRAGMENT_BOUNDARY_ELEMENT_ID ) );
			if ( ! $loading_content ) {
				$loading_content = esc_html__( 'Loading&hellip;', 'pwa' );
			}
			echo $loading_content; // WPCS: XSS OK.
			echo '</div>';
		}

		if ( $is_header ) {
			printf(
				'<script id="%s">%s</script>',
				esc_attr( self::STREAM_COMBINE_DEFINE_SCRIPT_ID ),
				file_get_contents( PWA_PLUGIN_DIR . '/wp-includes/js/service-worker-stream-combiner.js' ) // phpcs:ignore
			);
		}

		// @todo We don't need this really because we can just use STREAM_COMBINE_DEFINE_SCRIPT_ID as the marker.
//		printf( '<!--%s-->', self::END_STREAM_BOUNDARY_COMMENT ); // WPCS: XSS OK.

		// Short-circuit the response when requesting the header since there is nothing left to stream.
		if ( $is_header ) {
			exit;
		}

		// Handle serving the body. Normally it is output-buffered here.
		$is_header_buffered = (
			false !== has_action( 'template_redirect', 'WP_Service_Worker_Navigation_Routing_Component::start_output_buffering_stream_fragment' )
			&&
			ob_get_level() > 0
		);
		if ( $is_header_buffered ) {
			$header_html       = ob_get_clean();
			$libxml_use_errors = libxml_use_internal_errors( true );
			$header_html       = preg_replace( '#<noscript.+?</noscript>#s', '', $header_html ); // Some libxml versions croak at noscript in head.
			$dom               = new DOMDocument( $header_html );
			$result            = $dom->loadHTML( $header_html );
			libxml_clear_errors();
			libxml_use_internal_errors( $libxml_use_errors );
			if ( ! $result ) {
				wp_die( esc_html__( 'Failed to turn header into document.', 'pwa' ) );
			}
			$response = self::prepare_stream_body_fragment( $dom );
			if ( is_wp_error( $response ) ) {
				wp_die( esc_html( $response->get_error_message() ) );
			}

			echo $response; // WPCS: XSS OK.
		}
	}
	/**
	 * Start output buffering for obtaining a stream fragment.
	 *
	 * This runs at template_redirect. If the theme dues not support streaming or the body fragment is not requested,
	 * then this function does nothing.
	 *
	 * @since 0.2
	 */
	public static function start_output_buffering_stream_fragment() {
		if ( ! current_theme_supports( self::STREAM_THEME_SUPPORT ) ) {
			return;
		}
		$stream_fragment = get_query_var( self::STREAM_FRAGMENT_QUERY_VAR );
		if ( 'body' === $stream_fragment ) {
			ob_start();
		}
	}

	/**
	 * Prepare stream header fragment.
	 *
	 * @since 0.2
	 *
	 * @param DOMDocument $dom Document.
	 * @return string|WP_Error Header response or error.
	 */
//	public static function prepare_stream_header_fragment( $dom ) {
//		$serialized  = "<!DOCTYPE html>\n";
//		$serialized .= AMP_DOM_Utils::get_content_from_dom_node( $dom, $dom->documentElement );
//		$token_pos   = strpos( $serialized, sprintf( '<!--%s-->', self::END_STREAM_BOUNDARY_COMMENT ) );
//		if ( false === $token_pos ) {
//			return new WP_Error( 'fragment_boundary_not_found' );
//		}
//		$response  = substr( $serialized, 0, $token_pos );
//		$response .= sprintf(
//			'<script id="%s">%s</script>',
//			esc_attr( self::STREAM_COMBINE_DEFINE_SCRIPT_ID ),
//			file_get_contents( PWA_PLUGIN_DIR . '/wp-includes/js/service-worker-stream-combiner.js' ) // phpcs:ignore
//		);
//		return $response;
//	}

	/**
	 * Prepare stream body fragment.
	 *
	 * @since 0.2
	 *
	 * @param DOMDocument $dom Document.
	 * @return string|WP_Error Body response or error.
	 */
	public static function prepare_stream_body_fragment( $dom ) {

		// Obtain body fragment.
		$data = array(
			// @todo Add root_attributes?
			'head_nodes'      => array(),
			'body_attributes' => array(),
		);
		$head = $dom->getElementsByTagName( 'head' )->item( 0 );
		if ( ! $head ) {
			return new WP_Error( 'no_head' );
		}
		foreach ( $head->childNodes as $node ) {
			if ( $node instanceof DOMElement ) {
				if ( 'noscript' === $node->nodeName ) {
					continue; // Obviously noscript will never be relevant to synchronize since it will never be evaluated.
				}
				$element = array(
					$node->nodeName,
					null,
				);
				if ( $node->hasAttributes() ) {
					$element[1] = array();
					foreach ( $node->attributes as $attribute ) {
						$element[1][ $attribute->nodeName ] = $attribute->nodeValue;
					}
				}
				if ( $node->firstChild instanceof DOMText ) {
					$element[] = $node->firstChild->nodeValue;
				}
				$data['head_nodes'][] = $element;
			} elseif ( $node instanceof DOMComment ) {
				$data['head_nodes'][] = array(
					'#comment',
					$node->nodeValue,
				);
			}
		}

		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			return new WP_Error( 'no_body' );
		}
		foreach ( $body->attributes as $attribute ) {
			$data['body_attributes'][ $attribute->nodeName ] = $attribute->nodeValue;
		}

		// @todo Also obtain classes used in nav menus.
		$response = sprintf(
			'<script id="%s">wpStreamCombine( %s );</script>',
			esc_attr( self::STREAM_COMBINE_INVOKE_SCRIPT_ID ),
			wp_json_encode( $data, JSON_PRETTY_PRINT ) // phpcs:ignore PHPCompatibility.PHP.NewConstants.json_pretty_printFound -- Defined in core.
		);

		// Include rest of body after the entire response was buffered.
		if ( did_action( 'wp_footer' ) ) {
			/**
			 * Allow plugins to use their own means of serializing the DOM to an HTML string.
			 *
			 * This is needed because PHP versions various issues with serializing HTML.
			 *
			 * @since 0.2
			 * @see AMP_DOM_Utils::get_content_from_dom_node() The AMP plugin has a method that accounts for various cases.
			 *
			 * @param null        $pre The serialized HTML. Plugins should override this to short-circuit DOMDocument::saveHTML() from being called.
			 * @param DOMDocument $dom The document to be serialized.
			 */
			$serialized = apply_filters( 'pre_wp_service_worker_serialize_stream_fragment', null, $dom );
			if ( null === $serialized ) {
				$serialized = $dom->saveHTML();
			}

			$search    = sprintf( '<!--%s-->', self::END_STREAM_BOUNDARY_COMMENT );
			$token_pos = strpos( $serialized, $search );
			if ( false === $token_pos ) {
				return new WP_Error( 'fragment_boundary_not_found' );
			}
			$response .= substr( $serialized, $token_pos + strlen( $search ) );
		}

		return $response;
	}

	/**
	 * Filter the title for the streaming header.
	 *
	 * @since 0.2
	 * @param string $title Title.
	 * @return string Title.
	 */
	public static function filter_title_for_streaming_header( $title ) {
		if ( current_theme_supports( self::STREAM_THEME_SUPPORT ) && 'header' === get_query_var( self::STREAM_FRAGMENT_QUERY_VAR ) ) {
			$title = __( 'Loading...', 'pwa' );
		}
		return $title;
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

		$revision = sprintf( '%s-v%s', $template, wp_get_theme( $template )->Version );
		if ( $template !== $stylesheet ) {
			$revision .= sprintf( ';%s-v%s', $stylesheet, wp_get_theme( $stylesheet )->Version );
		}

		// Ensure the user-specific offline/500 pages are precached, and thet they update when user logs out or switches to another user.
		$revision .= sprintf( ';user-%d', get_current_user_id() );

		if ( ! is_admin() ) {
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
			 *
			 * @param array $entry {
			 *     Server error precache entry.
			 *
			 *     @type string $url      URL to page that shows the server error template.
			 *     @type string $revision Revision for the template. This defaults to the template and stylesheet names, with their respective theme versions.
			 * }
			 */
			$server_error_precache_entry = apply_filters( 'wp_server_error_precache_entry', $server_error_precache_entry );

		} else {
			$offline_error_precache_entry = array(
				'url'      => add_query_arg( 'code', 'offline', admin_url( 'admin-ajax.php?action=wp_error_template' ) ), // Upon core merge, this would use admin_url( 'error.php' ).
				'revision' => PWA_VERSION, // Upon core merge, this should be the core version.
			);
			$server_error_precache_entry  = array(
				'url'      => add_query_arg( 'code', '500', admin_url( 'admin-ajax.php?action=wp_error_template' ) ), // Upon core merge, this would use admin_url( 'error.php' ).
				'revision' => PWA_VERSION, // Upon core merge, this should be the core version.
			);
		}

		$scripts->register(
			'wp-navigation-routing',
			array(
				'src'  => array( $this, 'get_script' ),
				'deps' => array( 'wp-base-config' ),
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

			if ( $streaming_header_precache_entry ) {
				$scripts->precaching_routes()->register( $streaming_header_precache_entry['url'], isset( $streaming_header_precache_entry['revision'] ) ? $streaming_header_precache_entry['revision'] : null );

				add_filter( 'wp_service_worker_navigation_preload', '__return_false' ); // Navigation preload and streaming don't mix!
			}
		}

		$blacklist_patterns = array();
		if ( ! is_admin() ) {
			$blacklist_patterns[] = '^' . preg_quote( untrailingslashit( wp_parse_url( admin_url(), PHP_URL_PATH ) ), '/' ) . '($|\?.*|/.*)';
		}

		$this->replacements = array(
			'ERROR_OFFLINE_URL'                => wp_service_worker_json_encode( isset( $offline_error_precache_entry['url'] ) ? $offline_error_precache_entry['url'] : null ),
			'ERROR_OFFLINE_BODY_FRAGMENT_URL'  => wp_service_worker_json_encode( isset( $offline_error_precache_entry['url'] ) ? add_query_arg( self::STREAM_FRAGMENT_QUERY_VAR, 'body', $offline_error_precache_entry['url'] ) : null ),
			'ERROR_500_URL'                    => wp_service_worker_json_encode( isset( $server_error_precache_entry['url'] ) ? $server_error_precache_entry['url'] : null ),
			'ERROR_500_BODY_FRAGMENT_URL'      => wp_service_worker_json_encode( isset( $server_error_precache_entry['url'] ) ? add_query_arg( self::STREAM_FRAGMENT_QUERY_VAR, 'body', $server_error_precache_entry['url'] ) : null ),
			'STREAM_HEADER_FRAGMENT_URL'       => wp_service_worker_json_encode( isset( $streaming_header_precache_entry['url'] ) ? $streaming_header_precache_entry['url'] : null ),
			'BLACKLIST_PATTERNS'               => wp_service_worker_json_encode( $blacklist_patterns ),
			'SHOULD_STREAM_RESPONSE'           => wp_service_worker_json_encode( $should_stream_response ),
			'STREAM_HEADER_FRAGMENT_QUERY_VAR' => wp_service_worker_json_encode( self::STREAM_FRAGMENT_QUERY_VAR ),
		);
	}

	/**
	 * Gets the priority this component should be hooked into the service worker action with.
	 *
	 * @since 0.2
	 *
	 * @return int Hook priority. A higher number means a lower priority.
	 */
	public function get_priority() {
		return -99999;
	}

	/**
	 * Get script for routing navigation requests.
	 *
	 * @return string Script.
	 */
	public function get_script() {
		$script = file_get_contents( PWA_PLUGIN_DIR . '/wp-includes/js/service-worker-navigation-routing.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$script = preg_replace( '#/\*\s*global.+?\*/#', '', $script );

		return str_replace(
			array_keys( $this->replacements ),
			array_values( $this->replacements ),
			$script
		);
	}
}
