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
	 * The option name for whether HTTPS is supported.
	 *
	 * @var string
	 */
	const HTTPS_SUPPORT_OPTION_NAME = 'is_https_supported';

	/**
	 * The query var key for HTTPS detection, used to verify that the request is from the same origin.
	 *
	 * @var string
	 */
	const REQUEST_QUERY_VAR = 'https_detection_token';

	/**
	 * The number passed to the query var.
	 *
	 * @var int
	 */
	public $query_var_number;

	/**
	 * Inits the class.
	 */
	public function init() {
		add_action( 'wp', array( $this, 'schedule_cron' ) );
		add_action( self::CRON_HOOK, array( $this, 'update_option_https_support' ) );
		add_filter( 'cron_request', array( $this, 'ensure_http_if_sslverify' ), PHP_INT_MAX );
		add_action( 'parse_query', array( $this, 'verify_https_check' ) );
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
	 * To ensure the request is from the correct origin,
	 * this passes a query var of a random number to the request.
	 * Then, the 'parse_query' hook verify_https_check() looks for the query var.
	 * If it's present, it calls wp_die() with a hash of the query var.
	 * This method then ensures that the hash is present in the response body,
	 *
	 * @return boolean|WP_Error Whether HTTPS is supported, or a WP_Error.
	 */
	public function check_https_support() {
		$this->query_var_number = wp_rand();
		$response               = wp_remote_request(
			add_query_arg(
				self::REQUEST_QUERY_VAR,
				$this->query_var_number,
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

		if ( false === strpos( wp_remote_retrieve_body( $response ), wp_hash( $this->query_var_number, 'nonce' ) ) ) {
			return new WP_Error(
				'invalid_https_validation_source',
				__( 'There was an issue in the request for HTTPS verification. It might not have been from the same origin.', 'default' )
			);
		}

		return 200 === wp_remote_retrieve_response_code( $response );
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
		if ( 0 === strpos( $request['url'], 'https://' ) ) {
			$request['args']['sslverify'] = false;
		}
		return $request;
	}

	/**
	 * If the query has the query var, call die() with the hashed query var.
	 *
	 * This uses wp_die() instead of die(),
	 * because it allows filtering the handler: 'wp_die_handler'.
	 * This prevents stopping the unit tests.
	 *
	 * @param WP_Query $wp_query The query object.
	 */
	public function verify_https_check( $wp_query ) {
		$query_var = $wp_query->get( self::REQUEST_QUERY_VAR );
		if ( ! empty( $query_var ) ) {
			wp_die( wp_hash( $query_var, 'nonce' ) ); // WPCS: XSS ok.
		}
	}
}
