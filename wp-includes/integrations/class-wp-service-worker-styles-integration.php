<?php
/**
 * WP_Service_Worker_Styles_Integration class.
 *
 * @package PWA
 */

/**
 * Class representing the Styles service worker integration.
 *
 * @since 0.2
 */
class WP_Service_Worker_Styles_Integration extends WP_Service_Worker_Base_Integration {

	/**
	 * Stylesheet handles to manage.
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
	 * @param array $handles Stylesheet handles to manage.
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
			foreach ( wp_styles()->registered as $handle => $dependency ) {
				if ( ! empty( $dependency->extra['precache'] ) ) {
					$handles[] = $handle;
				}
			}
		}

		$original_to_do = wp_styles()->to_do;
		wp_styles()->all_deps( $handles );
		foreach ( wp_styles()->to_do as $handle ) {
			if ( ! isset( wp_styles()->registered[ $handle ] ) ) {
				continue;
			}

			$dependency = wp_styles()->registered[ $handle ];

			// Skip bundles.
			if ( ! $dependency->src ) {
				continue;
			}

			$url = $dependency->src;

			$revision = false === $dependency->ver ? get_bloginfo( 'version' ) : $dependency->ver;

			/** This filter is documented in wp-includes/class.wp-styles.php */
			$url = apply_filters( 'style_loader_src', $url, $handle );

			if ( $url ) {
				$registry->register_precached_route( $url, $revision );
			}
		}
		wp_styles()->to_do = $original_to_do; // Restore original styles to do.
	}
}
