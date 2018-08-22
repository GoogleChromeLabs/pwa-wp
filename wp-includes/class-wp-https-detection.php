<?php
/**
 * WP_HTTPS_Detection class.
 *
 * @package PWA
 */

/**
 * WP_HTTPS_Detection class.
 */
class WP_HTTPS_Detection {

	/**
	 * The cron hook to check HTTPS support.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'check_https_support';

	/**
	 * The interval at which to run the cron hook.
	 *
	 * @var string
	 */
	const CRON_INTERVAL = 'twicedaily';

	/**
	 * The option name for whether HTTPS is supported.
	 *
	 * @var string
	 */
	const HTTPS_SUPPORT_OPTION_NAME = 'is_https_supported';

	/**
	 * The option name for the insecure content.
	 *
	 * @var string
	 */
	const INSECURE_CONTENT_OPTION_NAME = 'insecure_content';

	/**
	 * The tag names and attributes for insecure content types.
	 *
	 * These are organized by 'passive' and 'active' to show the security risk they pose.
	 * Passive insecure content is less of a risk, and includes images and media.
	 * The tag name indicates the tag to search for, and the attribute is where the URL will be.
	 * For example, in the <img> tag, the URL will be in the 'src'.
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/Security/Mixed_content
	 * @var array $insecure_content_type[][] {
	 *     The insecure content types.
	 *
	 *     @type string    $tag       The name of the tag.
	 *     @type string    $attribute The name of the attribute to check.
	 * }
	 */
	public $insecure_content_types = array(
		'passive' => array(
			'img'   => 'src',
			'audio' => 'src',
			'video' => 'src',
		),
		'active'  => array(
			'script' => 'src',
			'link'   => 'href',
			'iframe' => 'src',
		),
	);

	/**
	 * Inits the class.
	 */
	public function init() {
		add_action( 'wp', array( $this, 'schedule_cron' ) );
		add_action( self::CRON_HOOK, array( $this, 'update_https_support_options' ) );
		add_filter( 'cron_request', array( $this, 'ensure_http_if_sslverify' ), PHP_INT_MAX );
		$wp_https_ui = new WP_HTTPS_UI( $this );
		$wp_https_ui->init();

		// @todo: remove this, as it's only for development.
		add_action( 'init', array( $this, 'set_mock_insecure_content' ) );
	}

