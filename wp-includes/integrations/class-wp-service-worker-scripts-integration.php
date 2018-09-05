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
class WP_Service_Worker_Scripts_Integration extends WP_Service_Worker_Base_Integration {

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
	}

	/**
	 * Registers the integration functionality.
	 *
	 * @since 0.2
	 *
	 * @param WP_Service_Worker_Registry $registry Instance to register service worker behavior with.
	 */
	public function register( WP_Service_Worker_Registry $registry ) {
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

			$revision = false === $dependency->ver ? get_bloginfo( 'version' ) : $dependency->ver;

			/** This filter is documented in wp-includes/class.wp-scripts.php */
			$url = apply_filters( 'script_loader_src', $url, $handle );

			if ( $url ) {
				$registry->register_precached_route( $url, $revision );
			}
		}
		wp_scripts()->to_do = $original_to_do; // Restore original scripts to do.
	}
}
