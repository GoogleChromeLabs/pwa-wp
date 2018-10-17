<?php
/**
 * WP_Service_Worker_Offline_Commenting_Component class.
 *
 * @package PWA
 */

/**
 * Class representing the service worker core component for handling offline commenting.
 *
 * @since 0.2
 */
class WP_Service_Worker_Offline_Commenting_Component implements WP_Service_Worker_Component {

	/**
	 * Internal storage for replacements to make in the offline commenting handling script.
	 *
	 * @since 0.2
	 * @var array
	 */
	protected $replacements = array();

	/**
	 * Adds the component functionality to the service worker.
	 *
	 * @since 0.2
	 *
	 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
	 */
	public function serve( WP_Service_Worker_Scripts $scripts ) {

		$scripts->register(
			'wp-offline-commenting',
			array(
				'src'  => array( $this, 'get_script' ),
				'deps' => array( 'wp-base-config', 'wp-error-response' ),
			)
		);

		if ( ! is_admin() ) {
			$offline_template_url = add_query_arg( 'wp_error_template', 'offline', home_url( '/' ) );
		} else {
			$offline_template_url = add_query_arg( 'code', 'offline', admin_url( 'admin-ajax.php?action=wp_error_template' ) ); // Upon core merge, this would use admin_url( 'error.php' ).
		}

		$this->replacements = array(
			'ERROR_OFFLINE_URL'  => wp_service_worker_json_encode( $offline_template_url ),
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
	 * Get script for handling of offline commenting.
	 *
	 * @return string Script.
	 */
	public function get_script() {
		$script = file_get_contents( PWA_PLUGIN_DIR . '/wp-includes/js/service-worker-offline-commenting.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$script = preg_replace( '#/\*\s*global.+?\*/#', '', $script );

		return str_replace(
			array_keys( $this->replacements ),
			array_values( $this->replacements ),
			$script
		);
	}
}
