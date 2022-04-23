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
final class WP_Web_App_Manifest {

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
	 * Option name for short_name.
	 *
	 * @var string
	 */
	const SHORT_NAME_OPTION = 'short_name';

	/**
	 * Maximum length for short_name.
	 *
	 * @since 0.4
	 * @link https://developers.google.com/web/tools/lighthouse/audits/manifest-contains-short_name
	 * @link https://developer.chrome.com/apps/manifest/name#short_name
	 * @var int
	 */
	const SHORT_NAME_MAX_LENGTH = 12;

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
	 * Initialize.
	 */
	public function init() {
		add_action( 'wp_head', array( $this, 'manifest_link_and_meta' ) );
		add_action( 'rest_api_init', array( $this, 'register_manifest_rest_route' ) );
		add_filter( 'site_status_tests', array( $this, 'add_pwa_site_health_tests' ) );

		add_action( 'rest_api_init', array( $this, 'register_short_name_setting' ) );
		add_action( 'admin_init', array( $this, 'register_short_name_setting' ) );
		add_action( 'admin_init', array( $this, 'add_short_name_settings_field' ) );
	}

	/**
	 * Outputs the <link> and <meta> tags for the app manifest.
	 *
	 * Mainly copied from Jetpack_PWA_Manifest::render_manifest_link().
	 */
	public function manifest_link_and_meta() {
		$manifest = $this->get_manifest();
		?>
		<link rel="manifest" href="<?php echo esc_url( static::get_url() ); ?>">
		<meta name="theme-color" content="<?php echo esc_attr( $manifest['theme_color'] ); ?>">
		<?php
		$display = isset( $manifest['display'] ) ? $manifest['display'] : '';
		switch ( $display ) :
			case 'fullscreen':
			case 'minimal-ui':
			case 'standalone':
				?>
				<meta name="apple-mobile-web-app-capable" content="yes">
				<meta name="mobile-web-app-capable" content="yes">

				<?php
				// Use the smallest icon from the manifest as the default Apple touch startup image.
				$icons = isset( $manifest['icons'] ) ? $manifest['icons'] : array();
				usort(
					$icons,
					static function ( $a, $b ) {
						$a_size = isset( $a['sizes'] ) ? (int) strtok( $a['sizes'], 'x' ) : 0;
						$b_size = isset( $b['sizes'] ) ? (int) strtok( $b['sizes'], 'x' ) : 0;
						return $a_size - $b_size;
					}
				);
				$icon = array_shift( $icons );

				$images = array();
				if ( ! empty( $icon ) ) {
					$images[] = array( 'href' => $icon['src'] );
				}

				/**
				 * Filters splash screen images for Safari on iOS.
				 *
				 * @param array $images {
				 *     Array of splash screen images and their attributes.
				 *
				 *     @type array ...$0 {
				 *         Array of splash screen image attributes.
				 *
				 *         @type string $href  URL of splash screen image. Required.
				 *         @type string $media Media query for when the splash screen image should be used.
				 *     }
				 * }
				 */
				$images = apply_filters( 'apple_touch_startup_images', $images );

				foreach ( $images as $image ) {
					if ( is_array( $image ) && isset( $image['href'] ) && esc_url( $image['href'], array( 'http', 'https' ) ) ) {
						printf( '<link rel="apple-touch-startup-image" href="%s"', esc_url( $image['href'] ) );
						if ( isset( $image['media'] ) ) {
							printf( ' media="%s"', esc_attr( $image['media'] ) );
						}
						echo ">\n";
					}
				}
				break;
		endswitch;
		?>

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
	 * Check if the supplied name is short.
	 *
	 * @link https://developers.google.com/web/tools/lighthouse/audits/manifest-contains-short_name
	 * @link https://developer.chrome.com/apps/manifest/name#short_name
	 * @link https://github.com/GoogleChrome/lighthouse/blob/949bbdb/lighthouse-core/computed/manifest-values.js#L13-L15
	 *
	 * @param string $name Name.
	 * @return bool Whether name is short.
	 */
	public function is_name_short( $name ) {
		$name   = trim( $name );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $name ) : strlen( $name );
		return $length <= self::SHORT_NAME_MAX_LENGTH;
	}

	/**
	 * Gets the manifest data for the REST API response.
	 *
	 * Mainly copied from Jetpack_PWA_Helpers::render_manifest_json().
	 */
	public function get_manifest() {
		$manifest = array(
			'name'      => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'utf-8' ),
			'start_url' => home_url( '/' ),
			'display'   => 'minimal-ui',
			'dir'       => is_rtl() ? 'rtl' : 'ltr',
		);

