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
	 * Internal storage for replacements to make in the error response handling script.
	 *
	 * @since 0.2
	 * @var array
	 */
	protected $replacements = array();

	/**
	 * Adds the component functionality to the service worker.
	 *
	 * @todo This is all copied from navigation routing component and duplication should be removed.
	 *
	 * @since 0.2
	 *
	 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
	 */
	public function serve( WP_Service_Worker_Scripts $scripts ) {
		$template   = get_template();
		$stylesheet = get_stylesheet();

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
			'wp-offline-commenting',
			array(
				'src'  => array( $this, 'get_script' ),
				'deps' => array( 'wp-base-config' ),
			)
		);

		if ( $offline_error_precache_entry ) {
			$scripts->precaching_routes()->register( $offline_error_precache_entry['url'], isset( $offline_error_precache_entry['revision'] ) ? $offline_error_precache_entry['revision'] : null );
		}
		if ( $server_error_precache_entry ) {
			$scripts->precaching_routes()->register( $server_error_precache_entry['url'], isset( $server_error_precache_entry['revision'] ) ? $server_error_precache_entry['revision'] : null );
		}

		$this->replacements = array(
			'ERROR_OFFLINE_URL' => wp_service_worker_json_encode( isset( $offline_error_precache_entry['url'] ) ? $offline_error_precache_entry['url'] : null ),
			'ERROR_500_URL'     => wp_service_worker_json_encode( isset( $server_error_precache_entry['url'] ) ? $server_error_precache_entry['url'] : null ),
			'ERROR_MESSAGES'    => wp_service_worker_json_encode( wp_service_worker_get_error_messages() ),
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
		return 10;
	}

	/**
	 * Get script for routing navigation requests.
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
