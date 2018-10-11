<?php
/**
 * Service Worker functions.
 *
 * @since 0.1
 *
 * @package PWA
 */

/**
 * Initialize $wp_service_workers if it has not been set.
 *
 * @since 0.1
 * @global WP_Service_Workers $wp_service_workers
 * @return WP_Service_Workers WP_Service_Workers instance.
 */
function wp_service_workers() {
	global $wp_service_workers;
	if ( ! ( $wp_service_workers instanceof WP_Service_Workers ) ) {
		$wp_service_workers = new WP_Service_Workers();
	}
	return $wp_service_workers;
}

/**
 * Register a service worker script.
 *
 * @since 0.2
 *
 * @param string $handle Handle of the script.
 * @param array  $args   {
 *     Additional script arguments.
 *
 *     @type string|callable $src  Required. URL to the source in the WordPress install, or a callback that
 *                                 returns the JS to include in the service worker.
 *     @type array           $deps An array of registered item handles this item depends on. Default empty array.
 * }
 */
function wp_register_service_worker_script( $handle, $args = array() ) {
	wp_service_workers()->get_registry()->register( $handle, $args );
}

/**
 * Registers a precaching route.
 *
 * @since 0.2
 *
 * @param string $url  URL to cache.
 * @param array  $args {
 *     Additional route arguments.
 *
 *     @type string $revision Revision.
 * }
 */
function wp_register_service_worker_precaching_route( $url, $args = array() ) {
	wp_service_workers()->get_registry()->precaching_routes()->register( $url, $args );
}

/**
 * Registers a caching route.
 *
 * @since 0.2
 *
 * @param string $route Route regular expression, without delimiters.
 * @param array  $args  {
 *     Additional route arguments.
 *
 *     @type string $strategy   Required. Strategy, can be WP_Service_Worker_Caching_Routes::STRATEGY_STALE_WHILE_REVALIDATE, WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_FIRST,
 *                              WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_FIRST, WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_ONLY,
 *                              WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_ONLY.
 *     @type string $cache_name Name to use for the cache.
 *     @type array  $plugins    Array of plugins with configuration. The key of each plugin in the array must match the plugin's name.
 *                              See https://developers.google.com/web/tools/workbox/guides/using-plugins#workbox_plugins.
 * }
 */
function wp_register_service_worker_caching_route( $route, $args = array() ) {
	wp_service_workers()->get_registry()->caching_routes()->register( $route, $args );
}

/**
 * Get service worker URL by scope.
 *
 * @since 0.1
 *
 * @param int $scope Scope for which service worker to output. Can be WP_Service_Workers::SCOPE_FRONT (default) or WP_Service_Workers::SCOPE_ADMIN.
 * @return string Service Worker URL.
 */
function wp_get_service_worker_url( $scope = WP_Service_Workers::SCOPE_FRONT ) {
	if ( WP_Service_Workers::SCOPE_FRONT !== $scope && WP_Service_Workers::SCOPE_ADMIN !== $scope ) {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Scope must be either WP_Service_Workers::SCOPE_FRONT or WP_Service_Workers::SCOPE_ADMIN.', 'pwa' ), '?' );
		$scope = WP_Service_Workers::SCOPE_FRONT;
	}

	if ( WP_Service_Workers::SCOPE_FRONT === $scope ) {
		return add_query_arg(
			array( WP_Service_Workers::QUERY_VAR => $scope ),
			home_url( '/', 'https' )
		);
	} else {
		return add_query_arg(
			array( 'action' => WP_Service_Workers::QUERY_VAR ),
			admin_url( 'admin-ajax.php' )
		);
	}
}

/**
 * Print service workers' scripts that can be installed.
 *
 * @since 0.1
 */
function wp_print_service_workers() {
	global $pagenow;
	$scopes = array();

	$on_front_domain = isset( $_SERVER['HTTP_HOST'] ) && wp_parse_url( home_url(), PHP_URL_HOST ) === $_SERVER['HTTP_HOST'];
	$on_admin_domain = isset( $_SERVER['HTTP_HOST'] ) && wp_parse_url( admin_url(), PHP_URL_HOST ) === $_SERVER['HTTP_HOST'];

	// Install the front service worker if currently on the home domain.
	if ( $on_front_domain ) {
		$scopes[ WP_Service_Workers::SCOPE_FRONT ] = home_url( '/', 'relative' ); // The home_url() here will account for subdirectory installs.
	}

	// Include admin service worker if it seems it will be used (and it can be installed).
	if ( $on_admin_domain && ( is_user_logged_in() || is_admin() || in_array( $pagenow, array( 'wp-login.php', 'wp-signup.php', 'wp-activate.php' ), true ) ) ) {
		$scopes[ WP_Service_Workers::SCOPE_ADMIN ] = wp_parse_url( admin_url( '/' ), PHP_URL_PATH );
	}

	if ( empty( $scopes ) ) {
		return;
	}

	?>
	<script>
		if ( navigator.serviceWorker ) {
			window.addEventListener('load', function() {
				<?php foreach ( $scopes as $name => $scope ) { ?>
					navigator.serviceWorker.register(
						<?php echo wp_json_encode( wp_get_service_worker_url( $name ) ); ?>,
						<?php echo wp_json_encode( compact( 'scope' ) ); ?>
					).then( function() {
						document.cookie = 'wordpress_sw_installed=1; path=/; expires=Fri, 31 Dec 9999 23:59:59 GMT; secure; samesite=strict';
					} );
				<?php } ?>
			} );
		}
	</script>
	<?php
}

