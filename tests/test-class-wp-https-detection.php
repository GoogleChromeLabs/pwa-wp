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
	 * The message passed to wp_die().
	 *
	 * @var string
	 */
	public $wp_die_message;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->instance = new WP_HTTPS_Detection();
		add_filter( 'wp_die_handler', array( $this, 'get_handler_to_prevent_exiting' ), 11 );
		add_filter( 'http_response', array( $this, 'mock_successful_response' ) );
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
		$this->assertEquals( 10, has_action( 'parse_query', array( $this->instance, 'verify_https_check' ) ) );
	}

	/**
	 * Test is_https_supported.
	 *
	 * @covers WP_HTTPS_Detection::is_https_supported()
	 */
	public function test_is_https_supported() {
		$this->assertFalse( WP_HTTPS_Detection::is_https_supported() );

		update_option( WP_HTTPS_Detection::HTTPS_SUPPORT_OPTION_NAME, true );
		$this->assertTrue( WP_HTTPS_Detection::is_https_supported() );

		update_option( WP_HTTPS_Detection::HTTPS_SUPPORT_OPTION_NAME, false );
		$this->assertFalse( WP_HTTPS_Detection::is_https_supported() );
	}

	/**
	 * Test get_insecure_content.
	 *
	 * @covers WP_HTTPS_Detection::get_insecure_content()
	 */
	public function test_get_insecure_content() {
		$html_boilerplate = '<!DOCTYPE html><html><head><meta http-equiv="content-type" content="text/html; charset=' . get_bloginfo( 'charset' ) . '"></head><body>%s</body></html>';
		$insecure_img_src = 'http://example.com/baz';
		$body             = sprintf(
			$html_boilerplate,
			sprintf(
				'<img src="%s">',
				$insecure_img_src
			)
		);
		$this->assertEquals( array( 'passive' => array( $insecure_img_src ) ), $this->instance->get_insecure_content( compact( 'body' ) ) );

		$insecure_audio_src = 'http://example.com/foo';
		$insecure_video_src = 'http://example.com/bar';
		$body               = sprintf(
			$html_boilerplate,
			sprintf(
				'<audio src="%s"></audio><video src="%s"></video>',
				$insecure_audio_src,
				$insecure_video_src
			)
		);
		$insecure_urls      = $this->instance->get_insecure_content( compact( 'body' ) );
		$this->assertTrue( in_array( $insecure_audio_src, $insecure_urls['passive'], true ) );
		$this->assertTrue( in_array( $insecure_video_src, $insecure_urls['passive'], true ) );

		// Allow interpolating tags into the <head>.
		$html_boilerplate    = '<!DOCTYPE html><html><head>%s</head><body>%s</body></html>';
		$insecure_script_src = 'http://example.com/script';
		$insecure_link_href  = 'http://example.com/link';
		$body                = sprintf(
			$html_boilerplate,
			sprintf(
				'<script src="%s"></script><link href="%s" rel="stylesheet">', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
				$insecure_script_src,
				$insecure_link_href
			),
			sprintf(
				'<audio src="%s"></audio>',
				$insecure_audio_src
			)
		);
		$this->assertEquals(
			array(
				'passive' => array( $insecure_audio_src ),
				'active'  => array( $insecure_script_src, $insecure_link_href ),
			),
			$this->instance->get_insecure_content( compact( 'body' ) )
		);
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
		$this->assertTrue( get_option( WP_HTTPS_Detection::HTTPS_SUPPORT_OPTION_NAME ) );

		// There should be HTTPS support, as check_https_support() should return true.
		add_filter( 'http_response', array( $this, 'mock_error_response' ) );
		$this->instance->update_option_https_support();
		$this->assertTrue( get_option( WP_HTTPS_Detection::HTTPS_SUPPORT_OPTION_NAME ) );
		remove_filter( 'http_response', array( $this, 'mock_error_response' ) );

		// The response is a 301, so the option value should be false.
		add_filter( 'http_response', array( $this, 'mock_incorrect_response' ) );
		$this->instance->update_option_https_support();
		$this->assertFalse( get_option( WP_HTTPS_Detection::HTTPS_SUPPORT_OPTION_NAME ) );
		remove_filter( 'http_response', array( $this, 'mock_incorrect_response' ) );
	}

	/**
	 * Test check_https_support.
	 *
	 * @covers WP_HTTPS_Detection::check_https_support()
	 */
	public function test_check_https_support() {
		$this->assertTrue( $this->instance->check_https_support() );

		// The response is a WP_Error.
		add_filter( 'http_response', array( $this, 'mock_error_response' ) );
		$this->assertTrue( is_wp_error( $this->instance->check_https_support() ) );
		remove_filter( 'http_response', array( $this, 'mock_error_response' ) );

		// The response should cause check_https_support() to be false.
		add_filter( 'http_response', array( $this, 'mock_incorrect_response' ) );
		$this->assertFalse( $this->instance->check_https_support() );
		remove_filter( 'http_response', array( $this, 'mock_incorrect_response' ) );
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
	 * Test verify_https_check.
	 *
	 * @covers WP_Query::verify_https_check()
	 */
	public function test_verify_https_check() {
		$query_var = wp_rand();
		$wp_query  = new WP_Query( array(
			WP_HTTPS_Detection::REQUEST_QUERY_VAR => $query_var,
		) );

		// The query var is present in WP_Query, so this should pass the message to wp_die().
		$this->instance->verify_https_check( $wp_query );
		$this->assertEquals( wp_hash( $query_var, 'nonce' ), $this->wp_die_message );

		$this->wp_die_message = null;
		$wp_query             = new WP_Query();

		// The query var is not present in WP_Query, so this shouldn't call wp_die().
		$this->instance->verify_https_check( $wp_query );
		$this->assertEquals( null, $this->wp_die_message );

	}

	/**
	 * Alters the response, to simulate a scenario where HTTPS isn't supported.
	 *
	 * @param WP_HTTP_Requests_Response $response The response object.
	 * @return WP_HTTP_Requests_Response $response The filtered response object.
	 */
	public function mock_incorrect_response( $response ) {
		$response['response']['code'] = 301;
		return $response;
	}

	/**
	 * Alters the response to be a WP_Error.
	 *
	 * @return WP_Error An error response.
	 */
	public function mock_error_response() {
		return new WP_Error();
	}

	/**
	 * Gets the handler to prevent exiting from the tests.
	 *
	 * @return array
	 */
	public function get_handler_to_prevent_exiting() {
		return array( $this, 'prevent_exiting_from_tests' );
	}

	/**
	 * Prevents exiting early from tests, by overriding the handler for wp_die().
	 *
	 * @param string $message The message passed to wp_die().
	 */
	public function prevent_exiting_from_tests( $message ) {
		$this->wp_die_message = $message;
	}

	/**
	 * Overrides the response body from wp_remote_get().
	 *
	 * This is needed because WP_HTTPS_Detection::verify_https_check() calls wp_die(),
	 * and the wp_die() handler had to be overridden to prevent the tests from stopping.
	 * So this mocks the expected response.
	 *
	 * @return array The response.
	 */
	public function mock_successful_response() {
		return array(
			'body'     => sprintf( '<html><body>%s</body></html>', wp_hash( $this->instance->query_var_number, 'nonce' ) ),
			'response' => array(
				'code' => 200,
			),
		);
	}
}
