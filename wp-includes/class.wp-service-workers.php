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
 * @todo Does it make sense to extend WP_Scripts, maybe WP_Dependencies is enough?
 */
class WP_Service_Workers extends WP_Scripts {

	/**
	 * Param for service workers.
	 *
	 * @var string
	 */
	public $query_var = 'wp_service_worker';

	/**
	 * Array of scopes.
	 *
	 * @var array
	 */
	public $scopes = array();

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

		if ( ! isset( $this->scopes[ $scope ] ) ) {
			$this->scopes[ $scope ] = true;
		}
		return true;
	}

	/**
	 * Get service worker logic for scope.
	 *
	 * @param string $scope Scope of the Service Worker.
	 */
	public function do_service_worker( $scope ) {

		header( 'Content-Type: text/javascript; charset=utf-8' );
		header( 'Cache-Control: no-cache' );

		if ( ! isset( $this->scopes[ $scope ] ) ) {
			echo '';
			exit;
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
			header( 'HTTP/1.1 304 Not Modified' );
			exit;
		}

		// @codingStandardsIgnoreLine
		echo $this->output;
		exit;
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
		$this->output .= $wp_filesystem->get_contents( site_url() . $obj->src ) . '
';
	}
}