/**
 * Print the script that is responsible for populating the details iframe with the error info from the service worker.
 *
 * Broadcast a request to obtain the original response text from the internal server error response and display it inside
 * a details iframe if the 500 response included any body (such as an error message). This is used in a the 500.php template.
 *
 * @since 0.2
 *
 * @param string $callback Function in JS to invoke with the data. This may be either a global function name or method of another object, e.g. "mySite.handleServerError".
 */
function wp_print_service_worker_error_details_script( $callback ) {
	?>
	<script>
		{
			const clientUrl = location.href;
			const channel = new BroadcastChannel( 'wordpress-server-errors' );
			channel.onmessage = ( event ) => {
				if ( event.data && event.data.requestUrl && clientUrl === event.data.requestUrl ) {
					channel.onmessage = null;
					channel.close();

					<?php echo 'window[' . implode( '][', array_map( 'json_encode', explode( '.', $callback ) ) ) . ']( event.data );'; ?>
				}
			};
			channel.postMessage( { clientUrl } )
		}
	</script>
	<?php
}

/**
 * Serve the service worker for the frontend if requested.
 *
 * @since 0.1
 * @see rest_api_loaded()
 * @see wp_ajax_wp_service_worker()
 */
function wp_service_worker_loaded() {
	global $wp;
	if ( isset( $wp->query_vars[ WP_Service_Workers::QUERY_VAR ] ) ) {
		wp_service_workers()->serve_request();
		exit;
	}
}

/**
 * Serve admin service worker.
 *
 * This will be moved to wp-admin/includes/admin-actions.php
 *
 * @since 0.2
 * @see wp_service_worker_loaded()
 */
function wp_ajax_wp_service_worker() {
	wp_service_workers()->serve_request();
	exit;
}

/**
 * JSON-encodes with pretty printing.
 *
 * @since 0.2
 *
 * @param mixed $data Data.
 * @return string JSON.
 */
function wp_service_worker_json_encode( $data ) {
	return wp_json_encode( $data, 128 | 64 /* JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES */ );
}

/**
 * Registers all default service workers.
 *
 * @since 0.2
 *
 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
 */
function wp_default_service_workers( $scripts ) {
	$scripts->base_url        = site_url();
	$scripts->content_url     = defined( 'WP_CONTENT_URL' ) ? WP_CONTENT_URL : '';
	$scripts->default_version = get_bloginfo( 'version' );

	$integrations = array(
		'wp-site-icon'         => new WP_Service_Worker_Site_Icon_Integration(),
		'wp-custom-logo'       => new WP_Service_Worker_Custom_Logo_Integration(),
		'wp-custom-header'     => new WP_Service_Worker_Custom_Header_Integration(),
		'wp-custom-background' => new WP_Service_Worker_Custom_Background_Integration(),
		'wp-scripts'           => new WP_Service_Worker_Scripts_Integration(),
		'wp-styles'            => new WP_Service_Worker_Styles_Integration(),
		'wp-fonts'             => new WP_Service_Worker_Fonts_Integration(),
	);

	if ( ! SCRIPT_DEBUG ) {
		$integrations['wp-admin-assets'] = new WP_Service_Worker_Admin_Assets_Integration();
	}

	/**
	 * Filters the service worker integrations to initialize.
	 *
	 * @since 0.2
	 *
	 * @param array $integrations Array of $slug => $integration pairs, where $integration is an instance
	 *                            of a class that implements the WP_Service_Worker_Integration interface.
	 */
	$integrations = apply_filters( 'wp_service_worker_integrations', $integrations );

	foreach ( $integrations as $slug => $integration ) {
		if ( ! $integration instanceof WP_Service_Worker_Integration ) {
			_doing_it_wrong(
				__FUNCTION__,
				sprintf(
					/* translators: 1: integration slug, 2: interface name */
					esc_html__( 'The integration with slug %1$s does not implement the %2$s interface.', 'pwa' ),
					esc_html( $slug ),
					'WP_Service_Worker_Integration'
				),
				'0.2'
			);
			continue;
		}

		$scope    = $integration->get_scope();
		$priority = $integration->get_priority();
		switch ( $scope ) {
			case WP_Service_Workers::SCOPE_FRONT:
				add_action( 'wp_front_service_worker', array( $integration, 'register' ), $priority, 1 );
				break;
			case WP_Service_Workers::SCOPE_ADMIN:
				add_action( 'wp_admin_service_worker', array( $integration, 'register' ), $priority, 1 );
				break;
			case WP_Service_Workers::SCOPE_ALL:
				add_action( 'wp_front_service_worker', array( $integration, 'register' ), $priority, 1 );
				add_action( 'wp_admin_service_worker', array( $integration, 'register' ), $priority, 1 );
				break;
			default:
				$valid_scopes = array( WP_Service_Workers::SCOPE_FRONT, WP_Service_Workers::SCOPE_ADMIN, WP_Service_Workers::SCOPE_ALL );
				_doing_it_wrong(
					__FUNCTION__,
					sprintf(
						/* translators: 1: integration slug, 2: a comma-separated list of valid scopes */
						esc_html__( 'Scope for integration %1$s must be one out of %2$s.', 'pwa' ),
						esc_html( $slug ),
						esc_html( implode( ', ', $valid_scopes ) )
					),
					'0.1'
				);
		}
	}
}

