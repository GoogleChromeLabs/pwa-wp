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
	 * Option name for whether HTTPS is supported.
	 *
	 * @var string
	 */
	const HTTPS_SUPPORT_OPTION_NAME = 'is_https_supported';

	/**
	 * Secret for the HTTPS detection request.
	 *
	 * @var string
	 */
	const REQUEST_SECRET = 'https_detection_secret';

	/**
	 * Query arg key for the HTTPS detection token.
	 *
	 * @var string
	 */
	const REQUEST_TOKEN_QUERY_ARG = 'https_detection_token';

	/**
	 * Inits the class.
	 */
	public function init() {
		add_action( 'wp', array( $this, 'schedule_cron' ) );
		add_action( self::CRON_HOOK, array( $this, 'update_option_https_support' ) );
		add_filter( 'cron_request', array( $this, 'ensure_http_if_sslverify' ), PHP_INT_MAX );
	}

	/**
	 * Schedules a cron event to check for HTTPS support.
	 */
	public function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Makes a request to find whether HTTPS is supported, and stores the result in an option.
	 * If the request is a WP_Error, don't update the option.
	 */
	public function update_option_https_support() {
		$https_support = $this->check_https_support();
		if ( is_bool( $https_support ) ) {
			update_option( self::HTTPS_SUPPORT_OPTION_NAME, $https_support );
		}
	}

	/**
	 * Makes a request to the home URL to determine whether HTTPS is supported.
	 *
	 * Returns true if the URL has the scheme of HTTPS, and it has the correct token.
	 * The token is only to verify that the request is for the same site.
	 * Also, in wp_remote_get(), 'sslverify' is true by default.
	 *
	 * @return boolean|WP_Error Whether HTTPS is supported, or a WP_Error if the loopback request resulted in an error.
	 */
	public function check_https_support() {
		$response = wp_remote_request(
			add_query_arg(
				self::REQUEST_TOKEN_QUERY_ARG,
				$this->get_token(),
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
		return 200 === wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Gets the token, based on the secret.
	 *
	 * This token is not intended for security, as much as ensuring that the request is for this site.
	 * By calling wp_hash(), it uses this site's salts, which should be different from other sites.
	 *
	 * @return string $token The token for the request.
	 */
	public function get_token() {
		return wp_hash( self::REQUEST_SECRET );
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
		if ( 0 === strpos( $request['url'], 'https' ) ) {
			if ( isset( $request['args'] ) ) {
				$request['args']['sslverify'] = false;
			} else {
				$request['args'] = array( 'sslverify' => false );
			}
		}
		return $request;
	}

}
