<?php
/**
 * WP_HTTPS_UI class.
 *
 * @package PWA
 */

/**
 * WP_HTTPS_UI class.
 */
final class WP_HTTPS_UI {

	/**
	 * The option group, indicating that this UI should be on the 'General Settings' page.
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
	 * The maximum number of URLs that show initially, before clicking 'Show more'.
	 *
	 * @var int
	 */
	const NUMBER_INITIAL_URLS = 4;

	/**
	 * The number of URLs in each <ul>, which display on clicking 'Show more'.
	 *
	 * @var string
	 */
	const NUMBER_URLS_IN_EACH_UL = 10;

	/**
	 * The max character length of the URL, after which it will be truncated with an ellipsis.
	 *
	 * @var int
	 */
	const MAX_URL_LENGTH = 75;

	/**
	 * The instance of WP_HTTPS_Detection.
	 *
	 * @var WP_HTTPS_Detection
	 */
	public $wp_https_detection;

	/**
	 * WP_HTTPS_UI constructor.
	 *
	 * @param WP_HTTPS_Detection $wp_https_detection An instance of WP_HTTPS_Detection.
	 */
	public function __construct( $wp_https_detection ) {
		$this->wp_https_detection = $wp_https_detection;
	}

	/**
	 * Initializes the object.
	 */
	public function init() {
		/** This filter is documented in wp-includes/class-wp-https-detection.php */
		if ( apply_filters( 'wp_https_detection_ui_disabled', false ) ) {
			return;
		}

		add_action( 'admin_init', array( $this, 'init_admin' ) );
		add_action( 'init', array( $this, 'filter_site_url_and_home' ) );
		add_action( 'init', array( $this, 'filter_header' ) );
		add_action( 'template_redirect', array( $this, 'conditionally_redirect_to_https' ), 11 ); // At 11 to run after redirect_canonical().
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
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
	}

	/**
	 * Adds a settings field to the 'General Settings' page.
	 *
	 * Only add this if the site can support HTTPS,
	 * but the home and siteurl option values are not HTTPS (WordPress Address and Site Address).
	 * This UI would not apply if those URLs are already HTTPS.
	 */
	public function add_settings_field() {
		add_settings_field(
			self::HTTPS_SETTING_ID,
			__( 'HTTPS', 'pwa' ),
			array( $this, 'render_https_settings' ),
			self::OPTION_GROUP
		);
	}

