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
	 * WP_Service_Workers constructor.
	 */
	public function __construct() {
		parent::__construct();
		global $wp_filesystem;

		if ( ! class_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
		}

		if ( null === $wp_filesystem ) {
			WP_Filesystem();
		}
	}

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
	 * @param string $handle Name of the item. Should be unique.
	 * @param string $path   Path of the item relative to the WordPress root directory.
	 * @param array  $deps   Optional. An array of registered item handles this item depends on. Default empty array.
	 * @param bool   $ver    Always false for service worker.
	 * @param mixed  $scope  Optional. Scope of the service worker. Default relative path.
	 * @return bool Whether the item has been registered. True on success, false on failure.
	 */
	public function add( $handle, $path, $deps = array(), $ver = false, $scope = null ) {

		// Set default scope if missing.
		if ( ! $scope ) {
			$scope = site_url( '/', 'relative' );
		}

		if ( false === parent::add( $handle, $path, $deps, false, $scope ) ) {
			return false;
		}
		return true;
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
			if ( $scope === $item->args ) {
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

		// @codingStandardsIgnoreLine
		echo $this->output;
	}

	/**
	 * Get all scopes.
	 *
	 * @return array Array of scopes.
	 */
	public function get_scopes() {

		$scopes = array();
		foreach ( $this->registered as $handle => $item ) {
			$scopes[] = $item->args;
		}
		return $scopes;
	}

	/**
	 * Process one registered script.
	 *
	 * @param string $handle Handle.
	 * @param bool   $group Group.
	 * @return void
	 */
	public function do_item( $handle, $group = false ) {
		global $wp_filesystem;

		$obj           = $this->registered[ $handle ];
		$this->output .= $wp_filesystem->get_contents( $this->get_validated_file_path( $obj->src ) ) . "\n";
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
		$needs_base_url = ! preg_match( '|^(https?:)?//|', $url );
		$base_url       = site_url();

		if ( $needs_base_url ) {
			$url = $base_url . $url;
		}

		// Strip URL scheme, query, and fragment.
		$url = $this->remove_url_scheme( preg_replace( ':[\?#].*$:', '', $url ) );

		$includes_url = $this->remove_url_scheme( includes_url( '/' ) );
		$content_url  = $this->remove_url_scheme( content_url( '/' ) );
		$admin_url    = $this->remove_url_scheme( get_admin_url( null, '/' ) );

		$allowed_hosts = array(
			wp_parse_url( $includes_url, PHP_URL_HOST ),
			wp_parse_url( $content_url, PHP_URL_HOST ),
			wp_parse_url( $admin_url, PHP_URL_HOST ),
		);

		$url_host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! in_array( $url_host, $allowed_hosts, true ) ) {
			/* translators: %s is file URL */
			return new WP_Error( 'external_file_url', sprintf( __( 'URL is located on an external domain: %s.', 'pwa' ), $url_host ) );
		}

		$file_path = null;
		if ( 0 === strpos( $url, $content_url ) ) {
			$file_path = WP_CONTENT_DIR . substr( $url, strlen( $content_url ) - 1 );
		} elseif ( 0 === strpos( $url, $includes_url ) ) {
			$file_path = ABSPATH . WPINC . substr( $url, strlen( $includes_url ) - 1 );
		} elseif ( 0 === strpos( $url, $admin_url ) ) {
			$file_path = ABSPATH . 'wp-admin' . substr( $url, strlen( $admin_url ) - 1 );
		} else {
			$file_path = ABSPATH . substr( $url, strlen( $this->remove_url_scheme( $base_url ) ) );
		}

		if ( ! $file_path || false !== strpos( '../', $file_path ) || 0 !== validate_file( $file_path ) || ! file_exists( $file_path ) ) {
			/* translators: %s is file URL */
			return new WP_Error( 'file_path_not_found', sprintf( __( 'Unable to locate filesystem path for %s.', 'pwa' ), $url ) );
		}

		return $file_path;
	}
}
