<?php
/**
 * WP_Service_Worker_Admin_Assets_Integration class.
 *
 * @package PWA
 */

/**
 * Class representing the admin assets service worker integration.
 *
 * @since 0.2
 * @deprecated 0.7 Integrations will not be proposed for WordPress core merge.
 */
final class WP_Service_Worker_Admin_Assets_Integration extends WP_Service_Worker_Base_Integration {

	/**
	 * Registers the integration functionality.
	 *
	 * @since 0.2
	 *
	 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
	 */
	public function register( WP_Service_Worker_Scripts $scripts ) {
		if ( ! function_exists( 'list_files' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$admin_dir    = ABSPATH . 'wp-admin/';
		$admin_images = list_files( $admin_dir . 'images/' );
		$inc_images   = list_files( ABSPATH . WPINC . '/images/' );

		// @todo This should not enqueue TinyMCE if rich editing is disabled?
		$this->flag_admin_assets_with_precache( wp_scripts()->registered );
		$this->flag_admin_assets_with_precache( wp_styles()->registered );

		$routes = array_merge(
			$this->get_routes_from_file_list( $admin_images, 'wp-admin' ),
			$this->get_routes_from_file_list( $inc_images, 'wp-includes' ),
			$this->get_woff_file_list(),
			$this->get_tinymce_file_list()
		);

		foreach ( $routes as $options ) {
			$url = $options['url'];
			unset( $options['url'] );
			$scripts->precaching_routes()->register( $url, $options );
		}

		// Add deprecation warning in user's console when service worker is installed.
		$scripts->register(
			__CLASS__ . '-deprecation',
			array(
				'src' => static function () {
					return sprintf(
						'console.warn( %s );',
						wp_json_encode(
							sprintf(
								/* translators: %1$s: integration class name, %2$s: issue url */
								__( 'The %1$s integration in the PWA plugin is no longer being considered WordPress core merge. See %2$s', 'pwa' ),
								__CLASS__,
								'https://github.com/GoogleChromeLabs/pwa-wp/issues/403'
							)
						)
					);
				},
			)
		);
	}

	/**
	 * Defines the scope of this integration by setting `$this->scope`.
	 *
	 * @since 0.2
	 */
	protected function define_scope() {
		$this->scope = WP_Service_Workers::SCOPE_ADMIN;
	}

	/**
	 * Flags admin assets with precache.
	 *
	 * @param _WP_Dependency[] $dependencies Array of _WP_Dependency objects.
	 */
	protected function flag_admin_assets_with_precache( $dependencies ) {
		foreach ( $dependencies as $params ) {

			// Only precache scripts from wp-admin and wp-includes (and Gutenberg).
			if ( preg_match( '#/(wp-admin|wp-includes|wp-content/plugins/gutenberg)/#', $params->src ) ) {
				$params->add_data( 'precache', true );
			}
		}
	}

	/**
	 * Get static list of .woff files to precache.
	 *
	 * @todo These should be also available to the frontend. So this should go into WP_Service_Worker_Precaching_Routes.
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
	 * Get list of TinyMCE files for precaching.
	 *
	 * @return array Routes.
	 */
	protected function get_tinymce_file_list() {
		global $tinymce_version;
		$tinymce_routes = array();
		$tinymce_files  = list_files( ABSPATH . WPINC . '/js/tinymce/' );

		foreach ( $tinymce_files as $tinymce_file ) {
			if ( preg_match( '#\.min\.(css|js)$#', $tinymce_file ) ) {
				$url = includes_url( preg_replace( '/.*' . WPINC . '/', '', $tinymce_file ) );

				$tinymce_routes[] = array(
					'url'      => $url,
					'revision' => $tinymce_version,
				);
			}
		}
		return $tinymce_routes;
	}

	/**
	 * Get routes from file paths list.
	 *
	 * @param string[] $paths  List of file paths.
	 * @param string   $folder Folder -- either 'wp-admin' or 'wp-includes'.
	 * @return array List of routes.
	 */
	protected function get_routes_from_file_list( $paths, $folder ) {
		$routes = array();
		foreach ( $paths as $filename ) {
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
}
