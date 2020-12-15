<?php
/**
 * WP_Service_Worker_Configuration_Component class.
 *
 * @package PWA
 */

/**
 * Class representing the service worker core component for defining the base configuration.
 *
 * @since 0.2
 */
final class WP_Service_Worker_Configuration_Component implements WP_Service_Worker_Component {

	/**
	 * Adds the component functionality to the service worker.
	 *
	 * @since 0.2
	 *
	 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
	 */
	public function serve( WP_Service_Worker_Scripts $scripts ) {
		$scripts->register(
			'wp-base-config',
			array(
				'src' => array( $this, 'get_script' ),
			)
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
		return -999999;
	}

	/**
	 * Get base script for service worker.
	 *
	 * This involves the loading and configuring Workbox. However, the `workbox` global should not be directly
	 * interacted with. Instead, developers should interface with `wp.serviceWorker` which is a wrapper around
	 * the Workbox library.
	 *
	 * @link https://github.com/GoogleChrome/workbox
	 *
	 * @return string Script.
	 */
	public function get_script() {
		$current_scope = wp_service_workers()->get_current_scope();
		$workbox_dir   = sprintf( 'wp-includes/js/workbox-v%s/', PWA_WORKBOX_VERSION );

		$script = '';
		if ( SCRIPT_DEBUG ) {
			$enable_debug_log = defined( 'WP_SERVICE_WORKER_DEBUG_LOG' ) && WP_SERVICE_WORKER_DEBUG_LOG;
			if ( ! $enable_debug_log ) {
				$script .= "self.__WB_DISABLE_DEV_LOGS = true;\n";
			}

			// Load with importScripts() so that source map is available.
			$script .= sprintf(
				"importScripts( %s );\n",
				wp_json_encode( PWA_PLUGIN_URL . $workbox_dir . 'workbox-sw.js' )
			);
		} else {
			// Inline the workbox-sw.js to avoid an additional HTTP request.
			$wbjs    = file_get_contents( PWA_PLUGIN_DIR . '/' . $workbox_dir . 'workbox-sw.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$script .= preg_replace( '://# sourceMappingURL=.+?\.map\s*$:s', '', $wbjs );
		}

		$options = array(
			'debug'            => SCRIPT_DEBUG, // When true, the dev builds are loaded. Otherwise, the prod builds are used.
			'modulePathPrefix' => PWA_PLUGIN_URL . $workbox_dir,
		);
		$script .= sprintf( "workbox.setConfig( %s );\n", wp_json_encode( $options ) );

		// Vary the prefix by the root directory of the site to ensure multisite subdirectory installs don't pollute each other's caches.
		$prefix = sprintf(
			'wp-%s',
			wp_parse_url( WP_Service_Workers::SCOPE_FRONT === $current_scope ? home_url( '/' ) : site_url( '/' ), PHP_URL_PATH )
		);

		$cache_name_details = array(
			'prefix'   => $prefix,
			// Also precache name by scope (front vs admin) so that different assets can be precached in each respective application.
			'precache' => sprintf( 'precache-%s', WP_Service_Workers::SCOPE_FRONT === $current_scope ? 'front' : 'admin' ),
			'suffix'   => 'v1',
		);

		$script .= sprintf( "workbox.core.setCacheNameDetails( %s );\n", wp_json_encode( $cache_name_details ) );

		$skip_waiting = wp_service_worker_skip_waiting();

		/**
		 * Filters whether the service worker should use clientsClaim() after skipWaiting().
		 *
		 * Using clientsClaim() ensures that all uncontrolled clients (i.e. pages) that are
		 * within scope will be controlled by a service worker immediately after that service worker activates.
		 * Without enabling it, they won't be controlled until the next navigation.
		 *
		 * For opting-out of client claiming, the following code may be used:
		 *
		 *     add_filter( 'wp_service_worker_clients_claim', '__return_false' );
		 *
		 * @since 0.2
		 * @since 0.4.1 Enabled by default.
		 *
		 * @param bool $clients_claim Whether to run clientsClaim() after skipWaiting(). Defaults to true.
		 */
		$clients_claim = apply_filters( 'wp_service_worker_clients_claim', true );

		if ( true === $skip_waiting ) {
			$script .= "workbox.core.skipWaiting();\n";

			if ( true === $clients_claim ) {
				$script .= "workbox.core.clientsClaim();\n";
			}
		}

		// Note: This includes the aliasing of `workbox` to `wp.serviceWorker`.
		$script .= file_get_contents( PWA_PLUGIN_DIR . '/wp-includes/js/service-worker.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return $script;
	}
}
