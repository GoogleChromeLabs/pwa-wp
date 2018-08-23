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
	const CRON_HOOK = 'wp_https_support_check';

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
	 * But if the request is a WP_Error, this does not update the option for insecure content.
	 */
	public function update_https_support_options() {
		// If the home and siteurl values are already HTTPS, there's no need to update these options for the UI, as the UI won't display.
		if ( $this->is_currently_https() ) {
			return;
		}

		$https_support_response = $this->check_https_support();
		update_option(
			self::HTTPS_SUPPORT_OPTION_NAME,
			! is_wp_error( $https_support_response ) && 200 === wp_remote_retrieve_response_code( $https_support_response )
		);

		if ( ! is_wp_error( $https_support_response ) ) {
			$insecure_content = $this->get_insecure_content( $https_support_response );
			if ( ! is_wp_error( $insecure_content ) ) {
				update_option( self::INSECURE_CONTENT_OPTION_NAME, $insecure_content );
			}
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
			home_url( '/', 'https' ),
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
		$libxml_previous_state = libxml_use_internal_errors( true );
		$dom                   = new DOMDocument( '1.0' );
		$dom->loadHTML( $body );
		$has_manifest = false;

		foreach ( $dom->getElementsByTagName( 'link' ) as $link ) {
			if ( $link->getAttribute( 'href' ) === set_url_scheme( rest_url( WP_Web_App_Manifest::REST_NAMESPACE . WP_Web_App_Manifest::REST_ROUTE ), 'https' ) ) {
				$has_manifest = true;
				break;
			}
		}

		// Store $has_manifest in a variable instead of returning true the foreach loop, so these functions always run.
		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_previous_state );

		return $has_manifest;
	}

	/**
	 * Gets the URLs with insecure content in the response that could not be upgraded to HTTPS.
	 *
	 * Finds insecure content in the HTML document, including images, scripts, and stylesheets.
	 * And attempts to upgrade the HTTP URL to HTTPS with is_upgraded_url_valid().
	 * If a request to the upgraded URL does not succeed, this includes it in the returned URL(s).
	 *
	 * @param array $response The response from a wp_remote_request().
	 * @return array|WP_Error $insecure_content[][] {
	 *     The insecure content, by type.
	 *
	 *     @type string[]  $passive The passive insecure URLs.
	 *     @type string[]  $active  The active insecure URLs.
	 * }
	 */
	public function get_insecure_content( $response ) {
		$libxml_previous_state = libxml_use_internal_errors( true );
		$body                  = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return new WP_Error(
				'insecure_content_request_empty_body',
				__( 'The request for insecure content returned an empty body.', 'pwa' )
			);
		}

		$dom = new DOMDocument( '1.0' );
		$dom->loadHTML( $body );
		$insecure_urls = array();

		foreach ( $this->insecure_content_types as $content_type => $tags_to_check ) {
			foreach ( $tags_to_check as $tag => $attribute ) {
				$nodes = $dom->getElementsByTagName( $tag );
				foreach ( $nodes as $node ) {
					if (
						! $node instanceof DOMElement
						||
						! $node->hasAttribute( $attribute )
						||
						( 'link' === $tag && 'stylesheet' !== $node->getAttribute( 'rel' ) )
					) {
						continue;
					}
					$url = $node->getAttribute( $attribute );
					if ( 'http' === wp_parse_url( $url, PHP_URL_SCHEME ) ) {
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