		$short_name = get_option( self::SHORT_NAME_OPTION, '' );
		if ( $short_name ) {
			$manifest['short_name'] = $short_name;
		} elseif ( $this->is_name_short( $manifest['name'] ) ) {
			// Lighthouse complains when the short_name is absent, even when the name is 12 characters or less. If the name
			// is 12 characters or less, use it as the short_name.
			$manifest['short_name'] = trim( $manifest['name'] );
		}

		$language = get_bloginfo( 'language' );
		if ( $language ) {
			$manifest['lang'] = $language;
		}

		$theme_color = $this->get_theme_color();
		if ( $theme_color ) {
			$manifest['background_color'] = $theme_color;
			$manifest['theme_color']      = $theme_color;
		}

		$description = html_entity_decode( get_bloginfo( 'description' ), ENT_QUOTES, 'utf-8' );
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
	 * Register test for PWA site health checks.
	 *
	 * @since 0.7
	 *
	 * @param array $tests Tests.
	 * @return array Tests.
	 */
	public function add_pwa_site_health_tests( $tests ) {
		// Add tests for the short_name.
		$tests['direct']['web_app_manifest_short_name'] = array(
			'label' => __( 'Short Name in Web App Manifest', 'pwa' ),
			'test'  => array( $this, 'test_short_name_present_in_manifest' ),
		);

		// Add tests for the site icon validation.
		$tests['direct']['pwa_site_icon_validation'] = array(
			'label' => __( 'Site Icon', 'pwa' ),
			'test'  => array( $this, 'test_site_icon' ),
		);

		return $tests;
	}

	/**
	 * Test that web app manifest contains a short_name.
	 *
	 * @since 0.4
	 * @todo Add test for PNG site icon.
	 *
	 * @return array Test results.
	 */
	public function test_short_name_present_in_manifest() {
		$manifest = $this->get_manifest();

		$description = sprintf(
			/* translators: %s is the max length as a number */
			__( 'This is the short version of your site title. It is displayed when there is not enough space for the full title, for example with the site icon on a phone&#8217;s homescreen as an installed app. It should be a maximum of %s characters long.', 'pwa' ),
			number_format_i18n( self::SHORT_NAME_MAX_LENGTH )
		);

		$actions = sprintf(
			/* translators: %s is the URL to the Short Name field on the General Settings screen */
			__( 'You can update the <a href="%s">Short Name</a> on the General Settings screen.', 'pwa' ),
			esc_url( admin_url( 'options-general.php' ) . '#short_name' )
		);

		if ( empty( $manifest['short_name'] ) ) {
			$result = array(
				'label'       => __( 'Web App Manifest lacks a short name entry', 'pwa' ),
				'status'      => 'recommended',
				'badge'       => array(
					'label' => __( 'Progressive Web App', 'pwa' ),
					'color' => 'orange',
				),
				'description' => wp_kses_post( sprintf( '<p>%s</p>', $description ) ),
				'actions'     => wp_kses_post( $actions ),
			);
		} elseif ( ! $this->is_name_short( $manifest['short_name'] ) ) {
			$result = array(
				'label'       =>
					wp_kses_post(
						sprintf(
							/* translators: %s is the short name */
							__( 'Web App Manifest has a short name (%s) that is too long', 'pwa' ),
							$manifest['short_name']
						)
					),
				'status'      => 'recommended',
				'badge'       => array(
					'label' => __( 'Progressive Web App', 'pwa' ),
					'color' => 'orange',
				),
				'description' => wp_kses_post( sprintf( '<p>%s</p>', $description ) ),
				'actions'     => wp_kses_post( $actions ),
			);
		} else {
			$result = array(
				'label'       =>
					wp_kses_post(
						sprintf(
							/* translators: %s is the short name */
							__( 'Web App Manifest has a short name (%s)', 'pwa' ),
							$manifest['short_name']
						)
					),
				'status'      => 'good',
				'badge'       => array(
					'label' => __( 'Progressive Web App', 'pwa' ),
					'color' => 'green',
				),
				'description' => wp_kses_post( sprintf( '<p>%s</p>', $description ) ),
			);
		}

		$result['test'] = 'web_app_manifest_short_name';

		return $result;
	}

