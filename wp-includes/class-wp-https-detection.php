<?php
/**
 * WP_HTTPS_Detection class.
 *
 * @package PWA
 */

/**
 * WP_HTTPS_Detection class.
 */
final class WP_HTTPS_Detection {

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
	 * The tag names for insecure content.
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/Security/Mixed_content
	 * @var array
	 */
	public $insecure_content_tags = array(
		'img',
		'audio',
		'video',
		'script',
		'link',
		'iframe',
	);

	/**
	 * Initializes the object.
	 */
	public function init() {
		/**
		 * Filter tag to disable HTTPS detection and UI, such as when redirection
		 * is handled outside of WordPress.
		 *
		 * @param bool $disabled Disable detection and UI.
		 */
		if ( apply_filters( 'wp_https_detection_ui_disabled', false ) ) {
			return;
		}

		add_action( 'init', array( $this, 'schedule_cron' ) );
		add_action( self::CRON_HOOK, array( $this, 'update_https_support_options' ) );
		add_filter( 'cron_request', array( $this, 'conditionally_prevent_sslverify' ), PHP_INT_MAX );

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
		$support_errors = new WP_Error();

		$response = wp_remote_request(
			home_url( '/', 'https' ),
			array(
				'headers'   => array(
					'Cache-Control' => 'no-cache',
				),
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			$unverified_response = wp_remote_request(
				home_url( '/', 'https' ),
				array(
					'headers'   => array(
						'Cache-Control' => 'no-cache',
					),
					'sslverify' => false,
				)
			);

			if ( is_wp_error( $unverified_response ) ) {
				$support_errors->errors = array_merge( $unverified_response->errors );
			} else {
				$support_errors->add(
					'ssl_verification_failed',
					$response->get_error_message()
				);
			}
			$response = $unverified_response;
		}

		$body = null;
		if ( ! is_wp_error( $response ) ) {
			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				$support_errors->add( 'response_error', wp_remote_retrieve_response_message( $response ) );
			} else {
				$body = wp_remote_retrieve_body( $response );
				if ( ! $this->has_proper_manifest( $body ) ) {
					$support_errors->add(
						'invalid_https_validation_source',
						__( 'There was an issue in the request for HTTPS verification.', 'pwa' )
					);
				}
			}
		}

		update_option(
			self::HTTPS_SUPPORT_OPTION_NAME,
			empty( $support_errors->errors ) ? true : $support_errors
		);

		if ( $body ) {
			update_option( self::INSECURE_CONTENT_OPTION_NAME, $this->get_insecure_content( $body ) );
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
	 * Gets the URLs with insecure content in the response.
	 *
	 * This includes images, scripts, and stylesheets.
	 * But it only looks at the HTML document in the $response body.
	 * If a script requests an insecure script, this will not detect that.
	 *
	 * @param string $body The response body from a wp_remote_request().
	 * @return array The URLs for insecure content.
	 */
	public function get_insecure_content( $body ) {
		$libxml_previous_state = libxml_use_internal_errors( true );

		$dom = new DOMDocument( '1.0' );
		$dom->loadHTML( $body );
		$insecure_urls = array();

		foreach ( $this->insecure_content_tags as $tag ) {
			$nodes     = $dom->getElementsByTagName( $tag );
			$attribute = 'link' === $tag ? 'href' : 'src';
			foreach ( $nodes as $node ) {
				if (
					! $node instanceof DOMElement
					||
					! $node->hasAttribute( $attribute )
					||
					// Other <link> elements are allowed to have non-HTTPS URLs, like <link rel="profile" href="http://gmpg.org/xfn/11">.
					( 'link' === $tag && 'stylesheet' !== $node->getAttribute( 'rel' ) )
				) {
					continue;
				}
				$url = $node->getAttribute( $attribute );
				if ( 'http' === wp_parse_url( $url, PHP_URL_SCHEME ) ) {
					$insecure_urls[] = $url;
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
	 * Prevents an issue if HTTPS breaks, where there would be a failed attempt to verify HTTPS.
	 *
	 * @param array $request The cron request arguments.
	 * @return array $request The filtered cron request arguments.
	 */
	public function conditionally_prevent_sslverify( $request ) {
		if ( 'https' === wp_parse_url( $request['url'], PHP_URL_SCHEME ) ) {
			$request['args']['sslverify'] = false;
		}
		return $request;
	}
}