	/**
	 * Schedules a cron event to check for HTTPS support.
	 */
	public function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), self::CRON_INTERVAL, self::CRON_HOOK );
		}
	}

	/**
	 * Makes a request to find whether HTTPS is supported, and stores the results in options.
	 *
	 * Updates the options for HTTPS support and insecure content.
	 * But if the request is a WP_Error, this does not update the options.
	 */
	public function update_https_support_options() {
		// If the home and siteurl values are already HTTPS, there's no need to prepare these options for the UI.
		if ( $this->is_currently_https() ) {
			return;
		}

		$https_support_response = $this->check_https_support();
		if ( ! is_wp_error( $https_support_response ) ) {
			update_option( self::HTTPS_SUPPORT_OPTION_NAME, 200 === wp_remote_retrieve_response_code( $https_support_response ) );
			update_option( self::INSECURE_CONTENT_OPTION_NAME, $this->get_insecure_content( $https_support_response ) );
		}
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
	 * Makes a loopback request to the homepage to determine whether HTTPS is supported.
	 *
	 * @return array|WP_Error A response from a loopback request to the homepage, or a WP_Error.
	 */
	public function check_https_support() {
		// Add an arbitrary query arg to prevent a cached response.
		$response = wp_remote_request(
			add_query_arg(
				'a',
				'',
				home_url( '/', 'https' )
			),
			array(
				'headers' => array(
					'Cache-Control' => 'no-cache',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( ! $this->has_proper_manifest( $body ) ) {
			return new WP_Error(
				'invalid_https_validation_source',
				__( 'There was an issue in the request for HTTPS verification.', 'pwa' )
			);
		}

		return $response;
	}

	/**
	 * Gets whether the body has a manifest <link>, with the proper href value.
	 *
	 * @param string $body The body of the response.
	 * @return boolean Whether the body has a proper manifest.
	 */
	public function has_proper_manifest( $body ) {
		$dom = new DOMDocument();
		$dom->loadHTML( $body );

		foreach ( $dom->getElementsByTagName( 'link' ) as $link ) {
			if ( $link->getAttribute( 'href' ) === rest_url( WP_Web_App_Manifest::REST_NAMESPACE . WP_Web_App_Manifest::REST_ROUTE ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Gets the URLs with insecure content in the response that could not be upgraded to HTTPS.
	 *
	 * Finds insecure content in the HTML document, including images, scripts, and stylesheets.
	 * And attempts to upgrade the HTTP URL to HTTPS with is_upgraded_url_valid().
	 * If a request to the upgraded URL does not succeed, this includes it in the returned URL(s).
	 *
	 * @param array $response The response from a wp_remote_request().
	 * @return array $insecure_content[][] {
	 *     The insecure content, by type.
	 *
	 *     @type string[]  $passive The passive insecure URLs.
	 *     @type string[]  $active  The active insecure URLs.
	 * }
	 */
	public function get_insecure_content( $response ) {
		$libxml_previous_state = libxml_use_internal_errors( true );
		$body                  = wp_remote_retrieve_body( $response );
		$dom                   = new DOMDocument();
		$dom->loadHTML( $body );
		$insecure_urls = array();

		foreach ( $this->insecure_content_types as $content_type => $tags_to_check ) {
			foreach ( $tags_to_check as $tag => $attribute ) {
				$nodes = $dom->getElementsByTagName( $tag );
				foreach ( $nodes as $node ) {
					if ( ! $node instanceof DOMElement || ! $node->hasAttribute( $attribute ) ) {
						continue;
					}
					$url = $node->getAttribute( $attribute );
					if ( 'http' === wp_parse_url( $url, PHP_URL_SCHEME ) && ! $this->is_upgraded_url_valid( $url ) ) {
						$insecure_urls[ $content_type ][] = $url;
					}
				}
			}
		}
		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_previous_state );

		return $insecure_urls;
	}

	/**
	 * Whether a request to the HTTPS version of a URL succeeds.
	 *
	 * When using the Upgrade-Insecure-Requests header,
	 * HTTP URLs will be upgraded to HTTPS.
	 * But if they fail, there is no fallback.
	 * So this finds whether the upgraded HTTPS URL succeeds.
	 *
	 * @param string $url The HTTP URL to convert to HTTPS and make a request for.
	 * @return bool Whether the upgraded URL succeeds.
	 */
	public function is_upgraded_url_valid( $url ) {
		$upgraded_url = preg_replace( '#^http(?=://)#', 'https', $url );
		$response     = wp_remote_get( $upgraded_url );
		return 200 === wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Sets mock insecure content, only for testing.
	 *
	 * @todo: Remove this later, as this is only for development.
	 */
	public function set_mock_insecure_content() {
		update_option(
			self::INSECURE_CONTENT_OPTION_NAME,
			array(
				'passive' => array(
					'http://example.com/foo-passive',
					'http://example.com/bar-passive',
					'http://example.com/baz-passive',
					'http://example.com/var-passive',
					'http://example.com/something-passive',
					'http://example.com/example-passive',
					'http://example.com/this-passive',
					'http://example.com/mab-passive',
				),
				'active'  => array(
					'http://example.com/foo-active',
					'http://example.com/bar-active',
					'http://example.com/baz-active',
					'http://example.com/var-active',
					'http://example.com/ex-active',
					'http://example.com/something-active',
					'http://example.com/example-active',
					'http://example.com/this-active',
					'http://example.com/mab-active',
					'http://example.com/zoom-active',
					'http://example.com/this-active',
					'http://example.com/won-active',
				),
			)
		);
	}

	/**
	 * If the 'cron_request' arguments include an HTTPS URL, this ensures sslverify is false.
	 *
	 * Prevents an issue if HTTPS breaks,
	 * where there would be a failed attempt to verify HTTPS.
	 *
	 * @param array $request The cron request arguments.
	 * @return array $request The filtered cron request arguments.
	 */
	public function ensure_http_if_sslverify( $request ) {
		if ( 'https' === wp_parse_url( $request['url'], PHP_URL_SCHEME ) ) {
			$request['args']['sslverify'] = false;
		}
		return $request;
	}
}
