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
	 * Inits the class.
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'init_admin' ) );
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
			'https://make.wordpress.org/support/user-manual/web-publishing/https-for-wordpress/',
			esc_html__( 'More details', 'pwa' )
		);
		$insecure_content_more_details = sprintf(
			'<a href="%s">%s</a>',
			'https://developer.mozilla.org/en-US/docs/Web/Security/Mixed_content/How_to_fix_website_with_mixed_content',
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
		/* Translators: %s: a link for more details */
		$insecure_content_description = esc_html__( 'Your home page doesn’t contain insecure URLs. However, there may be URLs on other pages that could be blocked. %s', 'pwa' );

		?>
		<p class="description">
			<?php
			echo wp_kses_post( sprintf(
				/* Translators: %s: a link for more details */
				esc_html__( 'HTTPS is essential to securing your WordPress site, we strongly suggest upgrading to HTTPS on your site. %s', 'pwa' ),
				$https_more_details
			) );
			?>
		</p>
		<p style="margin-top: 20px;"><strong><?php esc_html_e( 'HTTPS Upgrade', 'pwa' ); ?></strong></p>
		<p class="description"><?php esc_html_e( 'Your site appears to support HTTPS', 'pwa' ); ?></p>
		<p>
			<label><input name="<?php echo esc_attr( self::UPGRADE_HTTPS_OPTION ); ?>" type="radio" <?php checked( $upgrade_https_value, self::OPTION_SELECTED_VALUE ); ?> value="<?php echo esc_attr( self::OPTION_SELECTED_VALUE ); ?>"><?php esc_html_e( 'Yes', 'pwa' ); ?></label>
			<label><input name="<?php echo esc_attr( self::UPGRADE_HTTPS_OPTION ); ?>" type="radio" <?php checked( self::OPTION_SELECTED_VALUE !== $upgrade_https_value ); ?> value="0" ><?php esc_html_e( 'No', 'pwa' ); ?></label>
		</p>

		<p style="margin-top: 20px;"><strong><?php esc_html_e( 'Upgrade Insecure URLs', 'pwa' ); ?></strong></p>
		<p class="description">
			<?php echo wp_kses_post( printf( $insecure_content_description, $insecure_content_more_details ) ); ?>
		<p>
			<label><input name="<?php echo esc_attr( self::UPGRADE_INSECURE_CONTENT_OPTION ); ?>" type="radio" <?php checked( $upgrade_insecure_content, self::OPTION_SELECTED_VALUE ); ?> value="<?php echo esc_attr( self::OPTION_SELECTED_VALUE ); ?>"><?php esc_html_e( 'Yes', 'pwa' ); ?></label>
			<label><input name="<?php echo esc_attr( self::UPGRADE_INSECURE_CONTENT_OPTION ); ?>" type="radio" <?php checked( self::OPTION_SELECTED_VALUE !== $upgrade_insecure_content ); ?> value="0" ><?php esc_html_e( 'No', 'pwa' ); ?></label>
		</p>
		<?php
	}

}