	/**
	 * Validate site icon.
	 *
	 * @since 0.7
	 *
	 * @return true|WP_Error $icon_errors Errors for site icon, or true if there were no errors.
	 */
	public function validate_site_icon() {
		$icon_errors = new WP_Error();

		$site_icon_id = get_option( 'site_icon' );

		if ( ! $site_icon_id ) {
			$icon_errors->add( 'site_icon_not_set', __( 'A site icon has not been selected.', 'pwa' ) );
			return $icon_errors;
		}

		$site_icon_metadata = wp_get_attachment_metadata( $site_icon_id );

		if ( ! $site_icon_metadata ) {
			$icon_errors->add( 'site_icon_metadata_not_found', __( 'The site icon metadata could not be found. Please re-select a site icon.', 'pwa' ) );
			return $icon_errors;
		}

		if ( $site_icon_metadata['width'] < 512 || $site_icon_metadata['height'] < 512 ) {
			$icon_errors->add( 'site_icon_too_small', __( 'The site icon is too small. Please use a square image with a minimum size of 512 &times; 512 pixels.', 'pwa' ) );
		}

		if ( $site_icon_metadata['height'] !== $site_icon_metadata['width'] ) {
			$icon_errors->add( 'site_icon_not_square', __( 'The site icon is not square. Please select an image with a 1:1 aspect ratio. You may be able to crop your existing site icon.', 'pwa' ) );
		}

		if ( 'image/png' !== get_post_mime_type( $site_icon_id ) ) {
			$icon_errors->add( 'site_icon_not_png', __( 'The site icon is not a PNG image. Please select an image in PNG format.', 'pwa' ) );
		}

		return empty( $icon_errors->errors ) ? true : $icon_errors;
	}

