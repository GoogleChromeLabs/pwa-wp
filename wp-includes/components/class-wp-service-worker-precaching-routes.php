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
class WP_Service_Worker_Precaching_Routes implements WP_Service_Worker_Registry {

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
	 * @since 0.2
	 */
	public function register_emoji_script() {
		$this->register(
			includes_url( 'js/wp-emoji-release.min.js' ),
			array(
				'revision' => get_bloginfo( 'version' ),
			)
		);
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