/**
 * Disables concatenating scripts to leverage caching the assets via Service Worker instead.
 */
function wp_disable_script_concatenation() {
	global $concatenate_scripts;

	/*
	 * This cookie is set when the service worker registers successfully, avoiding unnecessary result
	 * for browsers that don't support service workers. Note that concatenation only applies in the admin,
	 * for authenticated users without full-page caching.
	*/
	if ( isset( $_COOKIE['wordpress_sw_installed'] ) ) {
		$concatenate_scripts = false; // WPCS: Override OK.
	}

	// @todo This is just here for debugging purposes.
	if ( isset( $_GET['wp_concatenate_scripts'] ) ) { // WPCS: csrf ok.
		$concatenate_scripts = rest_sanitize_boolean( $_GET['wp_concatenate_scripts'] ); // WPCS: csrf ok, override ok.
	}
}

/**
 * Prepare stream fragment response.
 *
 * @since 0.2
 * @todo Hook this up to work in non-AMP responses.
 *
 * @param DOMDocument $dom           Document.
 * @param string      $fragment_name Fragment name.
 * @return string|WP_Error Response fragment string, or WP_Error.
 */
function wp_prepare_stream_fragment_response( $dom, $fragment_name ) {
	$boundary_element = $dom->getElementById( WP_Service_Worker_Navigation_Routing_Component::STREAM_FRAGMENT_BOUNDARY_ELEMENT_ID );
	if ( ! $boundary_element ) {
		return new WP_Error( 'no_fragment_boundary' );
	}

	$boundary_comment_text = 'WP_STREAM_FRAGMENT_BOUNDARY';
	$search                = "<!--$boundary_comment_text-->";

	// Obtain header fragment.
	if ( 'header' === $fragment_name ) {
		$serialized = "<!DOCTYPE html>\n";
		$comment    = $dom->createComment( $boundary_comment_text );
		$boundary_element->parentNode->insertBefore( $comment, $boundary_element->nextSibling );
		$serialized .= AMP_DOM_Utils::get_content_from_dom_node( $dom, $dom->documentElement );
		$token_pos   = strpos( $serialized, $search );
		if ( false === $token_pos ) {
			return new WP_Error( 'fragment_boundary_not_found' );
		}
		$response  = substr( $serialized, 0, $token_pos );
		$response .= sprintf(
			'<script id="wp-stream-combine-function">%s</script>',
			file_get_contents( PWA_PLUGIN_DIR . '/wp-includes/js/service-worker-stream-combiner.js' ) // phpcs:ignore
		);
		return $response;
	}

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
	$boundary_element->parentNode->insertBefore( $dom->createComment( $boundary_comment_text ), $boundary_element );
	$boundary_element->parentNode->removeChild( $boundary_element ); // Only relevant to serve in the header fragment.
	$serialized = AMP_DOM_Utils::get_content_from_dom_node( $dom, $dom->documentElement );
	$token_pos  = strpos( $serialized, $search );
	if ( false === $token_pos ) {
		return new WP_Error( 'fragment_boundary_not_found' );
	}

	$response = sprintf(
		'<script id="wp-stream-combine-call">wpStreamCombine( %s );</script>',
		wp_json_encode( $data, JSON_PRETTY_PRINT ) // phpcs:ignore PHPCompatibility.PHP.NewConstants.json_pretty_printFound -- Defined in core.
	);

	$response .= substr( $serialized, $token_pos + strlen( $search ) );

	return $response;
}
