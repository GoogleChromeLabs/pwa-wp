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
			'configuration'     => new WP_Service_Worker_Configuration_Component(),
			'error_response'    => new WP_Service_Worker_Error_Response_Component(),
			'precaching_routes' => new WP_Service_Worker_Precaching_Routes_Component(),
			'caching_routes'    => new WP_Service_Worker_Caching_Routes_Component(),
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
	 * @global WP $wp
	 *
	 * @return int Scope. Either SCOPE_FRONT, SCOPE_ADMIN, or if neither then 0.
	 */
	public function get_current_scope() {
		global $wp;
		if ( ! isset( $wp->query_vars[ self::QUERY_VAR ] ) || ! is_numeric( $wp->query_vars[ self::QUERY_VAR ] ) ) {
			return 0;
		}
		$scope = (int) $wp->query_vars[ self::QUERY_VAR ];
		if ( self::SCOPE_FRONT === $scope ) {
			return self::SCOPE_FRONT;
		} elseif ( self::SCOPE_ADMIN === $scope ) {
			return self::SCOPE_ADMIN;
		}
		return 0;
	}

	/**
	 * Add precaching for wp-admin and wp-includes .js and .css files.
	 */
	public function precache_admin_assets() {

		// Don't continue if debug mode.
		if ( SCRIPT_DEBUG ) {
			return;
		}

		if ( ! function_exists( 'list_files' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$admin_dir    = ABSPATH . 'wp-admin/';
		$admin_images = list_files( $admin_dir . 'images/' );
		$inc_images   = list_files( ABSPATH . WPINC . '/images/' );

		// This needs to be done for $_wp_admin_css_colors to be set.
		register_admin_color_schemes();

		$this->flag_admin_assets_with_precache( wp_scripts()->registered, 'script' );
		$this->flag_admin_assets_with_precache( wp_styles()->registered, 'style' );

		$routes = array_merge(
			$this->get_routes_from_file_list( $admin_images, 'wp-admin' ),
			$this->get_routes_from_file_list( $inc_images, 'wp-includes' ),
			$this->get_woff_file_list()
		);

		foreach ( $routes as $options ) {
			$url = '';
			if ( isset( $options['url'] ) ) {
				$url = $options['url'];
				unset( $options['url'] );
			}

			$this->cache_registry->register_precached_route( $url, $options );
		}
	}

	/**
	 * Flags admin assets with precache.
	 *
	 * @param array  $dependencies Array of _WP_Dependency objects.
	 * @param string $type Type: 'script' or 'style'.
	 * @return array Array of routes.
	 */
	protected function flag_admin_assets_with_precache( $dependencies, $type ) {
		$routes = array();
		foreach ( $dependencies as $handle => $params ) {

			// Only precache scripts from wp-admin and wp-includes.
			if ( false === strpos( $params->src, 'wp-admin' ) && false === strpos( $params->src, 'wp-includes' ) ) {
				continue;
			}
			if ( 'script' === $type ) {
				wp_script_add_data( $handle, 'precache', true );
			} else {
				wp_style_add_data( $handle, 'precache', true );
			}
		}
		return $routes;
	}

	/**
	 * Get static list of .woff files to precache.
	 *
	 * @return array
	 */
	protected function get_woff_file_list() {
		return array(
			array(
				'revision' => get_bloginfo( 'version' ),
				'url'      => '/wp-includes/fonts/dashicons.woff',
			),
			array(
				'revision' => get_bloginfo( 'version' ),
				'url'      => '/wp-includes/js/tinymce/skins/lightgray/fonts/tinymce-small.woff',
			),
			array(
				'revision' => get_bloginfo( 'version' ),
				'url'      => '/wp-includes/js/tinymce/skins/lightgray/fonts/tinymce.woff',
			),
		);
	}

	/**
	 * Get routes from file paths list.
	 *
	 * @param array  $list List of file paths.
	 * @param string $folder Folder -- either 'wp-admin' or 'wp-includes'.
	 * @return array List of routes.
	 */
	protected function get_routes_from_file_list( $list, $folder ) {
		$routes = array();
		foreach ( $list as $filename ) {
			$ext = pathinfo( $filename, PATHINFO_EXTENSION );
			if ( ! in_array( $ext, array( 'png', 'gif', 'svg' ), true ) ) {
				continue;
			}

			$routes[] = array(
				'url'      => strstr( $filename, '/' . $folder ),
				'revision' => get_bloginfo( 'version' ),
			);
		}

		return $routes;
	}

	/**
	 * Get service worker logic for scope.
	 *
	 * @see wp_service_worker_loaded()
	 * @param int $scope Scope of the Service Worker.
	 */
	public function serve_request( $scope ) {
		/*
		 * Per Workbox <https://developers.google.com/web/tools/workbox/guides/service-worker-checklist#cache-control_of_your_service_worker_file>:
		 * "Generally, most developers will want to set the Cache-Control header to no-cache,
		 * forcing browsers to always check the server for a new service worker file."
		 * Nevertheless, an ETag header is also sent with support for Conditional Requests
		 * to save on needlessly re-downloading the same service worker with each page load.
		 */
		@header( 'Cache-Control: no-cache' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged

		@header( 'Content-Type: text/javascript; charset=utf-8' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged

		if ( self::SCOPE_FRONT === $scope ) {
			wp_enqueue_scripts();

			/**
			 * Fires before serving the frontend service worker, when its scripts should be registered, caching routes established, and assets precached.
			 *
			 * The following integrations are hooked into this action by default: 'wp-site-icon', 'wp-custom-logo', 'wp-custom-header', 'wp-custom-background',
			 * 'wp-scripts', 'wp-styles', and 'wp-fonts'. This default behavior can be disabled with code such as the following, for disabling the
			 * 'wp-custom-header' integration:
			 *
			 *     add_filter( 'wp_service_worker_integrations', function( $integrations ) {
			 *         unset( $integrations['wp-custom-header'] );
			 *         return $integrations;
			 *     } );
			 *
			 * @since 0.2
			 *
			 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
			 */
			do_action( 'wp_front_service_worker', $this->scripts );
		} elseif ( self::SCOPE_ADMIN === $scope ) {
			/**
			 * Fires before serving the wp-admin service worker, when its scripts should be registered, caching routes established, and assets precached.
			 *
			 * @since 0.2
			 *
			 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
			 */
			do_action( 'wp_admin_service_worker', $this->scripts );
		}

		if ( self::SCOPE_FRONT !== $scope && self::SCOPE_ADMIN !== $scope ) {
			status_header( 400 );
			echo '/* invalid_scope_requested */';
			return;
		}

		printf( "/* PWA v%s */\n\n", esc_html( PWA_VERSION ) );

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