	/**
	 * Renders the HTTPS settings in /wp-admin on the General Settings page.
	 */
	public function render_https_settings() {
		$https_support = get_option( WP_HTTPS_Detection::HTTPS_SUPPORT_OPTION_NAME );
		if ( empty( $https_support ) ) {
			return;
		}

		$upgrade_https_value = (bool) get_option( self::UPGRADE_HTTPS_OPTION );
		$https_more_details  = sprintf(
			'<a href="%s">%s</a>',
			esc_url( __( 'https://make.wordpress.org/support/user-manual/web-publishing/https-for-wordpress/', 'pwa' ) ),
			esc_html__( 'More details', 'pwa' )
		);

		?>
		<p class="description">
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s: a link for more details */
					__( 'HTTPS is essential to securing your WordPress site, we strongly suggest upgrading to it. %s', 'pwa' ),
					$https_more_details
				)
			);
			?>
		</p>

		<?php if ( is_wp_error( $https_support ) ) : ?>
			<?php foreach ( $https_support->get_error_messages() as $error_message ) : ?>
				<div class="notice notice-error inline">
					<p>
						<?php echo esc_html( $error_message ); ?>
					</p>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

		<p>
			<label>
				<input name="<?php echo esc_attr( self::UPGRADE_HTTPS_OPTION ); ?>" type="checkbox" <?php checked( $upgrade_https_value ); ?> value="<?php echo esc_attr( self::OPTION_CHECKED_VALUE ); ?>">
				<?php esc_html_e( 'Force secure connections', 'pwa' ); ?>
			</label>
		</p>
		<script>
			( function ( $ ) {
				// Move this UI under the Site Address (URL) <tr> on the General Settings page.
				$( 'input[name=<?php echo self::UPGRADE_HTTPS_OPTION; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>]' ).parents( 'tr' ).insertAfter( $( 'label[for=home]' ).parents( 'tr') );
			} )( jQuery );
		</script>
		<?php

		$all_insecure_urls = get_option( WP_HTTPS_Detection::INSECURE_CONTENT_OPTION_NAME );
		$total_urls_count  = is_array( $all_insecure_urls ) && ! empty( $all_insecure_urls ) ? count( $all_insecure_urls ) : 0;

		// Exit if there is no insecure URL to display.
		if ( ! $total_urls_count ) {
			return;
		}

		$insecure_content_id = 'insecure-content';
		$show_more_button_id = 'view-urls';
		$insecure_urls_class = 'insecure-urls';
		$description         = sprintf(
			/* translators: %s is a link for more details */
			__( 'We found content on your site that wasn&#39;t loading correctly over HTTPS. While we will try to fix these links automatically, you might check to be sure your pages work as expected. %s', 'pwa' ),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( __( 'https://developer.mozilla.org/en-US/docs/Web/Security/Mixed_content/How_to_fix_website_with_mixed_content', 'pwa' ) ),
				esc_html__( 'More details', 'pwa' )
			)
		);

		/*
		 * Add class="hidden" to this <div> if the 'HTTPS Upgrade' checkbox isn't checked.
		 * This insecure content UI does not apply if the user isn't upgrading to HTTPS,
		 * as there will be no need to upgrade insecure requests.
		 */
		?>
		<div id="<?php echo esc_attr( $insecure_content_id ); ?>" <?php echo ! $upgrade_https_value ? 'class="hidden"' : ''; ?>>
			<p class="description">
				<?php echo wp_kses_post( $description ); ?>
			</p>
			<ul class="<?php echo esc_attr( $insecure_urls_class ); ?>">
				<?php
				for ( $i = 0; $i < self::NUMBER_INITIAL_URLS; $i++ ) :
					if ( ! isset( $all_insecure_urls[ $i ] ) ) :
						break;
					endif;

					$url           = $all_insecure_urls[ $i ];
					$truncated_url = $this->get_truncated_url( $url );
					?>
					<li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $truncated_url ); ?></a></li>
				<?php endfor; ?>
			</ul>
			<?php
			// Output the <ul> elements that display on clicking 'Show more'.
			while ( isset( $all_insecure_urls[ $i ] ) ) :
				?>
				<ul class="<?php echo esc_attr( $insecure_urls_class ); ?> hidden">
					<?php
					for ( $j = 0; $j < self::NUMBER_URLS_IN_EACH_UL; $j++ ) :
						if ( ! isset( $all_insecure_urls[ $i ] ) ) {
							break;
						}
						$url           = $all_insecure_urls[ $i++ ];
						$truncated_url = $this->get_truncated_url( $url );
						?>
						<li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $truncated_url ); ?></a></li>
					<?php endfor; ?>
				</ul>
			<?php endwhile; ?>
			<?php if ( $total_urls_count > self::NUMBER_INITIAL_URLS ) : ?>
				<button id="<?php echo esc_attr( $show_more_button_id ); ?>" class="button button-secondary"><?php esc_html_e( 'Show more', 'pwa' ); ?></button>
			<?php endif; ?>
		</div>
		<script>
			( function ( $ ) {
				// On checking 'Upgrade to secure connection,' toggle the display of the insecure URLs, as they don't apply unless it's checked.
				$( 'input[type=checkbox][name="<?php echo esc_attr( self::UPGRADE_HTTPS_OPTION ); ?>"]' ).on( 'change', function() {
					$( <?php echo wp_json_encode( "#$insecure_content_id" ); ?> ).toggleClass( 'hidden' );
				} );

				$( '#<?php echo esc_attr( $show_more_button_id ); ?>' ).on( 'click', function( event ) {
					event.preventDefault();
					$( <?php echo wp_json_encode( ".$insecure_urls_class.hidden" ); ?> ).first().removeClass( 'hidden' );

					// If there are no more insecure URLs that are hidden, hide the 'Show more' button.
					if ( ! $( <?php echo wp_json_encode( ".$insecure_urls_class.hidden" ); ?> ).length ) {
						$( this ).addClass( 'hidden' );
					}
				} );
			} )( jQuery );
		</script>
		<style>
			.<?php echo $insecure_urls_class; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> {
				margin: 0 0 0 5px;
			}

			.<?php echo $insecure_urls_class; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> li {
				padding: 4px 0;
				margin-bottom: 0;
			}

			.<?php echo $insecure_urls_class; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> li:nth-child(odd) {
				background: rgba(255,255,255,0.6);
			}

			#<?php echo $show_more_button_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> {
				margin-top: 10px;
			}

			#<?php echo $insecure_content_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> .description {
				margin: 20px 0;
			}
		</style>
		<?php
	}

	/**
	 * Gets the URL, which is truncated with an ellipsis if it goes over the maximum character length.
	 *
	 * @param string $url URL The URL to truncate.
	 * @return string $url The processed URL.
	 */
	public function get_truncated_url( $url ) {
		if ( strlen( $url ) <= self::MAX_URL_LENGTH ) {
			return $url;
		}
		return substr( $url, 0, self::MAX_URL_LENGTH ) . '&hellip;';
	}

	/**
	 * Conditionally filters the 'siteurl' and 'home' values from the wp-config and options.
	 *
	 * Note that these run at priority 11 so that they apply after _config_wp_home().
	 */
	public function filter_site_url_and_home() {
		if ( get_option( self::UPGRADE_HTTPS_OPTION ) && ! $this->wp_https_detection->is_currently_https() ) {
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
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Upgrade-Insecure-Requests
	 */
	public function filter_header() {
		if ( get_option( self::UPGRADE_HTTPS_OPTION ) ) {
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
		$headers['Content-Security-Policy'] = 'upgrade-insecure-requests';
		return $headers;
	}

	/**
	 * Conditionally redirects HTTP requests to HTTPS.
	 *
	 * @see redirect_canonical() This runs when accessing a post permalink over HTTP.
	 */
	public function conditionally_redirect_to_https() {
		$do_redirect = (
			! is_ssl()
			&&
			get_option( self::UPGRADE_HTTPS_OPTION ) // The checkbox to upgrade to HTTPS is checked.
			&&
			get_option( WP_HTTPS_Detection::HTTPS_SUPPORT_OPTION_NAME ) // The last loopback request to check for HTTPS succeeded.
			&&
			isset( $_SERVER['REQUEST_URI'] )
			&&
			( 'cli' !== php_sapi_name() || class_exists( 'WP_UnitTestCase' ) )
		);

		if ( $do_redirect ) {
			$parsed_url = wp_parse_url( home_url( '/', 'https' ) );

			$url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
			if ( isset( $parsed_url['port'] ) ) {
				$url .= ':' . $parsed_url['port'];
			}
			$url .= wp_unslash( $_SERVER['REQUEST_URI'] );

			wp_safe_redirect( $url, 302 ); // Temporary redirect. @todo Make permanent.
			exit;
		}
	}
}
