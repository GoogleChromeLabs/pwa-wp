<?php
/**
 * WP_Service_Workers class.
 *
 * @since 0.2
 * @package PWA
 */

/**
 * Class used to register service workers.
 *
 * @since 0.1
 *
 * @see WP_Dependencies
 */
class WP_Service_Workers implements WP_Service_Worker_Registry_Aware {

	/**
	 * Param for service workers.
	 *
	 * @var string
	 */
	const QUERY_VAR = 'wp_service_worker';

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
	 * Service worker scripts registry.
	 *
	 * @var WP_Service_Worker_Scripts
	 */
	protected $scripts;

	/**
	 * Constructor.
	 *
	 * Instantiates the service worker scripts registry.
	 */
	public function __construct() {
		$components = array(
			'configuration'      => new WP_Service_Worker_Configuration_Component(),
			'navigation_routing' => new WP_Service_Worker_Navigation_Routing_Component(),
			'precaching_routes'  => new WP_Service_Worker_Precaching_Routes_Component(),
			'caching_routes'     => new WP_Service_Worker_Caching_Routes_Component(),
		);

		$this->scripts = new WP_Service_Worker_Scripts( $components );
	}

	/**
	 * Gets the service worker scripts registry.
	 *
	 * @return WP_Service_Worker_Scripts Scripts registry instance.
	 */
	public function get_registry() {
		return $this->scripts;
	}

	/**
	 * Get the current scope for the service worker request.
	 *
	 * @todo We don't really need this. A simple call to is_admin() is all that is required.
	 * @return int Scope. Either SCOPE_FRONT or SCOPE_ADMIN.
	 */
	public function get_current_scope() {
		return is_admin() ? self::SCOPE_ADMIN : self::SCOPE_FRONT;
	}

	/**
	 * Get service worker logic for scope.
	 *
	 * @see wp_service_worker_loaded()
	 */
	public function serve_request() {
		// See wp_debug_mode() for how this is also done for REST API responses.
		@ini_set( 'display_errors', 0 ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_ini_set, WordPress.PHP.IniSet.display_errors_Blacklisted

		/*
		 * Per Workbox <https://developers.google.com/web/tools/workbox/guides/service-worker-checklist#cache-control_of_your_service_worker_file>:
		 * "Generally, most developers will want to set the Cache-Control header to no-cache,
		 * forcing browsers to always check the server for a new service worker file."
		 * Nevertheless, an ETag header is also sent with support for Conditional Requests
		 * to save on needlessly re-downloading the same service worker with each page load.
		 */
		@header( 'Cache-Control: no-cache' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged

		@header( 'Content-Type: text/javascript; charset=utf-8' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! is_admin() ) {
			wp_enqueue_scripts();

			/**
			 * Fires before serving the frontend service worker, when its scripts should be registered, caching routes established, and assets precached.
			 *
			 * @since 0.2
			 *
			 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
			 */
			do_action( 'wp_front_service_worker', $this->scripts );
		} else {
			$hook_name = 'service-worker';
			set_current_screen( $hook_name );

			/** This action is documented in wp-admin/admin-header.php */
			do_action( 'admin_enqueue_scripts', $hook_name );

			/**
			 * Fires before serving the wp-admin service worker, when its scripts should be registered, caching routes established, and assets precached.
			 *
			 * @since 0.2
			 *
			 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
			 */
			do_action( 'wp_admin_service_worker', $this->scripts );
		}

		printf( "/* PWA v%s-%s */\n\n", esc_html( PWA_VERSION ), is_admin() ? 'admin' : 'front' );

		ob_start();
		$this->scripts->do_items( array_keys( $this->scripts->registered ) );
		$output = ob_get_clean();

		$file_hash = md5( $output );
		@header( "ETag: $file_hash" ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged

		$etag_header = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( $_SERVER['HTTP_IF_NONE_MATCH'] ) : false;
		if ( $file_hash === $etag_header ) {
			status_header( 304 );
			return;
		}

		echo $output; // phpcs:ignore WordPress.XSS.EscapeOutput, WordPress.Security.EscapeOutput
	}
}
