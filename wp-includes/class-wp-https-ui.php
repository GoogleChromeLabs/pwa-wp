<?php
/**
 * WP_HTTPS_UI class.
 *
 * @package PWA
 */

/**
 * WP_HTTPS_UI class.
 */
class WP_HTTPS_UI {

	/**
	 * The option group.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'general';

	/**
	 * The option name to upgrade to https.
	 *
	 * @var string
	 */
	const UPGRADE_HTTPS_OPTION = 'should_upgrade_https';

	/**
	 * The option name to ugprade insecure content.
	 *
	 * @var string
	 */
	const UPGRADE_INSECURE_CONTENT_OPTION = 'should_upgrade_insecure_content';

	/**
	 * The expected value if the option is enabled.
	 *
	 * @var string
	 */
	const OPTION_CHECKED_VALUE = '1';

	/**
	 * The ID of the settings section.
	 *
	 * @var string
	 */
	const SETTING_ID = 'wp_upgrade_https';

	/**
	 * Inits the class.
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'init_admin' ) );
		add_action( 'admin_init', array( $this, 'filter_site_url_and_home' ) );
		add_action( 'admin_init', array( $this, 'filter_header' ) );
	}

	/**
	 * Initializes the admin tasks.
	 */
	public function init_admin() {
		$this->register_settings();
		$this->add_settings_field();
	}

	/**
	 * Registers the HTTPS settings.
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::UPGRADE_HTTPS_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'upgrade_https_sanitize_callback' ),
			)
		);

		register_setting(
			self::OPTION_GROUP,
			self::UPGRADE_INSECURE_CONTENT_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'upgrade_insecure_content_sanitize_callback' ),
			)
		);
	}

	/**
	 * Sanitization callback for the upgrade HTTPS option.
	 *
	 * @param string $raw_value The value to sanitize.
	 * @return bool Whether the option is true or false.
	 */
	public function upgrade_https_sanitize_callback( $raw_value ) {
		unset( $raw_value );
		return isset( $_POST[ self::UPGRADE_HTTPS_OPTION ] ); // WPCS: CSRF OK.
	}

	/**
	 * Sanitization callback for the upgrade insecure content option.
	 *
	 * @param string $raw_value The value to sanitize.
	 * @return bool Whether the option is true or false.
	 */
	public function upgrade_insecure_content_sanitize_callback( $raw_value ) {
		unset( $raw_value );
		return isset( $_POST[ self::UPGRADE_INSECURE_CONTENT_OPTION ] ); // WPCS: CSRF OK.
	}

	/**
	 * Adds a settings field to the 'Reading Settings' page.
	 */
	public function add_settings_field() {
		add_settings_field(
			self::SETTING_ID,
			__( 'HTTPS', 'pwa' ),
			array( $this, 'render_settings' ),
			self::OPTION_GROUP
		);
	}

