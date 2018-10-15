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
class WP_Service_Worker_Configuration_Component implements WP_Service_Worker_Component {

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
		$workbox_dir   = 'wp-includes/js/workbox-v3.6.1/';

		$script = sprintf(
			"importScripts( %s );\n",
			wp_service_worker_json_encode( PWA_PLUGIN_URL . $workbox_dir . 'workbox-sw.js' )
		);

		$options = array(
			'debug'            => WP_DEBUG,
			'modulePathPrefix' => PWA_PLUGIN_URL . $workbox_dir,
		);
		$script .= sprintf( "workbox.setConfig( %s );\n", wp_service_worker_json_encode( $options ) );

		$cache_name_details = array(
			// Vary the prefix by the root directory of the site to ensure multisite subdirectory installs don't pollute each other's caches.
			'prefix'   => sprintf(
				'wp-%s',
				wp_parse_url( WP_Service_Workers::SCOPE_FRONT === $current_scope ? home_url( '/' ) : site_url( '/' ), PHP_URL_PATH )
			),
			// Also precache name by scope (front vs admin) so that different assets can be precached in each respective application.
			'precache' => sprintf( 'precache-%s', WP_Service_Workers::SCOPE_FRONT === $current_scope ? 'front' : 'admin' ),
			'suffix'   => 'v1',
		);

		$script .= sprintf( "workbox.core.setCacheNameDetails( %s );\n", wp_service_worker_json_encode( $cache_name_details ) );

		$skip_waiting = wp_service_worker_skip_waiting();

		/**
		 * Filters whether the service worker should use clientsClaim() after skipWaiting().
		 * Using clientsClaim() ensures that all uncontrolled clients (i.e. pages) that are
		 * within scope will be controlled by a service worker immediately after that service worker activates.
		 * Without enabling it, they won't be controlled until the next navigation.
		 *
		 * For optioning in for .clientsClaim(), you could do:
		 *
		 *     add_filter( 'wp_service_worker_clients_claim', '__return_true' );
		 *
		 * @param bool $clients_claim Whether to run clientsClaim() after skipWaiting().
		 */
		$clients_claim = apply_filters( 'wp_service_worker_clients_claim', false );

		if ( true === $skip_waiting ) {
			$script .= "workbox.skipWaiting();\n";

			if ( true === $clients_claim ) {
				$script .= "workbox.clientsClaim();\n";
			}
		}

		/**
		 * Filters whether navigation preload is enabled.
		 *
		 * The filtered value will be sent as the Service-Worker-Navigation-Preload header value if a truthy string.
		 * This filter should be set to return false to disable navigation preload such as when a site is using
		 * the app shell model. Take care of the current scope when setting this, as it is unlikely that the admin
		 * should have navigation preload disabled until core has an admin single-page app. To disable navigation preload on
		 * the frontend only, you may do:
		 *
		 *     add_filter( 'wp_front_service_worker', function() {
		 *         add_filter( 'wp_service_worker_navigation_preload', '__return_false' );
		 *     } );
		 *
		 * Alternatively, you should check the `$current_scope` for example:
		 *
		 *     add_filter( 'wp_service_worker_navigation_preload', function( $preload, $current_scope ) {
		 *         if ( WP_Service_Workers::SCOPE_FRONT === $current_scope ) {
		 *             $preload = false;
		 *         }
		 *         return $preload;
		 *     }, 10, 2 );
		 *
		 * @param bool|string $navigation_preload Whether to use navigation preload. Returning a string will cause it it to populate the Service-Worker-Navigation-Preload header.
		 * @param int         $current_scope      The current scope. Either 1 (WP_Service_Workers::SCOPE_FRONT) or 2 (WP_Service_Workers::SCOPE_ADMIN).
		 */
		$navigation_preload = apply_filters( 'wp_service_worker_navigation_preload', true, $current_scope );
		if ( false !== $navigation_preload ) {
			if ( is_string( $navigation_preload ) ) {
				$script .= sprintf( "workbox.navigationPreload.enable( %s );\n", wp_service_worker_json_encode( $navigation_preload ) );
			} else {
				$script .= "workbox.navigationPreload.enable();\n";
			}
		} else {
			$script .= "workbox.navigationPreload.disable();\n";
		}

		// Note: This includes the aliasing of `workbox` to `wp.serviceWorker`.
		$script .= file_get_contents( PWA_PLUGIN_DIR . '/wp-includes/js/service-worker.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return $script;
	}
}
