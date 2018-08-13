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
	 * The expected value if the option is enabled.
	 *
	 * @var string
	 */
	const OPTION_CHECKED_VALUE = '1';

	/**
	 * The ID of the HTTPS settings section.
	 *
	 * @var string
	 */
	const HTTPS_SETTING_ID = 'wp_upgrade_https';

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
	 * Adds a settings field to the 'General Settings' page.
	 *
	 * Only add this if the site can support HTTPS,
	 * but the home and siteurl option values are not HTTPS (WordPress Address and Site Address).
	 * This UI would not apply if those URLs are already HTTPS.
	 */
	public function add_settings_field() {
		/*
		 * Todo: change ! get_option() to get_option, as this is only for development.
		 * The WP_HTTPS_Detection doesn't work with my local SSL certificate, and always returns false.
		 * Also, change $this->is_currently_https() to ! $this->is_currently_https().
		 * This is also for development only.
		 * This if block should only run if the site can use HTTPS, but the 'home' and 'siteurl' options aren't HTTPS.
		 */
		if ( $this->is_currently_https() && ! get_option( WP_HTTPS_Detection::HTTPS_SUPPORT_OPTION_NAME ) ) {
			add_settings_field(
				self::HTTPS_SETTING_ID,
				__( 'HTTPS', 'pwa' ),
				array( $this, 'render_https_settings' ),
				self::OPTION_GROUP
			);
		}
	}

	/**
	 * Renders the HTTPS settings in /wp-admin on the General Settings page.
	 */
	public function render_https_settings() {
		$upgrade_https_value = (bool) get_option( self::UPGRADE_HTTPS_OPTION );
		$https_more_details  = sprintf(
			'<a href="%s">%s</a>',
			__( 'https://make.wordpress.org/support/user-manual/web-publishing/https-for-wordpress/', 'pwa' ),
			__( 'More details', 'pwa' )
		);

		?>
		<p class="description">
			<?php
			echo wp_kses_post( sprintf(
				/* translators: %s: a link for more details */
				__( 'HTTPS is essential to securing your WordPress site, we strongly suggest upgrading to HTTPS. %s', 'pwa' ),
				$https_more_details
			) );
			?>
		</p>
		<p>
			<label><input name="<?php echo esc_attr( self::UPGRADE_HTTPS_OPTION ); ?>" type="checkbox" <?php checked( $upgrade_https_value ); ?> value="<?php echo esc_attr( self::OPTION_CHECKED_VALUE ); ?>"><?php esc_html_e( 'HTTPS Upgrade', 'pwa' ); ?></label>
		</p>
		<?php

		$insecure_urls_option = get_option( WP_HTTPS_Detection::INSECURE_CONTENT_OPTION_NAME );

		/*
		 * Only display the insecure URLs if there's active insecure content.
		 * These are more of a security risk than passive insecure content, like <img>, <video>, and <audio> elements.
		 */
		if ( empty( $insecure_urls_option['active'] ) ) {
			return;
		}

		$insecure_content_id   = 'insecure-content';
		$passive_insecure_urls = isset( $insecure_urls_option['passive'] ) ? $insecure_urls_option['passive'] : array();
		$active_insecure_urls  = isset( $insecure_urls_option['active'] ) ? $insecure_urls_option['active'] : array();
		$all_insecure_urls     = array_merge( $passive_insecure_urls, $active_insecure_urls );
		$total_urls_count      = count( $all_insecure_urls );

		/**
		 * If there are no active insecure URLs, do not display the insecure URLs.
		 * In that case, this won't upgrade insecure requests, and there's less of a reason to notify the user.
		 */
		if ( ! count( $active_insecure_urls ) ) {
			return;
		}

		$description = sprintf(
			/* translators: %1$d is the number of non-secure URLs, %1$s is a link for more details */
			_n(
				'There is %1$d non-HTTPS URL on your home page. It will be upgraded to HTTPS automatically, but you might check to be sure your page looks as expected. %2$s',
				'There are %1$d non-HTTPS URLs on your home page. They will be upgraded to HTTPS automatically, but you might check to be sure your page looks as expected. %2$s',
				$total_urls_count,
				'pwa'
			),
			number_format_i18n( $total_urls_count ),
			sprintf(
				'<a href="%s">%s</a>',
				__( 'https://developer.mozilla.org/en-US/docs/Web/Security/Mixed_content/How_to_fix_website_with_mixed_content', 'pwa' ),
				__( 'More details', 'pwa' )
			)
		);

		/**
		 * Add class="hidden" to this <div> if the 'HTTPS Upgrade' checkbox isn't checked.
		 * This insecure content UI does not apply if the user isn't upgrading to HTTPS,
		 * as there will be no need to upgrade insecure requests.
		 */
		?>
		<div id="<?php echo esc_attr( $insecure_content_id ); ?>" <?php echo ! $upgrade_https_value ? 'class="hidden"' : ''; ?>>
			<p style="margin-top: 20px;" class="description">
				<?php echo wp_kses_post( $description ); ?>
			</p>
			<ul style="max-width: 400px; max-height: 220px; overflow-y: auto">
				<?php
				for ( $i = 0; $i < $total_urls_count; $i++ ) :
					if ( empty( $all_insecure_urls[ $i ] ) ) :
						continue;
					endif;
					?>
					<li><a href="<?php echo esc_attr( $all_insecure_urls[ $i ] ); ?>"><?php echo esc_html( $all_insecure_urls[ $i ] ); ?></a></li>
				<?php endfor; ?>
			</ul>
		</div>
		<script>
			//  On checking 'HTTPS Upgrade,' toggle the display of the insecure URLs, as they don't apply unless it's checked.
			(function ( $ ) {
				$( 'input[type=checkbox][name="<?php echo esc_js( self::UPGRADE_HTTPS_OPTION ); ?>"]' ).on( 'change', function() {
					$( '#<?php echo esc_attr( $insecure_content_id ); ?>' ).toggleClass( 'hidden' );
				} );
			})( jQuery );
		</script>
		<?php
	}

	/**
	 * Whether the options indicate that the site is currently using HTTPS.
	 *
	 * Returns true only if the siteurl and home option values are HTTPS.
	 * These are also known as the WordPress Address (URL) and Site Address (URL) in the 'General Settings' page.
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
		if ( get_option( self::UPGRADE_HTTPS_OPTION ) && ! $this->is_currently_https() ) {
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
	 *
	 * Only adds this if there is active insecure content.
	 * Normally, the risk in upgrading insecure requests is that when a HTTP URL is upgraded to HTTPS, it could fail.
	 * And there's no fallback.
	 * But if there are active insecure URLs like for scripts, the browser already blocks them.
	 * So they're failing already, and upgrading them at least gives them a chance.
	 * This does not add the header if there is no or only passive insecure content like
	 * Those are less of a security risk, and upgrading them to HTTPS might cause them to fail, with no fallback.
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Upgrade-Insecure-Requests
	 */
	public function filter_header() {
		$insecure_urls = get_option( WP_HTTPS_Detection::INSECURE_CONTENT_OPTION_NAME );
		if ( ! empty( $insecure_urls['active'] ) && get_option( self::UPGRADE_HTTPS_OPTION ) && ! $this->is_currently_https() ) {
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
