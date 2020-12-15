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
final class WP_Service_Worker_Scripts extends WP_Scripts {

	/**
	 * Service worker components.
	 *
	 * @since 0.2
	 * @var array
	 */
	protected $components = array();

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
	 * @since 0.2
	 *
	 * @param WP_Service_Worker_Caching_Routes    $caching_routes    Caching routes.
	 * @param WP_Service_Worker_Precaching_Routes $precaching_routes Precaching routes.
	 * @param array                               $components Optional. Service worker components as $slug => $instance pairs.
	 *                                                        Each component must implement `WP_Service_Worker_Component`.
	 *                                                        Default empty array.
	 */
	public function __construct( $caching_routes, $precaching_routes, $components = array() ) {
		$this->caching_routes    = $caching_routes;
		$this->precaching_routes = $precaching_routes;
		$this->components        = $components;
		parent::__construct();
	}

	/**
	 * Initialize the class.
	 */
	public function init() {
		foreach ( $this->components as $component ) {
			$callback = array( $component, 'serve' );
			$priority = $component->get_priority();

			add_action( 'wp_front_service_worker', $callback, $priority, 1 );
			add_action( 'wp_admin_service_worker', $callback, $priority, 1 );
		}

		/**
		 * Fires when the WP_Service_Worker_Scripts instance is initialized.
		 *
		 * @since 0.1
		 *
		 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
		 */
		do_action_ref_array( 'wp_default_service_workers', array( &$this ) );
	}

	/**
	 * Get caching routes registry.
	 *
	 * @since 0.6
	 *
	 * @return WP_Service_Worker_Caching_Routes Registry.
	 */
	public function caching_routes() {
		return $this->caching_routes;
	}

	/**
	 * Get precaching routes registry.
	 *
	 * @since 0.6
	 *
	 * @return WP_Service_Worker_Precaching_Routes Registry.
	 */
	public function precaching_routes() {
		return $this->precaching_routes;
	}

	/**
	 * Registers a service worker script.
	 *
	 * @since 0.2
	 *
	 * @param string $handle Handle of the script.
	 * @param array  $args   {
	 *     Additional script arguments.
	 *
	 *     @type string|callable $src  Required. URL to the source in the WordPress install, or a callback that
	 *                                 returns the JS to include in the service worker.
	 *     @type array           $deps An array of registered item handles this item depends on. Default empty array.
	 * }
	 */
	public function register( $handle, $args = array() ) {
		if ( ! is_array( $args ) || is_callable( $args ) ) {
			$args = array( 'src' => $args );
		}

		$args = wp_parse_args(
			$args,
			array(
				'src'  => '',
				'deps' => array(),
			)
		);

		if ( empty( $args['src'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'The src argument must be provided.', 'pwa' ),
				'0.2'
			);
			return;
		}

		parent::add( $handle, $args['src'], $args['deps'], false );
	}

	/**
	 * Gets all registered service worker scripts.
	 *
	 * @since 0.2
	 *
	 * @return array List of registered scripts.
	 */
	public function get_all() {
		return array_values( $this->registered );
	}

	/**
	 * Process one registered script.
	 *
	 * @param string $handle Handle.
	 * @param bool   $group Group. Unused.
	 * @return bool True on success, false on failure.
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
			@_doing_it_wrong( 'WP_Service_Worker_Scripts::register', esc_html( $error ), '0.1' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged -- We want the error in the PHP log, but not in the JS output.
			printf( "console.warn( %s );\n", wp_json_encode( $error ) ); // phpcs:ignore WordPress.XSS.EscapeOutput, WordPress.Security.EscapeOutput
		}

		return ! $invalid;
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
