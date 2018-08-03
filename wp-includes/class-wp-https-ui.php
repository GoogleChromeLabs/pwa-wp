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
	 * The ID of the content settings section.
	 *
	 * @var string
	 */
	const CONTENT_SETTING_ID = 'wp_upgrade_insecure_content';

	/**
	 * The number of initial URLs that are shown.
	 *
	 * @var int
	 */
	const INITIAL_URLS_SHOWN = 5;

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
	 * Adds a settings field to the 'General Settings' page.
	 *
	 * Only add this if the site can support HTTPS,
	 * but the home and siteurl option values are not HTTPS (WordPress Address and Site Address).
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
			esc_html__( 'More details', 'pwa' )
		);

		?>
		<p class="description">
			<?php
			echo wp_kses_post( sprintf(
				/* translators: %s: a link for more details */
				esc_html__( 'HTTPS is essential to securing your WordPress site, we strongly suggest upgrading to HTTPS. %s', 'pwa' ),
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

		$insecure_content_id = 'insecure-content';
		$all_insecure_urls   = isset( $insecure_urls_option['active'], $insecure_urls_option['passive'] ) ? array_merge( $insecure_urls_option['active'], $insecure_urls_option['passive'] ) : array();
		$total_urls_count    = count( $all_insecure_urls );
		$description         = sprintf(
			/* translators: %1$d is the number of non-secure URLs, %1$s is a link for more details */
			__( 'There are %1$d non-HTTPS URLs on your home page. They will be upgraded to HTTPS automatically, but you might check to be sure your page looks as expected. %2$s', 'pwa' ),
			$total_urls_count,
			sprintf(
				'<a href="%s">%s</a>',
				__( 'https://developer.mozilla.org/en-US/docs/Web/Security/Mixed_content/How_to_fix_website_with_mixed_content', 'pwa' ),
				__( 'More details', 'pwa' )
			)
		);

		?>
		<div id="<?php echo esc_attr( $insecure_content_id ); ?>" <?php echo ! $upgrade_https_value ? 'class="hidden"' : ''; ?>>
			<p style="margin-top: 20px;" class="description">
				<?php echo wp_kses_post( $description ); ?>
			</p>
			<ul>
				<?php
				for ( $i = 0; $i < self::INITIAL_URLS_SHOWN; $i++ ) :
					if ( empty( $all_insecure_urls[ $i ] ) ) :
						continue;
					endif;
					?>
					<li><a href="<?php echo esc_attr( $all_insecure_urls[ $i ] ); ?>"><?php echo esc_html( $all_insecure_urls[ $i ] ); ?></a></li>
				<?php endfor; ?>
			</ul>
			<?php if ( $total_urls_count > self::INITIAL_URLS_SHOWN ) : ?>
				<details style="max-width: 400px; max-height: 200px; overflow-y: scroll">
					<summary><?php esc_html_e( 'More', 'pwa' ); ?></summary>
					<ul>
						<?php
						for ( $i = self::INITIAL_URLS_SHOWN; $i < $total_urls_count; $i++ ) :
							if ( empty( $all_insecure_urls[ $i ] ) ) :
								continue;
							endif;
							?>
							<li><a href="<?php echo esc_attr( $all_insecure_urls[ $i ] ); ?>"><?php echo esc_html( $all_insecure_urls[ $i ] ); ?></a></li>
						<?php endfor; ?>
					</ul>
				</details>
			<?php endif; ?>
		</div>
		<script>
			//  On checking 'HTTPS Upgrade,' toggle the display of the insecure URLs, as they don't apply unless it's checked.
			(function ( $ ) {
				$( 'input[type=checkbox][name="<?php echo esc_js( self::UPGRADE_HTTPS_OPTION ); ?>"]' ).on( 'change', function() {
					$( '#<?php echo esc_js( $insecure_content_id ); ?>' ).toggleClass( 'hidden' );
				} );
			})( jQuery );
		</script>
		<?php
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
	 *
	 * Only adds this if there is active insecure content.
	 * Normally, the risk in upgrading insecure requests is that when a HTTP URL is upgraded to HTTPS, it could fail.
	 * And there's no fallback.
	 * But if there are active insecure URLs, the browser already blocks them.
	 * So they're failing already, and upgrading them at least gives them a chance.
	 * This does not add the header if there is no or only passive insecure content detected
	 * Those are less of a security risk, and upgrading them to HTTPS might cause them to fail (with no fallback).
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Upgrade-Insecure-Requests
	 */
	public function filter_header() {
		$insecure_urls = get_option( WP_HTTPS_Detection::INSECURE_CONTENT_OPTION_NAME );
		if ( ! empty( $insecure_urls['active'] ) ) {
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
