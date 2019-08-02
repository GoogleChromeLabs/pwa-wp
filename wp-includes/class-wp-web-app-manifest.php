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
	const REST_NAMESPACE = 'wp/v2';

	/**
	 * The REST API route for the manifest request.
	 *
	 * @var string
	 */
	const REST_ROUTE = '/web-app-manifest';

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
		$manifest = $this->get_manifest();
		?>
		<link rel="manifest" href="<?php echo esc_url( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ); ?>">
		<meta name="theme-color" content="<?php echo esc_attr( $manifest['theme_color'] ); ?>">
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="mobile-web-app-capable" content="yes">
		<meta name="apple-touch-fullscreen" content="YES">
		<?php
		$icons = isset( $manifest['icons'] ) ? $manifest['icons'] : array();
		usort( $icons, array( $this, 'sort_icons_callback' ) );
		$icon = array_shift( $icons );
		?>
		<?php if ( ! empty( $icon ) ) : ?>
			<link rel="apple-touch-startup-image" href="<?php echo esc_url( $icon['src'] ); ?>">
		<?php endif; ?>

		<?php $name = isset( $manifest['short_name'] ) ? $manifest['short_name'] : $manifest['name']; ?>
		<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( $name ); ?>">
		<meta name="application-name" content="<?php echo esc_attr( $name ); ?>">
		<?php
	}

	/**
	 * Gets the theme color for the manifest.
	 *
	 * Mainly copied from Jetpack_PWA_Helpers::get_theme_color().
	 * This color displays on loading the app.
	 *
	 * @return string $theme_color The theme color for the manifest.json file, as a hex value.
	 */
	public function get_theme_color() {

		// Check if the current theme supports theme-color and a color is defined.
		if ( current_theme_supports( 'theme-color' ) ) {
			$theme_color = get_theme_support( 'theme-color' );
			if ( $theme_color ) {
				return ( is_array( $theme_color ) ) ? array_shift( $theme_color ) : $theme_color;
			}
		}

		// Check if the current theme supports custom-background and a color is defined.
		if ( current_theme_supports( 'custom-background' ) ) {
			$background_color = get_background_color(); // This returns a hex value without the leading #, or an empty string.
			if ( $background_color ) {
				return "#{$background_color}";
			}
		}

		// Fallback color.
		return self::FALLBACK_THEME_COLOR;
	}

	/**
	 * Gets the manifest data for the REST API response.
	 *
	 * Mainly copied from Jetpack_PWA_Helpers::render_manifest_json().
	 */
	public function get_manifest() {
		$manifest = array(
			'name'      => wp_kses_decode_entities( get_bloginfo( 'name' ) ),
			'start_url' => home_url( '/' ),
			'display'   => 'minimal-ui',
			'dir'       => is_rtl() ? 'rtl' : 'ltr',
		);
		$language = get_bloginfo( 'language' );
		if ( $language ) {
			$manifest['lang'] = $language;
		}

		$theme_color = $this->get_theme_color();
		if ( $theme_color ) {
			$manifest['background_color'] = $theme_color;
			$manifest['theme_color']      = $theme_color;
		}

		$description = wp_kses_decode_entities( get_bloginfo( 'description' ) );
		if ( $description ) {
			$manifest['description'] = $description;
		}

		$manifest_icons = $this->get_icons();
		if ( ! empty( $manifest_icons ) ) {
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
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_manifest' ),
				'permission_callback' => array( $this, 'rest_permission' ),
			)
		);
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
	 * @return array $icon_object An array of icons, which may be empty.
	 */
	public function get_icons() {
		$site_icon_id = get_option( 'site_icon' );
		if ( ! $site_icon_id || ! function_exists( 'get_site_icon_url' ) ) {
			return array();
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

	/**
	 * Sort icon sizes.
	 *
	 * Used as a callback in usort(), called from the manifest_link_and_meta() method.
	 *
	 * @param array $a The 1st icon item in our comparison.
	 * @param array $b The 2nd icon item in our comparison.
	 * @return int
	 */
	public function sort_icons_callback( $a, $b ) {
		return (int) strtok( $a['sizes'], 'x' ) - (int) strtok( $b['sizes'], 'x' );
	}
}
