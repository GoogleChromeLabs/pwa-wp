<?php
/**
 * PWAWP_APP_Manifest class.
 *
 * @package PWA
 */

/**
 * PWAWP_APP_Manifest class.
 *
 * Mainly copied from Jetpack_PWA_Manifest and Jetpack_PWA_Helpers.
 */
class PWAWP_APP_Manifest {

	/**
	 * The query arg that is present in a request for the app manifest.
	 *
	 * @var string
	 */
	const MANIFEST_QUERY_ARG = 'pwawp_manifest';

	/**
	 * The default manifest icon sizes.
	 *
	 * Copied from Jetpack_PWA_Helpers::get_default_manifest_icon_sizes().
	 * Based on a conversation in https://github.com/GoogleChrome/lighthouse/issues/291
	 *
	 * @var int[]
	 */
	public $default_manifest_icon_sizes = array( 192, 512 );

	/**
	 * Inits the class.
	 *
	 * Mainly copied from Jetpack_PWA_Manifest::__construct().
	 */
	public function init() {
		add_action( 'wp_head', array( $this, 'manifest_link_and_meta' ) );
		add_action( 'template_redirect', array( $this, 'send_manifest_json' ), 2 );
	}

	/**
	 * Outputs the <link> and <meta> tags for the app manifest.
	 *
	 * Mainly copied from Jetpack_PWA_Manifest::render_manifest_link().
	 */
	public function manifest_link_and_meta() {
		?>
		<link rel="manifest" href="<?php echo esc_url( add_query_arg( self::MANIFEST_QUERY_ARG, '1', home_url( '/' ) ) ); ?>">
		<meta name="theme-color" content="<?php echo esc_attr( $this->get_theme_color() ); ?>">
		<?php
	}

	/**
	 * Gets the theme color for the manifest.
	 *
	 * Mainly copied from Jetpack_PWA_Helpers::get_theme_color().
	 * First looks for the header background color in the AMP for WordPress plugin, if it's active.
	 * This color displays on loading the app.
	 *
	 * @return string $theme_color The theme color for the manifest.json file, as a hex value.
	 */
	public function get_theme_color() {
		$theme_color = '';
		if ( current_theme_supports( 'custom-background' ) ) {
			$background_color = get_background_color(); // This returns a hex value without the leading #, or an empty string.
			if ( $background_color ) {
				$theme_color = "#$background_color";
			}
		}

		/**
		 * Enables overriding the PWA theme color.
		 *
		 * @param string $theme_color Hex color value.
		 */
		return apply_filters( 'pwawp_background_color', $theme_color );
	}

	/**
	 * Sends the json response of the manifest.
	 *
	 * Mainly copied from Jetpack_PWA_Helpers::render_manifest_json().
	 */
	public function send_manifest_json() {

		// Don't load the manifest in multiple locations.
		if ( is_front_page() && ! empty( $_GET[ self::MANIFEST_QUERY_ARG ] ) ) { // WPCS: CSRF ok.
			$theme_color = $this->get_theme_color();
			$manifest    = array(
				'name'      => get_bloginfo( 'name' ),
				'start_url' => get_home_url(),
				'display'   => 'standalone',
			);

			if ( $theme_color ) {
				$manifest['background_color'] = $theme_color;
				$manifest['theme_color']      = $theme_color;
			}

			/**
			 * Gets the 'short_name' by cutting off the blog name at the first space after the 12th character.
			 * This prevents cutting a word off in the middle.
			 *
			 * @todo: consider another source for this, as this short name could still be awkward.
			 */
			preg_match( '/^(.{1,12}[^\s]*)/', get_bloginfo( 'name' ), $short_name_matches );
			if ( $short_name_matches ) {
				$manifest['short_name'] = $short_name_matches[1];
			}

			$description = get_bloginfo( 'description' );
			if ( $description ) {
				$manifest['description'] = $description;
			}

			if ( function_exists( 'get_site_icon_url' ) ) {
				$manifest['icons'] = array_map(
					array( $this, 'build_icon_object' ),
					$this->default_manifest_icon_sizes
				);
			}

			/**
			 * Enables overriding the manifest json.
			 *
			 * There are more possible values for this, including 'orientation' and 'scope.'
			 * See the documentation: https://developers.google.com/web/fundamentals/web-app-manifest/
			 *
			 * @param array $manifest The manifest to send in the json response.
			 */
			$manifest = apply_filters( 'pwawp_manifest_json', $manifest );

			wp_send_json( $manifest );
		}
	}

	/**
	 * Gets an icon object, based on its size (dimension).
	 *
	 * Copied from Jetpack_PWA_Manifest::build_icon_object() and Jetpack_PWA_Helpers::site_icon_url().
	 *
	 * @param int $size The size of the icon, like 512.
	 * @return array|null $icon_object The icon object data, or null if there's no site icon.
	 */
	public function build_icon_object( $size ) {
		$site_icon_id = get_option( 'site_icon' );
		if ( ! $site_icon_id ) {
			return null;
		}

		return array(
			'src'   => get_site_icon_url( $size ),
			'sizes' => sprintf( '%1$dx%1$d', $size ),
			'type'  => get_post_mime_type( $site_icon_id ),
		);
	}
}
