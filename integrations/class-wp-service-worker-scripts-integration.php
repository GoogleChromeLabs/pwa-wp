<?php
/**
 * WP_Service_Worker_Scripts_Integration class.
 *
 * @package PWA
 */

/**
 * Class representing the Scripts service worker integration.
 *
 * @since 0.2
 */
final class WP_Service_Worker_Scripts_Integration extends WP_Service_Worker_Base_Integration {

	/**
	 * Script handles to manage.
	 *
	 * @since 0.2
	 * @var array
	 */
	protected $handles = array();

	/**
	 * Constructor.
	 *
	 * @since 0.2
	 *
	 * @param array $handles Script handles to manage.
	 */
	public function __construct( array $handles = array() ) {
		$this->handles = $handles;

		parent::__construct();
	}

	/**
	 * Registers the integration functionality.
	 *
	 * @since 0.2
	 *
	 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
	 */
	public function register( WP_Service_Worker_Scripts $scripts ) {
		$handles = $this->handles;

		if ( empty( $handles ) ) {
			$handles = array();
			foreach ( wp_scripts()->registered as $handle => $dependency ) {
				if ( ! empty( $dependency->extra['precache'] ) ) {
					$handles[] = $handle;
				}
			}
		}

		$original_to_do = wp_scripts()->to_do;
		wp_scripts()->all_deps( $handles );
		foreach ( wp_scripts()->to_do as $handle ) {
			if ( ! isset( wp_scripts()->registered[ $handle ] ) ) {
				continue;
			}

			$dependency = wp_scripts()->registered[ $handle ];

			// Skip bundles.
			if ( ! $dependency->src ) {
				continue;
			}

			$url = $dependency->src;

			if ( is_string( $url ) && ! preg_match( '|^(https?:)?//|', $url ) && ! ( wp_scripts()->content_url && 0 === strpos( $url, wp_scripts()->content_url ) ) ) {
				$url = wp_scripts()->base_url . $url;
			}

			/** This filter is documented in wp-includes/class.wp-scripts.php */
			$url = apply_filters( 'script_loader_src', $url, $handle );

			$revision = null;
			$version  = '';

			if ( null === $dependency->ver ) {
				$revision = wp_scripts()->default_version;
			} else {
				$version = $dependency->ver ? $dependency->ver : wp_scripts()->default_version;
			}

			if ( isset( wp_scripts()->args[ $handle ] ) ) {
				$version = $version ? $version . '&' . wp_scripts()->args[ $handle ] : wp_scripts()->args[ $handle ];
			}

			if ( ! empty( $version ) ) {
				$url = add_query_arg( 'ver', $version, $url );
			}

			// @todo Issue a warning when it is not a local file?
			if ( $url && $this->is_local_file_url( $url ) ) {
				$scripts->precaching_routes()->register( $url, $revision );
			}
		}

		$scripts->precaching_routes()->register_emoji_script();

		wp_scripts()->to_do = $original_to_do; // Restore original scripts to do.
	}

	/**
	 * Gets the priority this integration should be hooked into the service worker action with.
	 *
	 * @since 0.2
	 *
	 * @return int Hook priority. A higher number means a lower priority.
	 */
	public function get_priority() {
		return 10000;
	}

	/**
	 * Defines the scope of this integration by setting `$this->scope`.
	 *
	 * @since 0.2
	 */
	protected function define_scope() {
		$this->scope = WP_Service_Workers::SCOPE_ALL;
	}
}
