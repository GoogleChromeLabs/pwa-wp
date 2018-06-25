<?php
/**
 * Tests for class WP_HTTPS_Detection.
 *
 * @package PWA
 */

/**
 * Tests for class WP_HTTPS_Detection.
 */
class Test_WP_HTTPS_Detection extends WP_UnitTestCase {

	/**
	 * Tested instance.
	 *
	 * @var WP_HTTPS_Detection
	 */
	public $instance;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->instance = new WP_HTTPS_Detection();
	}

	/**
	 * Test init.
	 *
	 * @covers WP_HTTPS_Detection::init()
	 */
	public function test_init() {
		$this->instance->init();
		$this->assertEquals( 10, has_action( 'wp', array( $this->instance, 'schedule_cron' ) ) );
		$this->assertEquals( 10, has_action( WP_HTTPS_Detection::CRON_HOOK, array( $this->instance, 'update_option_https_support' ) ) );
		$this->assertEquals( PHP_INT_MAX, has_filter( 'cron_request', array( $this->instance, 'ensure_http_if_sslverify' ) ) );
	}

	/**
	 * Test schedule_cron.
	 *
	 * @covers WP_HTTPS_Detection::schedule_cron()
	 */
	public function test_schedule_cron() {
		$this->assertFalse( wp_next_scheduled( WP_HTTPS_Detection::CRON_HOOK ) );

		$this->instance->schedule_cron();
		$this->assertNotFalse( wp_next_scheduled( WP_HTTPS_Detection::CRON_HOOK ) );

		$cron_array       = _get_cron_array();
		$https_check_cron = end( $cron_array );
		$this->assertEquals(
			array(
				'args'     => array(),
				'interval' => HOUR_IN_SECONDS,
				'schedule' => 'hourly',
			),
			reset( $https_check_cron[ WP_HTTPS_Detection::CRON_HOOK ] )
		);
	}

	/**
	 * Test update_option_https_support.
	 *
	 * @covers WP_HTTPS_Detection::update_option_https_support()
	 */
	public function test_update_option_https_support() {
		$this->instance->update_option_https_support();
		$this->assertFalse( get_option( WP_HTTPS_Detection::HTTPS_SUPPORT_OPTION_NAME ) );

		// There should be HTTPS support, as is_https_supported() should return true.
		add_filter( 'http_response', array( $this, 'mock_correct_response' ) );
		$this->instance->update_option_https_support();
		$this->assertTrue( get_option( WP_HTTPS_Detection::HTTPS_SUPPORT_OPTION_NAME ) );
		remove_filter( 'http_response', array( $this, 'mock_correct_response' ) );
	}

	/**
	 * Test is_https_supported.
	 *
	 * @covers WP_HTTPS_Detection::is_https_supported()
	 */
	public function test_is_https_supported() {
		$this->assertFalse( $this->instance->is_https_supported() );

		// The response is HTTPS, but the token was not present.
		add_filter( 'http_response', array( $this, 'mock_incorrect_response' ) );
		$this->assertFalse( $this->instance->is_https_supported() );
		remove_filter( 'http_response', array( $this, 'mock_incorrect_response' ) );

		// The response is HTTPS with the token present, so is_https_supported() should return true.
		add_filter( 'http_response', array( $this, 'mock_correct_response' ) );
		$this->assertTrue( $this->instance->is_https_supported() );
		remove_filter( 'http_response', array( $this, 'mock_correct_response' ) );
	}

	/**
	 * Test get_token.
	 *
	 * @covers WP_HTTPS_Detection::get_token()
	 */
	public function test_get_token() {
		$this->assertEquals( wp_hash( WP_HTTPS_Detection::REQUEST_SECRET ), $this->instance->get_token() );
	}

	/**
	 * Test ensure_http_if_sslverify.
	 *
	 * @covers WP_HTTPS_Detection::ensure_http_if_sslverify()
	 */
	public function test_ensure_http_if_sslverify() {

		// The arguments don't have an HTTPS URL and 'sslverify' isn't true, so they shouldn't change.
		$http_url               = 'http://example.com';
		$allowed_cron_arguments = array(
			'url'  => $http_url,
			'args' => array(
				'sslverify' => false,
			),
		);
		$this->assertEquals( $allowed_cron_arguments, $this->instance->ensure_http_if_sslverify( $allowed_cron_arguments ) );

		// With an HTTPS URL and 'sslverify' => true, this should change 'sslverify' to false.
		$https_url                 = 'https://example.com';
		$disallowed_cron_arguments = array(
			'url'  => $https_url,
			'args' => array(
				'sslverify' => true,
			),
		);
		$cron_arguments_sslverify_true['args']['sslverify'] = true;
		$filtered_cron_arguments                            = $this->instance->ensure_http_if_sslverify( $disallowed_cron_arguments );
		$this->assertFalse( $filtered_cron_arguments['args']['sslverify'] );
		$this->assertEquals( $https_url, $filtered_cron_arguments['url'] );

		// The URL is HTTP, so 'sslverify' => true is allowed, and the arguments shouldn't change.
		$allowed_cron_arguments_http        = $disallowed_cron_arguments;
		$allowed_cron_arguments_http['url'] = $http_url;
		$this->assertEquals( $allowed_cron_arguments_http, $this->instance->ensure_http_if_sslverify( $allowed_cron_arguments_http ) );
	}

	/**
	 * Alter the response to be HTTPS, but without the token.
	 *
	 * Still, this should not cause is_htts_supported() to return true.
	 * It does not include the request token.
	 *
	 * @param WP_HTTP_Requests_Response $response The response object.
	 * @return WP_HTTP_Requests_Response $response The filtered response object.
	 */
	public function mock_incorrect_response( $response ) {
		$response['http_response']->get_response_object()->url = 'https://example.com';
		return $response;
	}

	/**
	 * Alter the response, which should cause is_https_supported() to return true because it includes the token.
	 *
	 * @param WP_HTTP_Requests_Response $response The response object.
	 * @return WP_HTTP_Requests_Response $response The filtered response object.
	 */
	public function mock_correct_response( $response ) {
		$response['http_response']->get_response_object()->url = add_query_arg(
			WP_HTTPS_Detection::REQUEST_TOKEN_QUERY_ARG,
			$this->instance->get_token(),
			'https://example.com'
		);
		return $response;
	}
}