	/**
	 * Test site icon if it is set, not too small and is square.
	 *
	 * @since 0.7
	 *
	 * @return array $result The test result.
	 */
	public function test_site_icon() {
		$site_icon_validity = $this->validate_site_icon();

		$results = array(
			'label'       => __( 'Site icon is valid', 'pwa' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Progressive Web App', 'pwa' ),
				'color' => 'green',
			),
			'description' => '<p>' . wp_kses_post( __( 'The site icon is used as a browser and app icon. It is recommended to use a square image with a minimum size of 512 &times; 512 pixels.', 'pwa' ) ) . '</p>',
			'actions'     => '',
			'test'        => 'pwa_site_icon_validation',
		);

		if ( true === $site_icon_validity ) {
			return $results;
		}

		$results['label']          = __( 'You should fix your site icon', 'pwa' );
		$results['status']         = 'recommended';
		$results['badge']['color'] = 'orange';

		if ( current_user_can( 'customize' ) ) {
			$results['actions'] = wp_kses_post(
				sprintf(
					'<a class="button button-secondary" href="%s">%s</a>',
					admin_url( 'customize.php?autofocus[control]=site_icon' ),
					esc_html__( 'Select site icon', 'pwa' )
				)
			);
		}

		$results['description']  = '<p>' . esc_html__( 'Resolve the following issue(s) to ensure your site is available as a Progressive Web App:', 'pwa' ) . '</p>';
		$results['description'] .= '<ul>';
		foreach ( $site_icon_validity->get_error_messages() as $message ) {
			$results['description'] .= wp_kses_post( sprintf( '<li>%s</li>', $message ) );
		}
		$results['description'] .= '</ul>';

		return $results;
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
				'callback'            => array( $this, 'rest_serve_manifest' ),
				'permission_callback' => array( $this, 'rest_permission' ),
			)
		);
	}

	/**
	 * Serve the manifest file.
	 *
	 * This serves our manifest file and sets the content type to `application/manifest+json`.
	 *
	 * @return WP_REST_Response Response containing the manifest and the right content-type header.
	 */
	public function rest_serve_manifest() {
		$response = rest_ensure_response( $this->get_manifest() );
		$response->header( 'Content-Type', 'application/manifest+json' );

		return $response;
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

		$mime_type = get_post_mime_type( $site_icon_id );
		if ( ! $mime_type ) {
			return array();
		}

		$icons    = array();
		$maskable = get_option( 'site_icon_maskable', false );
		foreach ( $this->default_manifest_icon_sizes as $size ) {
			$src = get_site_icon_url( $size );
			if ( $src ) {
				$icon = array(
					'purpose' => 'any',
					'src'     => $src,
					'sizes'   => sprintf( '%1$dx%1$d', $size ),
					'type'    => $mime_type,
				);

				$icons[] = $icon;

				if ( $maskable ) {
					$icon['purpose'] = 'maskable';
					$icons[]         = $icon;
				}
			}
		}
		return $icons;
	}

	/**
	 * Return manifest URL.
	 *
	 * @return string
	 */
	public static function get_url() {
		return rest_url( self::REST_NAMESPACE . self::REST_ROUTE );
	}

	/**
	 * Register setting for short_name.
	 */
	public function register_short_name_setting() {
		register_setting(
			'general',
			self::SHORT_NAME_OPTION,
			array(
				'type'              => 'string',
				'description'       => __( 'Short version of site title which is suitable for app icon on home screen.', 'pwa' ),
				'sanitize_callback' => array( $this, 'sanitize_short_name' ),
				'show_in_rest'      => true,
			)
		);
	}

	/**
	 * Add the settings field for short name.
	 */
	public function add_short_name_settings_field() {
		add_settings_field(
			self::SHORT_NAME_OPTION,
			esc_html__( 'Short Name', 'pwa' ),
			array( $this, 'render_short_name_settings_field' ),
			'general'
		);
	}

	/**
	 * Sanitize short name.
	 *
	 * @param string|mixed $value Unsanitized short name.
	 * @return string Sanitized short name.
	 */
	public function sanitize_short_name( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( sanitize_text_field( $value ) );
		return (string) substr( $value, 0, self::SHORT_NAME_MAX_LENGTH );
	}

	/**
	 * Render short name settings field.
	 *
	 * @return void
	 */
	public function render_short_name_settings_field() {
		$short_name_option = get_option( self::SHORT_NAME_OPTION, '' );

		$manifest          = $this->get_manifest();
		$actual_short_name = isset( $manifest['short_name'] ) ? $manifest['short_name'] : '';

		// Disable the field if the user is supplying the short name via the web_app_manifest filter.
		$readonly = (
			isset( $manifest['short_name'] )
			&&
			$actual_short_name !== $short_name_option
			&&
			has_filter( 'web_app_manifest' )
		);

		?>
		<table id="short_name_table" hidden>
			<tr>
				<th scope="row">
					<label for="<?php echo esc_attr( self::SHORT_NAME_OPTION ); ?>">
						<?php esc_html_e( 'Short Name', 'pwa' ); ?>
					</label>
				</th>
				<td>
					<input
						type="text"
						id="<?php echo esc_attr( self::SHORT_NAME_OPTION ); ?>"
						name="<?php echo esc_attr( self::SHORT_NAME_OPTION ); ?>"
						value="<?php echo esc_attr( $readonly ? $actual_short_name : $short_name_option ); ?>"
						class="regular-text <?php echo $readonly ? 'disabled' : ''; ?>" maxlength="<?php echo esc_attr( (string) self::SHORT_NAME_MAX_LENGTH ); ?>"
						<?php disabled( $readonly ); ?>
					>
					<p class="description">
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s is the max length as a number */
								__( 'This is the short version of your site title. It is displayed when there is not enough space for the full title, for example with the site icon on a phone&#8217;s homescreen as an installed app. It should be a maximum of %s characters long.', 'pwa' ),
								number_format_i18n( self::SHORT_NAME_MAX_LENGTH )
							)
						);
						?>
						<?php if ( $readonly ) : ?>
							<strong>
							<?php esc_html_e( 'A plugin or theme is managing this field.', 'pwa' ); ?>
							</strong>
						<?php endif; ?>
					</p>
				</td>
			</tr>
		</table>

		<script>
			( ( shortNameTable, blogNameField, shortNameMaxLength ) => {
				if ( ! shortNameTable || ! blogNameField ) {
					return;
				}

				const blogNameRow = blogNameField.closest( 'tr' );
				const shortNameRow = shortNameTable.querySelector( 'tr' );
				const shortNameField = shortNameTable.querySelector( '#short_name' );

				blogNameRow.parentNode.insertBefore( shortNameRow, blogNameRow.nextSibling );
				shortNameTable.parentNode.removeChild( shortNameTable );

				/*
				 * Enable form validation for General Settings. Disabling form validation was done 8 years ago (2014) in
				 * WP 4.0 in 2014 due to an email validation bug in Firefox. This has since surely been resolved, so
				 * there is no no need to retain it and we can start to benefit from client-side validation, such as
				 * the required constraint for the short name. See <https://core.trac.wordpress.org/ticket/22183#comment:6>.
				 */
				shortNameField.form.noValidate = false;

				/**
				 * Update short_name field.
				 *
				 * When the Site Title is not too long, use it as the placeholder for the Short Name field and let it
				 * not be required.
				 */
				function updateShortNameField() {
					if ( blogNameField.value.trim().length <= shortNameMaxLength ) {
						shortNameField.required = false;
						shortNameField.placeholder = blogNameField.value.trim();
					} else {
						shortNameField.required = true;
						shortNameField.placeholder = '';
					}
				}
				updateShortNameField();
				blogNameField.addEventListener( 'input', updateShortNameField );
			} )(
				document.getElementById( 'short_name_table' ),
				document.getElementById( 'blogname' ),
				<?php echo wp_json_encode( self::SHORT_NAME_MAX_LENGTH ); ?>
			);
		</script>
		<?php
	}
}
