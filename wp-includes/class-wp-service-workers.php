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
final class WP_Service_Workers {

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
	 * Caching routes.
	 *
	 * @since 0.6
	 * @var WP_Service_Worker_Caching_Routes
	 */
	protected $caching_routes;

	/**
	 * Precaching routes.
	 *
	 * @since 0.6
	 * @var WP_Service_Worker_Precaching_Routes
	 */
	protected $precaching_routes;

	/**
	 * Constructor.
	 *
	 * Instantiates the service worker scripts registry.
	 */
	public function __construct() {
		$this->precaching_routes = new WP_Service_Worker_Precaching_Routes();
		$this->caching_routes    = new WP_Service_Worker_Caching_Routes();

		$components = array(
			'configuration'      => new WP_Service_Worker_Configuration_Component(),
			'precaching_routes'  => new WP_Service_Worker_Precaching_Routes_Component( $this->precaching_routes ),
			'caching_routes'     => new WP_Service_Worker_Caching_Routes_Component( $this->caching_routes ),
			'navigation_routing' => new WP_Service_Worker_Navigation_Routing_Component(),
		);

		if ( get_option( 'offline_browsing' ) ) {
			$components = array_merge(
				$components,
				array(
					'core_asset_caching'     => new WP_Service_Worker_Core_Asset_Caching_Component(),
					'theme_asset_caching'    => new WP_Service_Worker_Theme_Asset_Caching_Component(),
					'plugin_asset_caching'   => new WP_Service_Worker_Plugin_Asset_Caching_Component(),
					'uploaded_image_caching' => new WP_Service_Worker_Uploaded_Image_Caching_Component(),
				)
			);
		}

		$this->scripts = new WP_Service_Worker_Scripts( $this->caching_routes, $this->precaching_routes, $components );
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
		/*
		 * Clear the currently-authenticated user to ensure that the service worker doesn't vary between users.
		 * Note that clearing the authenticated user in this way is in keeping with REST API requests wherein the
		 * WP_REST_Server::serve_request() method calls WP_REST_Server::check_authentication() which in turn applies
		 * the rest_authentication_errors filter which runs rest_cookie_check_errors() which is then responsible for
		 * calling wp_set_current_user( 0 ) if it was previously-determined a user was logged-in with the required
		 * nonce cookie set when wp_validate_auth_cookie() triggers one of the auth_cookie_* actions.
		 */
		wp_set_current_user( 0 );

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

		@header( 'X-Robots-Tag: noindex, follow' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged

		@header( 'Content-Type: text/javascript; charset=utf-8' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged

		ob_start(); // Start guarding against themes/plugins printing anything at wp_enqueue_scripts admin_enqueue_scripts.
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
		ob_end_clean(); // Finish guarding against themes/plugins printing anything at wp_enqueue_scripts admin_enqueue_scripts.

		ob_start();
		printf( "/* PWA v%s-%s */\n\n", esc_html( PWA_VERSION ), is_admin() ? 'admin' : 'front' );
		echo '/* ';
		printf(
			esc_js(
				/* translators: %s is the WordPress action hook */
				__( 'Note: This file is dynamically generated. To manipulate the contents of this file, use the `%s` action in WordPress.', 'pwa' )
			),
			is_admin() ? 'wp_admin_service_worker' : 'wp_front_service_worker'
		);
		echo " /*\n\n";
		$this->scripts->do_items( array_keys( $this->scripts->registered ) );
		$output = ob_get_clean();

		$file_hash = md5( $output );
		$etag      = sprintf( '"%s"', $file_hash );
		@header( "ETag: $etag" ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged

		$if_none_match = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : false;
		if ( $if_none_match === $etag ) {
			status_header( 304 );
			return;
		}

		echo $output; // phpcs:ignore WordPress.XSS.EscapeOutput, WordPress.Security.EscapeOutput
	}
}
