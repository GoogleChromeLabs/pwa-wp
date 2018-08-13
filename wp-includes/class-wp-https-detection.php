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
	 * The query var key for HTTPS detection, used to verify that the request is from the same origin.
	 *
	 * @var string
	 */
	const REQUEST_QUERY_VAR = 'https_detection_token';

	/**
	 * The tag names and attributes for insecure content types.
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
		add_action( self::CRON_HOOK, array( $this, 'update_option_https_support' ) );
		add_filter( 'cron_request', array( $this, 'ensure_http_if_sslverify' ), PHP_INT_MAX );
		$wp_https_ui = new WP_HTTPS_UI();
		$wp_https_ui->init();

		// @todo: remove this, as it's only for development.
		add_action( 'init', array( $this, 'set_mock_insecure_content' ) );
	}

	/**
	 * Gets whether HTTPS is supported, using the stored result of the loopback request.
	 *
	 * @return boolean
	 */
	public static function is_https_supported() {
		return (bool) get_option( self::HTTPS_SUPPORT_OPTION_NAME );
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
	 * Makes a request to find whether HTTPS is supported, and stores the result in an option.
	 * If the request is a WP_Error, this does not update the option.
	 */
	public function update_https_support_options() {
		$https_support_response = $this->check_https_support();
		if ( ! is_wp_error( $https_support_response ) ) {
			update_option( self::HTTPS_SUPPORT_OPTION_NAME, 200 === wp_remote_retrieve_response_code( $https_support_response ) );
			update_option( self::INSECURE_CONTENT_OPTION_NAME, $this->get_insecure_content( $https_support_response ) );
		}
	}

	/**
	 * Makes a request to the home URL to determine whether HTTPS is supported.
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
	 * Gets the URLs with insecure content in the response.
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
		if ( preg_match( '#^https://#', $request['url'] ) ) {
			$request['args']['sslverify'] = false;
		}
		return $request;
	}
}
