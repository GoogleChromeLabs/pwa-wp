<?php
/**
 * Dependencies API: WP_Service_Worker_Scripts class
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
class WP_Service_Worker_Scripts extends WP_Scripts {

	/**
	 * Cache Registry.
	 *
	 * @var WP_Service_Worker_Cache_Registry
	 */
	public $cache_registry;

	/**
	 * Constructor.
	 *
	 * @since 0.2
	 */
	public function __construct() {
		$this->cache_registry = new WP_Service_Worker_Cache_Registry();

		parent::__construct();
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
	 * Register service worker script.
	 *
	 * Registers service worker if no item of that name already exists.
	 *
	 * @param string          $handle Name of the item. Should be unique.
	 * @param string|callable $src    URL to the source in the WordPress install, or a callback that returns the JS to include in the service worker.
	 * @param array           $deps   Optional. An array of registered item handles this item depends on. Default empty array.
	 * @return bool Whether the item has been registered. True on success, false on failure.
	 */
	public function register_script( $handle, $src, $deps = array() ) {
		return parent::add( $handle, $src, $deps, false );
	}

	/**
	 * Register service worker script (deprecated).
	 *
	 * @deprecated Use the register_script() method instead.
	 *
	 * @param string          $handle Name of the item. Should be unique.
	 * @param string|callable $src    URL to the source in the WordPress install, or a callback that returns the JS to include in the service worker.
	 * @param array           $deps   Optional. An array of registered item handles this item depends on. Default empty array.
	 * @return bool Whether the item has been registered. True on success, false on failure.
	 */
	public function register( $handle, $src, $deps = array() ) {
		_deprecated_function( __METHOD__, '0.2', __CLASS__ . '::register_script' );
		return $this->register_script( $handle, $src, $deps );
	}

	/**
	 * Register route and caching strategy (deprecated).
	 *
	 * @deprecated Use the WP_Service_Worker_Cache_Registry::register_cached_route() method instead.
	 *
	 * @param string $route    Route regular expression, without delimiters.
	 * @param string $strategy Strategy, can be WP_Service_Workers::STRATEGY_STALE_WHILE_REVALIDATE, WP_Service_Workers::STRATEGY_CACHE_FIRST,
	 *                         WP_Service_Workers::STRATEGY_NETWORK_FIRST, WP_Service_Workers::STRATEGY_CACHE_ONLY,
	 *                         WP_Service_Workers::STRATEGY_NETWORK_ONLY.
	 * @param array  $strategy_args {
	 *     An array of strategy arguments.
	 *
	 *     @type string $cache_name Cache name. Optional.
	 *     @type array  $plugins    Array of plugins with configuration. The key of each plugin in the array must match the plugin's name.
	 *                              See https://developers.google.com/web/tools/workbox/guides/using-plugins#workbox_plugins.
	 * }
	 */
	public function register_cached_route( $route, $strategy, $strategy_args = array() ) {
		_deprecated_function( __METHOD__, '0.2', 'WP_Service_Worker_Cache_Registry::register_cached_route' );
		$this->cache_registry->register_cached_route( $route, $strategy, $strategy_args );
	}

	/**
	 * Register precached route (deprecated).
	 *
	 * @deprecated Use the WP_Service_Worker_Cache_Registry::register_precached_route() method instead.
	 *
	 * If a registered route is stored in the precache cache, then it will be served with the cache-first strategy.
	 * For other routes registered with non-precached routes (e.g. runtime), you must currently also call
	 * `wp_service_workers()->register_cached_route(...)` to specify the strategy for interacting with that
	 * precached resource.
	 *
	 * @see WP_Service_Workers::register_cached_route()
	 * @link https://github.com/GoogleChrome/workbox/issues/1612
	 *
	 * @param string       $url URL to cache.
	 * @param array|string $options {
	 *     Options. Or else if not an array, then treated as revision.
	 *
	 *     @type string $revision Revision. Currently only applicable for precache. Optional.
	 *     @type string $cache    Cache. Defaults to the precache (WP_Service_Workers::PRECACHE_CACHE_NAME); the values 'precache' and 'runtime' will be replaced with the appropriately-namespaced cache names.
	 * }
	 */
	public function register_precached_route( $url, $options = array() ) {
		_deprecated_function( __METHOD__, '0.2', 'WP_Service_Worker_Cache_Registry::register_precached_route' );
		$this->cache_registry->register_precached_route( $url, $options );
	}

	/**
	 * Register routes / files for precaching.
	 *
	 * @deprecated Use WP_Service_Worker_Cache_Registry::register_precached_route() method instead.
	 *
	 * @param array $routes Routes.
	 */
	public function register_precached_routes( $routes ) {
		_deprecated_function( __METHOD__, '0.2', 'WP_Service_Worker_Cache_Registry::register_precached_route' );

		if ( ! is_array( $routes ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Routes must be an array.', 'pwa' ), '0.2' );
			return;
		}

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
	 * Process one registered script.
	 *
	 * @param string $handle Handle.
	 * @param bool   $group Group. Unused.
	 * @return void
	 */
	public function do_item( $handle, $group = false ) {
		$registered = $this->registered[ $handle ];
		$invalid    = false;

		if ( is_callable( $registered->src ) ) {
			printf( "\n/* Source %s: */\n", esc_js( $handle ) );
			echo call_user_func( $registered->src ) . "\n"; // phpcs:ignore WordPress.XSS.EscapeOutput, WordPress.Security.EscapeOutput
		} elseif ( is_string( $registered->src ) ) {
			$validated_path = $this->get_validated_file_path( $registered->src );
			if ( is_wp_error( $validated_path ) ) {
				$invalid = true;
			} else {
				/* translators: %s is file URL */
				printf( "\n/* Source %s <%s>: */\n", esc_js( $handle ), esc_js( $registered->src ) );
				echo @file_get_contents( $validated_path ) . "\n"; // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents, WordPress.XSS.EscapeOutput, WordPress.Security.EscapeOutput
			}
		} else {
			$invalid = true;
		}

		if ( $invalid ) {
			/* translators: %s is script handle */
			$error = sprintf( __( 'Service worker src is invalid for handle "%s".', 'pwa' ), $handle );
			@_doing_it_wrong( 'WP_Service_Workers::register', esc_html( $error ), '0.1' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged -- We want the error in the PHP log, but not in the JS output.
			printf( "console.warn( %s );\n", wp_service_worker_json_encode( $error ) ); // phpcs:ignore WordPress.XSS.EscapeOutput, WordPress.Security.EscapeOutput
		}
	}

	/**
	 * Get validated path to file.
	 *
	 * @param string $url Relative path.
	 * @return string|WP_Error
	 */
	public function get_validated_file_path( $url ) {
		$needs_base_url = (
			! is_bool( $url )
			&&
			! preg_match( '|^(https?:)?//|', $url )
			&&
			! ( $this->content_url && 0 === strpos( $url, $this->content_url ) )
		);
		if ( $needs_base_url ) {
			$url = $this->base_url . $url;
		}

		$url_scheme_pattern = '#^\w+:(?=//)#';

		// Strip URL scheme, query, and fragment.
		$url = preg_replace( $url_scheme_pattern, '', preg_replace( ':[\?#].*$:', '', $url ) );

		$includes_url = preg_replace( $url_scheme_pattern, '', includes_url( '/' ) );
		$content_url  = preg_replace( $url_scheme_pattern, '', content_url( '/' ) );
		$admin_url    = preg_replace( $url_scheme_pattern, '', get_admin_url( null, '/' ) );

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

		$base_path = null;
		$file_path = null;
		if ( 0 === strpos( $url, $content_url ) ) {
			$base_path = WP_CONTENT_DIR;
			$file_path = substr( $url, strlen( $content_url ) - 1 );
		} elseif ( 0 === strpos( $url, $includes_url ) ) {
			$base_path = ABSPATH . WPINC;
			$file_path = substr( $url, strlen( $includes_url ) - 1 );
		} elseif ( 0 === strpos( $url, $admin_url ) ) {
			$base_path = ABSPATH . 'wp-admin';
			$file_path = substr( $url, strlen( $admin_url ) - 1 );
		}

		if ( ! $file_path || false !== strpos( $file_path, '../' ) || false !== strpos( $file_path, '..\\' ) ) {
			/* translators: %s is file URL */
			return new WP_Error( 'file_path_not_allowed', sprintf( __( 'Disallowed URL filesystem path for %s.', 'pwa' ), $url ) );
		}
		if ( ! file_exists( $base_path . $file_path ) ) {
			/* translators: %s is file URL */
			return new WP_Error( 'file_path_not_found', sprintf( __( 'Unable to locate filesystem path for %s.', 'pwa' ), $url ) );
		}

		return $base_path . $file_path;
	}
}
