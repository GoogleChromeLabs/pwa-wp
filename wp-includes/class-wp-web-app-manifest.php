<?php
/**
 * WP_Web_App_Manifest class.
 *
 * @package PWA
 */

/**
 * WP_Web_App_Manifest class.
 *
 * Mainly copied from Jetpack_PWA_Manifest and Jetpack_PWA_Helpers.
 */
class WP_Web_App_Manifest {

	/**
	 * The theme color to use if no dynamic value is present.
	 *
	 * @var string
	 */
	const FALLBACK_THEME_COLOR = '#fff';

	/**
	 * The REST API namespace for the manifest request.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'app/v1';

	/**
	 * The REST API route for the manifest request.
	 *
	 * @var string
	 */
	const REST_ROUTE = '/web-manifest';

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
		add_action( 'rest_api_init', array( $this, 'register_manifest_rest_route' ) );
	}

	/**
	 * Outputs the <link> and <meta> tags for the app manifest.
	 *
	 * Mainly copied from Jetpack_PWA_Manifest::render_manifest_link().
	 */
	public function manifest_link_and_meta() {
		?>
		<link rel="manifest" href="<?php echo esc_url( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ); ?>">
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
		if ( current_theme_supports( 'custom-background' ) ) {
			$background_color = get_background_color(); // This returns a hex value without the leading #, or an empty string.
			if ( $background_color ) {
				$theme_color = "#{$background_color}";
			}
		}

		if ( ! isset( $theme_color ) ) {
			$theme_color = self::FALLBACK_THEME_COLOR;
		}

		return $theme_color;
	}

	/**
	 * Gets the manifest data for the REST API response.
	 *
	 * Mainly copied from Jetpack_PWA_Helpers::render_manifest_json().
	 */
	public function get_manifest() {
		$manifest = array(
			'name'      => get_bloginfo( 'name' ),
			'start_url' => get_home_url(),
			'display'   => 'minimal-ui',
			'dir'       => is_rtl() ? 'rtl' : 'ltr',
		);
		$language = get_bloginfo( 'language' );
		if ( $language ) {
			$manifest['lang'] = $language;
		}

		/**
		 * Gets the 'short_name' by limiting the blog name to 12 characters.
		 * And if this cuts off a word, it omits the word entirely by using a positive look-ahead in the regex.
		 * For example, the first 12 characters of 'My PWA WordPress Site' are 'My PWA WordP'.
		 * Because this cuts off the last word, this removes 'WordP' entirely: 'My PWA'.
		 *
		 * @link https://stackoverflow.com/questions/12646197/cut-the-string-to-be-80-characters-and-must-keep-the-words-without-cutting-th#answer-12646400
		 */
		preg_match( '/^.{0,12}(?= |$)/', get_bloginfo( 'name' ), $short_name_matches );
		if ( $short_name_matches ) {
			$manifest['short_name'] = $short_name_matches[0];
		}

		$theme_color = $this->get_theme_color();
		if ( $theme_color ) {
			$manifest['background_color'] = $theme_color;
			$manifest['theme_color']      = $theme_color;
		}

		$description = get_bloginfo( 'description' );
		if ( $description ) {
			$manifest['description'] = $description;
		}

		$manifest_icons = $this->get_icons();
		if ( $manifest_icons ) {
			$manifest['icons'] = $manifest_icons;
		}

		/**
		 * Enables overriding the manifest json.
		 *
		 * There are more possible values for this, including 'orientation' and 'scope.'
		 * See the documentation: https://developers.google.com/web/fundamentals/web-app-manifest/
		 *
		 * @param array $manifest The manifest to send in the REST API response.
		 */
		return apply_filters( 'web_app_manifest', $manifest );
	}

	/**
	 * Registers the rest route to get the manifest.
	 */
	public function register_manifest_rest_route() {
		register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_manifest' ),
			'permission_callback' => array( $this, 'rest_permission' ),
		) );
	}

	/**
	 * Registers the rest route to get the manifest.
	 *
	 * Mainly copied from WP_REST_Posts_Controller::get_items_permissions_check().
	 * This should ndt allow a request in the 'edit' context.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request is allowed, WP_Error if the request is in the 'edit' context.
	 */
	public function rest_permission( WP_REST_Request $request ) {
		if ( 'edit' === $request['context'] ) {
			return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to edit the manifest.', 'default' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Gets the manifest icons.
	 *
	 * Mainly copied from Jetpack_PWA_Manifest::build_icon_object() and Jetpack_PWA_Helpers::site_icon_url().
	 *
	 * @return array|null $icon_object An array of icons, or null if there's no site icon.
	 */
	public function get_icons() {
		$site_icon_id = get_option( 'site_icon' );
		if ( ! $site_icon_id || ! function_exists( 'get_site_icon_url' ) ) {
			return null;
		}

		$icons     = array();
		$mime_type = get_post_mime_type( $site_icon_id );
		foreach ( $this->default_manifest_icon_sizes as $size ) {
			$icons[] = array(
				'src'   => get_site_icon_url( $size ),
				'sizes' => sprintf( '%1$dx%1$d', $size ),
				'type'  => $mime_type,
			);
		}
		return $icons;
	}
}
