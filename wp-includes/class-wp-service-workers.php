<?php
/**
 * Dependencies API: WP_Service_Workers class
 *
 * @since 0.1
 *
 * @package PWA
 */

/**
 * Class used to register service workers.
 *
 * @since 0.1
 *
 * @see WP_Dependencies
 */
class WP_Service_Workers extends WP_Scripts {

	/**
	 * Scope for front.
	 *
	 * @var int
	 */
	const SCOPE_FRONT = 1;

	/**
	 * Scope for admin.
	 *
	 * @var int
	 */
	const SCOPE_ADMIN = 2;

	/**
	 * Scope for both front and admin.
	 *
	 * @var int
	 */
	const SCOPE_ALL = 3;

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
	 * @param int             $scope  Scope for which service worker the script will be part of. Can be WP_Service_Workers::SCOPE_FRONT, WP_Service_Workers::SCOPE_ADMIN, or WP_Service_Workers::SCOPE_ALL. Default to WP_Service_Workers::SCOPE_ALL.
	 * @return bool Whether the item has been registered. True on success, false on failure.
	 */
	public function register( $handle, $src, $deps = array(), $scope = self::SCOPE_ALL ) {
		if ( ! in_array( $scope, array( self::SCOPE_FRONT, self::SCOPE_ADMIN, self::SCOPE_ALL ), true ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Scope must be either WP_Service_Workers::SCOPE_ALL, WP_Service_Workers::SCOPE_FRONT, or WP_Service_Workers::SCOPE_ADMIN.', 'pwa' ), '0.1' );
			$scope = self::SCOPE_ALL;
		}

		return parent::add( $handle, $src, $deps, false, compact( 'scope' ) );
	}

	/**
	 * Get service worker logic for scope.
	 *
	 * @see wp_service_worker_loaded()
	 * @param int $scope Scope of the Service Worker.
	 */
	public function serve_request( $scope ) {
		@header( 'Content-Type: text/javascript; charset=utf-8' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged

		if ( self::SCOPE_FRONT !== $scope && self::SCOPE_ADMIN !== $scope ) {
			status_header( 400 );
			echo '/* invalid_scope_requested */';
			return;
		}

		// @todo If $scope is admin should this admin_enqueue_scripts, and if front should it wp_enqueue_scripts?
		$scope_items = array();

		// Get handles from the relevant scope only.
		foreach ( $this->registered as $handle => $item ) {
			if ( $item->args['scope'] & $scope ) { // Yes, Bitwise AND intended. SCOPE_ALL & SCOPE_FRONT == true. SCOPE_ADMIN & SCOPE_FRONT == false.
				$scope_items[] = $handle;
			}
		}

		$this->output = '';
		$this->do_items( $scope_items );

		$file_hash = md5( $this->output );
		@header( "Etag: $file_hash" ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged

		$etag_header = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( $_SERVER['HTTP_IF_NONE_MATCH'] ) : false;
		if ( $file_hash === $etag_header ) {
			status_header( 304 );
			return;
		}

		echo $this->output; // phpcs:ignore WordPress.XSS.EscapeOutput, WordPress.Security.EscapeOutput
	}

	/**
	 * Process one registered script.
	 *
	 * @param string $handle Handle.
	 * @param bool   $group Group. Unused.
	 * @return void
	 */
	public function do_item( $handle, $group = false ) {
		$registered = $this->registered[ $handle ];
		$invalid    = false;

		if ( is_callable( $registered->src ) ) {
			$this->output .= sprintf( "\n/* Source %s: */\n", $handle );
			$this->output .= call_user_func( $registered->src ) . "\n";
		} elseif ( is_string( $registered->src ) ) {
			$validated_path = $this->get_validated_file_path( $registered->src );
			if ( is_wp_error( $validated_path ) ) {
				$invalid = true;
			} else {
				/* translators: %s is file URL */
				$this->output .= sprintf( "\n/* Source %s <%s>: */\n", $handle, $registered->src );
				$this->output .= @file_get_contents( $validated_path ) . "\n"; // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
			}
		} else {
			$invalid = true;
		}

		if ( $invalid ) {
			/* translators: %s is script handle */
			$error = sprintf( __( 'Service worker src is invalid for handle "%s".', 'pwa' ), $handle );
			_doing_it_wrong( 'WP_Service_Workers::register', esc_html( $error ), '0.1' );
			$this->output .= sprintf( "console.warn( %s );\n", wp_json_encode( $error ) );
		}
	}

	/**
	 * Remove URL scheme.
	 *
	 * @param string $schemed_url URL.
	 * @return string URL.
	 */
	protected function remove_url_scheme( $schemed_url ) {
		return preg_replace( '#^\w+:(?=//)#', '', $schemed_url );
	}

	/**
	 * Get validated path to file.
	 *
	 * @param string $url Relative path.
	 * @return null|string|WP_Error
	 */
	protected function get_validated_file_path( $url ) {
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
