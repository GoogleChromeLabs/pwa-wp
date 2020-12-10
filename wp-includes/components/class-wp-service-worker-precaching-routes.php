<?php
/**
 * WP_Service_Worker_Precaching_Routes class.
 *
 * @package PWA
 */

/**
 * Class representing a registry for precaching routes.
 *
 * @since 0.2
 */
final class WP_Service_Worker_Precaching_Routes {

	/**
	 * Registered caching routes.
	 *
	 * @since 0.2
	 * @var array
	 */
	protected $routes = array();

	/**
	 * Registers a route.
	 *
	 * @since 0.2
	 *
	 * @param string $url  URL to cache.
	 * @param array  $args {
	 *     Additional route arguments.
	 *
	 *     @type string $revision Revision.
	 * }
	 */
	public function register( $url, $args = array() ) {
		if ( empty( $url ) || false === wp_parse_url( $url ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Invalid URL provided for precaching.', 'pwa' ), '0.4.1' );
			return;
		}

		if ( ! is_array( $args ) ) {
			$args = array(
				'revision' => $args,
			);
		}

		$this->routes[] = array(
			'url'      => $url,
			'revision' => ! empty( $args['revision'] ) ? $args['revision'] : null,
		);
	}

	/**
	 * Register Emoji script.
	 *
	 * Short-circuit if SCRIPT_DEBUG (hence file not built) or print_emoji_detection_script has been removed.
	 *
	 * @since 0.2
	 *
	 * @return bool Whether emoji script was registered.
	 */
	public function register_emoji_script() {
		if ( SCRIPT_DEBUG || false === has_action( is_admin() ? 'admin_print_scripts' : 'wp_head', 'print_emoji_detection_script' ) ) {
			return false;
		}

		$this->register(
			includes_url( 'js/wp-emoji-release.min.js' ),
			array(
				'revision' => get_bloginfo( 'version' ),
			)
		);
		return true;
	}

	/**
	 * Gets all registered routes.
	 *
	 * @since 0.2
	 *
	 * @return array List of registered routes.
	 */
	public function get_all() {
		return $this->routes;
	}
}
