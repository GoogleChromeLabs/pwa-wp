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
	 * The option group for the offline page option.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'reading';

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
	const OPTION_SELECTED_VALUE = '1';

	/**
	 * The ID of the settings section.
	 *
	 * @var string
	 */
	const SETTING_ID = 'wp_upgrade_https';

	/**
	 * The HTTPS protocol
	 *
	 * @var string
	 */
	const HTTPS_PROTOCOL = 'https://';

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
		$args = array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_callback' ),
		);

		register_setting(
			self::OPTION_GROUP,
			self::UPGRADE_HTTPS_OPTION,
			$args
		);

		register_setting(
			self::OPTION_GROUP,
			self::UPGRADE_INSECURE_CONTENT_OPTION,
			$args
		);
	}

	/**
	 * Sanitizes the raw setting value.
	 *
	 * @param string $raw_setting The setting before sanitizing it.
	 * @return string|null The setting, or null if it's not valid.
	 */
	public function sanitize_callback( $raw_setting ) {
		if ( self::OPTION_SELECTED_VALUE === $raw_setting || '0' === $raw_setting ) {
			return $raw_setting;
		}
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
		$upgrade_https_value           = get_option( self::UPGRADE_HTTPS_OPTION );
		$upgrade_insecure_content      = get_option( self::UPGRADE_INSECURE_CONTENT_OPTION );
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
		 * Todo: change !== to === as this is only like this for development.
		 * The WP_HTTPS_Detection doesn't work with my local SSL certificate.
		 * Also, change $this->is_currently_https() to ! $this->is_currently_https().
		 * This is also for development only.
		 * This main if block should only run if the site can use HTTPS, but the 'home' and 'siteurl' options aren't HTTPS.
		 */
		if ( $this->is_currently_https() && true !== get_option( WP_HTTPS_Detection::HTTPS_SUPPORT_OPTION_NAME ) ) :
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
			<p style="margin-top: 20px;"><strong><?php esc_html_e( 'HTTPS Upgrade', 'pwa' ); ?></strong></p>
			<p>
				<label><input name="<?php echo esc_attr( self::UPGRADE_HTTPS_OPTION ); ?>" type="radio" <?php checked( $upgrade_https_value, self::OPTION_SELECTED_VALUE ); ?> value="<?php echo esc_attr( self::OPTION_SELECTED_VALUE ); ?>"><?php esc_html_e( 'Yes', 'pwa' ); ?></label>
				<label><input name="<?php echo esc_attr( self::UPGRADE_HTTPS_OPTION ); ?>" type="radio" <?php checked( self::OPTION_SELECTED_VALUE !== $upgrade_https_value ); ?> value="0" ><?php esc_html_e( 'No', 'pwa' ); ?></label>
			</p>
			<p class="description"><?php esc_html_e( 'Your site appears to support HTTPS', 'pwa' ); ?></p>

			<p style="margin-top: 20px;"><strong><?php esc_html_e( 'Upgrade Insecure URLs', 'pwa' ); ?></strong></p>
			<p>
				<label><input name="<?php echo esc_attr( self::UPGRADE_INSECURE_CONTENT_OPTION ); ?>" type="radio" <?php checked( $upgrade_insecure_content, self::OPTION_SELECTED_VALUE ); ?> value="<?php echo esc_attr( self::OPTION_SELECTED_VALUE ); ?>"><?php esc_html_e( 'Yes', 'pwa' ); ?></label>
				<label><input name="<?php echo esc_attr( self::UPGRADE_INSECURE_CONTENT_OPTION ); ?>" type="radio" <?php checked( self::OPTION_SELECTED_VALUE !== $upgrade_insecure_content ); ?> value="0" ><?php esc_html_e( 'No', 'pwa' ); ?></label>
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
		$urls = array( get_option( 'home' ), get_option( 'siteurl' ) );
		foreach ( $urls as $url ) {
			if ( 0 !== strpos( $url, self::HTTPS_PROTOCOL ) ) {
				return false;
			}
		}
		return true;

	}

	/**
	 * Conditionally filters the 'siteurl' and 'home' values from wp-config and options.
	 */
	public function filter_site_url_and_home() {
		if ( self::OPTION_SELECTED_VALUE === get_option( self::UPGRADE_HTTPS_OPTION ) ) {
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
		return str_replace( 'http://', self::HTTPS_PROTOCOL, $url );
	}

	/**
	 * Conditionally filters the header, to add an Upgrade-Insecure-Requests value.
	 */
	public function filter_header() {
		if ( self::OPTION_SELECTED_VALUE === get_option( self::UPGRADE_INSECURE_CONTENT_OPTION ) ) {
			add_filter( 'wp_headers', array( $this, 'upgrade_insecure_requests' ) );
		}
	}

	/**
	 * Adds an upgrade-insecure-requests header.
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