	/**
	 * Renders the HTTPS settings in /wp-admin on the Reading Settings page.
	 */
	public function render_settings() {
		$upgrade_https_value           = (bool) get_option( self::UPGRADE_HTTPS_OPTION );
		$upgrade_insecure_content      = (bool) get_option( self::UPGRADE_INSECURE_CONTENT_OPTION );
		$https_more_details            = sprintf(
			'<a href="%s">%s</a>',
			__( 'https://make.wordpress.org/support/user-manual/web-publishing/https-for-wordpress/', 'pwa' ),
			esc_html__( 'More details', 'pwa' )
		);
		$insecure_content_more_details = sprintf(
			'<a href="%s">%s</a>',
			__( 'https://developer.mozilla.org/en-US/docs/Web/Security/Mixed_content/How_to_fix_website_with_mixed_content', 'pwa' ),
			esc_html__( 'More details', 'pwa' )
		);

		/**
		 * Todo: change this description based on what insecure content is found.
		 *
		 * Have a separate description for when there's only passive insecure content.
		 * And one for when there's active and passive insecure content.
		 *
		 * @see https://github.com/xwp/pwa-wp/issues/17#issuecomment-406188455
		 */
		/* translators: %s: a link for more details */
		$insecure_content_description = esc_html__( 'Your home page doesn&#8217;t contain insecure URLs. However, there may be URLs on other pages that could be blocked. %s', 'pwa' );

		/*
		 * Todo: change ! get_option() to get_option, as this is only for development.
		 * The WP_HTTPS_Detection doesn't work with my local SSL certificate, and always returns false.
		 * Also, change $this->is_currently_https() to ! $this->is_currently_https().
		 * This is also for development only.
		 * This if block should only run if the site can use HTTPS, but the 'home' and 'siteurl' options aren't HTTPS.
		 */
		if ( $this->is_currently_https() && ! get_option( WP_HTTPS_Detection::HTTPS_SUPPORT_OPTION_NAME ) ) :
			?>
			<p class="description">
				<?php
				echo wp_kses_post( sprintf(
					/* translators: %s: a link for more details */
					esc_html__( 'HTTPS is essential to securing your WordPress site, we strongly suggest upgrading to HTTPS on your site. %s', 'pwa' ),
					$https_more_details
				) );
				?>
			</p>
			<p>
				<label><input name="<?php echo esc_attr( self::UPGRADE_HTTPS_OPTION ); ?>" type="checkbox" <?php checked( $upgrade_https_value ); ?> value="<?php echo esc_attr( self::OPTION_CHECKED_VALUE ); ?>"><?php esc_html_e( 'HTTPS Upgrade', 'pwa' ); ?></label>
			</p>
			<p class="description"><?php esc_html_e( 'Your site appears to support HTTPS', 'pwa' ); ?></p>

			<p style="margin-top: 20px;">
				<label><input name="<?php echo esc_attr( self::UPGRADE_INSECURE_CONTENT_OPTION ); ?>" type="checkbox" <?php checked( $upgrade_insecure_content ); ?> value="<?php echo esc_attr( self::OPTION_CHECKED_VALUE ); ?>"><?php esc_html_e( 'Upgrade Insecure URLs', 'pwa' ); ?></label>
			</p>
			<p class="description">
				<?php echo wp_kses_post( sprintf( $insecure_content_description, $insecure_content_more_details ) ); ?>
			<p>
			<?php
		else :
			/* translators: %s: HTTPS more details link */
			echo wp_kses_post( sprintf( __( 'Your site doesn&#8217;t look like it supports HTTPS. %s', 'pwa' ), $https_more_details ) );
		endif;
	}

	/**
	 * Whether the options indicate that the site is currently using HTTPS.
	 *
	 * @return bool Whether currently HTTPS.
	 */
	public function is_currently_https() {
		$urls = array( home_url(), site_url() );
		foreach ( $urls as $url ) {
			if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) {
				return false;
			}
		}
		return true;

	}

	/**
	 * Conditionally filters the 'siteurl' and 'home' values from wp-config and options.
	 */
	public function filter_site_url_and_home() {
		if ( get_option( self::UPGRADE_HTTPS_OPTION ) ) {
			add_filter( 'option_home', array( $this, 'convert_to_https' ), 11 );
			add_filter( 'option_siteurl', array( $this, 'convert_to_https' ), 11 );
		}
	}

	/**
	 * Converts a URL from HTTP to HTTPS.
	 *
	 * @param string $url The URL to convert.
	 * @return string $url The converted URL.
	 */
	public function convert_to_https( $url ) {
		return preg_replace( '#^http(?=://)#', 'https', $url );
	}

	/**
	 * Conditionally filters the header, to add an Upgrade-Insecure-Requests value.
	 */
	public function filter_header() {
		if ( get_option( self::UPGRADE_INSECURE_CONTENT_OPTION ) ) {
			add_filter( 'wp_headers', array( $this, 'upgrade_insecure_requests' ) );
		}
	}

	/**
	 * Adds an Upgrade-Insecure-Requests header.
	 *
	 * Upgrades all insecure requests to HTTPS, like scripts and images.
	 * There's no fallback if the upgraded HTTPS request fails.
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Upgrade-Insecure-Requests
	 * @param array $headers The response headers.
	 * @return array $headers The filtered response headers.
	 */
	public function upgrade_insecure_requests( $headers ) {
		$headers['Upgrade-Insecure-Requests'] = '1';
		return $headers;
	}
}
