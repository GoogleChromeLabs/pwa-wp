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
	 * The cron hook to check https support.
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
	 * Secret for the https detection request.
	 *
	 * @var string
	 */
	const REQUEST_SECRET = 'https_detection_secret';

	/**
	 * Query arg key for the https detection token.
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
	 */
	public function update_option_https_support() {
		update_option( self::HTTPS_SUPPORT_OPTION_NAME, $this->is_https_supported() );
	}

	/**
	 * Makes a request to the home URL to determine whether HTTPS is supported.
	 *
	 * Returns true if the URL has the scheme of HTTPS, and it has the correct token.
	 * The token is only to verify that the request is for the same site.
	 * Also, in wp_remote_get(), 'sslverify' is true by default.
	 * This method is private, as it's not part of the public API to get HTTPS support.
	 * Instead, please use get_option( WP_HTTPS_Detection::HTTPS_SUPPORT_OPTION_NAME ).
	 *
	 * @return boolean Whether HTTPS is supported.
	 */
	private function is_https_supported() {
		$request = wp_remote_get( add_query_arg(
			self::REQUEST_TOKEN_QUERY_ARG,
			$this->get_token(),
			home_url( '/', 'http' )
		) );

		if ( is_wp_error( $request ) || ! method_exists( $request['http_response'], 'get_response_object' ) ) {
			return false;
		}

		$response   = $request['http_response']->get_response_object();
		$parsed_url = wp_parse_url( $response->url );
		if ( ! $response->success || ! isset( $parsed_url['query'] ) ) {
			return false;
		}

		$query_args = wp_parse_args( $parsed_url['query'] );
		$is_https   = (
			isset( $parsed_url['scheme'], $query_args[ self::REQUEST_TOKEN_QUERY_ARG ] )
			&&
			'https' === $parsed_url['scheme']
			&&
			$this->get_token() === $query_args[ self::REQUEST_TOKEN_QUERY_ARG ]
		);

		return $is_https;
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
}
