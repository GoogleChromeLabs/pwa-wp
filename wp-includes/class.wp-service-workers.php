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
 * @todo Does it make sense to extend WP_Scripts?
 */
class WP_Service_Workers extends WP_Scripts {

	/**
	 * Array of scopes.
	 *
	 * @var array
	 */
	public $scopes = array();

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
		if ( false === parent::add( $handle, $path, $deps, false, $scope ) ) {
			return false;
		}

		// @todo Check later if registering scopes this way makes sense.
		if ( ! isset( $this->scopes[ $scope ] ) ) {
			$this->scopes[ $scope ] = array( $path );
		} else if ( ! in_array( $path, $this->scopes, true ) ) {
			$this->scopes[ $scope ][] = $path;
		}
		return true;
	}

	/**
	 * Get service worker logic for scope.
	 *
	 * @param string $scope Scope of the Service Worker.
	 */
	public function do_service_worker( $scope ) {

		// @todo Consider deps.
		header( 'Content-Type: text/javascript; charset=utf-8' );
		$output = '';
		if ( ! isset( $this->scopes[ $scope ] ) ) {
			echo $output;
			exit;
		}

		// @todo Is there a better way?
		foreach ( $this->scopes[ $scope ] as $path ) {
			$output .= @file_get_contents( site_url() . $path ) .'
';
		}
		echo $output;
		exit;
	}
}
