<?php
/**
 * Dependencies API: WP_Service_Workers class
 *
 * @since ?
 *
 * @package PWA
 */

/**
 * Class used to register service workers.
 *
 * @since ?
 *
 * @see WP_Dependencies
 */
class WP_Service_Workers extends WP_Scripts {

	/**
	 * Param for service workers.
	 *
	 * @var string
	 */
	public $query_var = 'wp_service_worker';

	/**
	 * Output for service worker scope script.
	 *
	 * @var string
	 */
	public $output = '';

	/**
	 * Initialize the class.
	 */
	public function init() {
		/**
		 * Fires when the WP_Service_Workers instance is initialized.
		 *
		 * @param WP_Service_Workers $this WP_Service_Workers instance (passed by reference).
		 */
		do_action_ref_array( 'wp_default_service_workers', array( &$this ) );
	}

	/**
	 * Register service worker.
	 *
	 * Registers service worker if no item of that name already exists.
	 *
	 * @param string          $handle Name of the item. Should be unique.
	 * @param string|callable $src    URL to the source in the WordPress install, or a callback that returns the JS to include in the service worker.
	 * @param array           $deps   Optional. An array of registered item handles this item depends on. Default empty array.
	 * @param array           $scopes Optional. Scopes of the service worker. Default relative path.
	 * @return bool Whether the item has been registered. True on success, false on failure.
	 */
	public function register( $handle, $src, $deps = array(), $scopes = array() ) {

		// Set default scope if missing.
		if ( empty( $scopes ) ) {
			$scopes = array( site_url( '/', 'relative' ) );
		}
		return parent::add( $handle, $src, $deps, false, compact( 'scopes' ) );
	}

	/**
	 * Get service worker logic for scope.
	 *
	 * @param string $scope Scope of the Service Worker.
	 */
	public function serve_request( $scope ) {

		header( 'Content-Type: text/javascript; charset=utf-8' );
		nocache_headers();

		if ( ! in_array( $scope, $this->get_scopes(), true ) ) {
			status_header( 404 );
			return;
		}

		$scope_items = array();

		// Get handles from the relevant scope only.
		foreach ( $this->registered as $handle => $item ) {
			if ( in_array( $scope, $item->args['scopes'], true ) ) {
				$scope_items[] = $handle;
			}
		}

		$this->output = '';
		$this->do_items( $scope_items );

		$file_hash = md5( $this->output );
		header( "Etag: $file_hash" );

		$etag_header = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( $_SERVER['HTTP_IF_NONE_MATCH'] ) : false;
		if ( $file_hash === $etag_header ) {
			status_header( 304 );
			return;
		}

		echo $this->output; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get all scopes.
	 *
	 * @return array Array of scopes.
	 */
	public function get_scopes() {

		$scopes = array();
		foreach ( $this->registered as $handle => $item ) {
			$scopes = array_merge( $scopes, $item->args['scopes'] );
		}
		return array_unique( $scopes );
	}

	/**
	 * Process one registered script.
	 *
	 * @param string $handle Handle.
	 * @param bool   $group Group.
	 * @return void
	 */
	public function do_item( $handle, $group = false ) {

		$obj = $this->registered[ $handle ];

		if ( is_callable( $obj->src ) ) {
			$this->output .= call_user_func( $obj->src ) . "\n";
		} else {
			$validated_path = $this->get_validated_file_path( $obj->src );
			if ( is_wp_error( $validated_path ) ) {
				_doing_it_wrong(
					__FUNCTION__,
					/* translators: %s is file URL */
					sprintf( esc_html__( 'Service worker src is incorrect: %s', 'pwa' ), esc_html( $obj->src ) ),
					'0.1'
				);

				/* translators: %s is file URL */
				$this->output .= "console.warn( '" . sprintf( esc_html__( 'Service worker src is incorrect: %s', 'pwa' ), esc_html( $obj->src ) ) . "' );\n";
			} else {
				/* translators: %s is file URL */
				$this->output .= sprintf( esc_html( "\n/* Source: %s */\n" ), esc_url( $obj->src ) );

				// @codingStandardsIgnoreLine
				$this->output .= @file_get_contents( $this->get_validated_file_path( $obj->src ) ) . "\n";
			}
		}
	}

	/**
	 * Remove URL scheme.
	 *
	 * @param string $schemed_url URL.
	 * @return string URL.
	 */
	public function remove_url_scheme( $schemed_url ) {
		return preg_replace( '#^\w+:(?=//)#', '', $schemed_url );
	}

	/**
	 * Get validated path to file.
	 *
	 * @param string $url Relative path.
	 * @return null|string|WP_Error
	 */
	public function get_validated_file_path( $url ) {
		if ( ! is_string( $url ) ) {
			return new WP_Error( 'incorrect_path_format', esc_html__( 'URL has to be a string', 'pwa' ) );
		}

		$needs_base_url = ! preg_match( '|^(https?:)?//|', $url );
		$base_url       = site_url();

		if ( $needs_base_url ) {
			$url = $base_url . $url;
		}

		// Strip URL scheme, query, and fragment.
		$url = $this->remove_url_scheme( preg_replace( ':[\?#].*$:', '', $url ) );

		$content_url  = $this->remove_url_scheme( content_url( '/' ) );
		$allowed_host = wp_parse_url( $content_url, PHP_URL_HOST );

		$url_host = wp_parse_url( $url, PHP_URL_HOST );

		if ( $allowed_host !== $url_host ) {
			/* translators: %s is file URL */
			return new WP_Error( 'external_file_url', sprintf( __( 'URL is located on an external domain: %s.', 'pwa' ), $url_host ) );
		}

		$file_path = null;
		if ( 0 === strpos( $url, $content_url ) ) {
			$file_path = WP_CONTENT_DIR . substr( $url, strlen( $content_url ) - 1 );
		}

		if ( ! $file_path || false !== strpos( '../', $file_path ) || 0 !== validate_file( $file_path ) || ! file_exists( $file_path ) ) {
			/* translators: %s is file URL */
			return new WP_Error( 'file_path_not_found', sprintf( __( 'Unable to locate filesystem path for %s.', 'pwa' ), $url ) );
		}

		return $file_path;
	}
}
