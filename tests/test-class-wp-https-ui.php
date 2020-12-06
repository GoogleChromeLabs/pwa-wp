<?php
/**
 * Tests for class WP_HTTPS_UI.
 *
 * @package PWA
 */

/**
 * Tests for class WP_HTTPS_UI.
 */
class Test_WP_HTTPS_UI extends WP_UnitTestCase {

	/**
	 * A mock HTTPS URL.
	 *
	 * @var string
	 */
	const HTTPS_URL = 'https://baz.com';

	/**
	 * A mock HTTP URL.
	 *
	 * @var string
	 */
	const HTTP_URL = 'http://baz.com';

	/**
	 * A mock URL that has a relative protocol.
	 *
	 * @var string
	 */
	const PROTOCOL_RELATIVE_URL = '//baz.com';

	/**
	 * A mock URL with no protocol.
	 *
	 * @var string
	 */
	const NO_PROTOCOL_URL = 'baz.com';

	/**
	 * Tested instance.
	 *
	 * @var WP_HTTPS_UI
	 */
	public $instance;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$wp_https_detection = new WP_HTTPS_Detection();
		$this->instance     = new WP_HTTPS_UI( $wp_https_detection );
	}

	/**
	 * Test __construct.
	 *
	 * @covers WP_HTTPS_UI::__construct()
	 */
	public function test_construct() {
		$this->assertEquals( 'WP_HTTPS_Detection', get_class( $this->instance->wp_https_detection ) );
	}

	/**
	 * Test init.
	 *
	 * @covers WP_HTTPS_UI::init()
	 */
	public function test_init() {
		$this->instance->init();
		$this->assertEquals( 10, has_action( 'admin_init', array( $this->instance, 'init_admin' ) ) );
		$this->assertEquals( 10, has_action( 'init', array( $this->instance, 'filter_site_url_and_home' ) ) );
		$this->assertEquals( 10, has_action( 'init', array( $this->instance, 'filter_header' ) ) );
		$this->assertEquals( 11, has_action( 'template_redirect', array( $this->instance, 'conditionally_redirect_to_https' ) ) );
	}

	/**
	 * Test register_settings.
	 *
	 * @covers WP_HTTPS_UI::register_settings()
	 */
	public function test_register_settings() {
		global $new_whitelist_options, $new_allowed_options, $wp_registered_settings;

		if ( isset( $new_whitelist_options ) && ! isset( $new_allowed_options ) ) {
			$new_allowed_options = &$new_whitelist_options;
		}

		$base_expected_settings = array(
			'type'         => 'boolean',
			'group'        => WP_HTTPS_UI::OPTION_GROUP,
			'description'  => '',
			'show_in_rest' => false,
		);

		$expected_https_settings = array_merge(
			$base_expected_settings,
			array(
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
		$this->instance->register_settings();
		$this->assertTrue( in_array( WP_HTTPS_UI::UPGRADE_HTTPS_OPTION, $new_allowed_options[ WP_HTTPS_UI::OPTION_GROUP ], true ) );
		$this->assertEquals(
			$expected_https_settings,
			$wp_registered_settings[ WP_HTTPS_UI::UPGRADE_HTTPS_OPTION ]
		);
	}

	/**
	 * Test add_settings_field.
	 *
	 * @covers WP_HTTPS_UI::add_settings_field()
	 */
	public function test_add_settings_field() {
		global $wp_settings_fields;

		add_filter( 'set_url_scheme', array( $this, 'convert_to_http' ) );
		$this->instance->add_settings_field();
		$this->assertEquals(
			array(
				'id'       => WP_HTTPS_UI::HTTPS_SETTING_ID,
				'title'    => 'HTTPS',
				'callback' => array( $this->instance, 'render_https_settings' ),
				'args'     => array(),
			),
			$wp_settings_fields[ WP_HTTPS_UI::OPTION_GROUP ]['default'][ WP_HTTPS_UI::HTTPS_SETTING_ID ]
		);
	}

	/**
	 * Test render_https_settings.
	 *
	 * @covers WP_HTTPS_UI::render_https_settings()
	 */
	public function test_render_https_settings() {
		// Set the option value, which should appear in the <input type="checkbox"> elements.
		update_option( WP_HTTPS_Detection::HTTPS_SUPPORT_OPTION_NAME, true );
		update_option( WP_HTTPS_UI::UPGRADE_HTTPS_OPTION, WP_HTTPS_UI::OPTION_CHECKED_VALUE );
		update_option( 'siteurl', self::HTTPS_URL );
		update_option( 'home', self::HTTPS_URL );
		add_filter( 'set_url_scheme', array( $this->instance, 'convert_to_https' ) );
		update_option(
			WP_HTTPS_Detection::INSECURE_CONTENT_OPTION_NAME,
			array( self::HTTP_URL )
		);

		ob_start();
		$this->instance->render_https_settings();
		$output = ob_get_clean();

		$this->assertContains( WP_HTTPS_UI::OPTION_CHECKED_VALUE, $output );
		$this->assertContains( WP_HTTPS_UI::UPGRADE_HTTPS_OPTION, $output );
		$this->assertContains( 'HTTPS is essential to securing your WordPress site, we strongly suggest upgrading to it.', $output );
		$this->assertContains( 'While we will try to fix these links automatically, you might check to be sure your pages work as expected.', $output );
		$this->assertContains( self::HTTP_URL, $output );
		$this->assertNotContains( 'class="hidden"', $output );
	}

	/**
	 * Test get_truncated_url.
	 *
	 * @covers WP_HTTPS_UI::get_truncated_url()
	 */
	public function test_get_truncated_url() {
		// When a URL is under the maximum character length, this method shouldn't alter it.
		$short_url = 'http://example.com/foo-bar';
		$this->assertEquals( $short_url, $this->instance->get_truncated_url( $short_url ) );

		// This URL shouldn't be altered, as it's still not over the limit.
		$url_at_limit_but_not_above = 'http://example.com/foo-passive/foo-bar-foo-bar-foo-bar-foo-bar-foo-bar-foo-';
		$this->assertEquals( $url_at_limit_but_not_above, $this->instance->get_truncated_url( $url_at_limit_but_not_above ) );

		// The URL is over the limit, so this should truncate it and add the ellipsis.
		$url_over_limit = $url_at_limit_but_not_above . 'baz';
		$this->assertEquals( $url_at_limit_but_not_above . '&hellip;', $this->instance->get_truncated_url( $url_over_limit ) );
	}

	/**
	 * Test filter_site_url_and_home.
	 *
	 * @covers WP_HTTPS_UI::filter_site_url_and_home()
	 */
	public function test_filter_site_url_and_home() {
		$initial_home_url = 'http://example.net';
		$initial_site_url = 'http://example.org';
		update_option( 'home', $initial_home_url );
		update_option( 'siteurl', $initial_site_url );
		remove_filter( 'option_home', '_config_wp_home' );
		remove_filter( 'option_siteurl', '_config_wp_siteurl' );

		// Simulate 'HTTPS Upgrade' not being selected in the UI, where the filters shouldn't convert the URLs to HTTPS.
		$this->instance->filter_site_url_and_home();
		$this->assertNotEquals( 11, has_filter( 'option_home', array( $this->instance, 'convert_to_https' ) ) );
		$this->assertNotEquals( 11, has_filter( 'option_siteurl', array( $this->instance, 'convert_to_https' ) ) );
		$this->assertSame( $initial_home_url, get_option( 'home' ) );
		$this->assertSame( $initial_site_url, get_option( 'siteurl' ) );
		$this->assertSame( $initial_home_url, home_url() );
		$this->assertSame( $initial_site_url, site_url() );

		// Simulate 'HTTPS Upgrade' being selected, where the filters should convert the URLs to HTTPS.
		update_option( WP_HTTPS_UI::UPGRADE_HTTPS_OPTION, WP_HTTPS_UI::OPTION_CHECKED_VALUE );
		$this->instance->filter_site_url_and_home();
		$_SERVER['HTTPS'] = 'on';

		$this->assertSame( 11, has_filter( 'option_home', array( $this->instance, 'convert_to_https' ) ) );
		$this->assertSame( 11, has_filter( 'option_siteurl', array( $this->instance, 'convert_to_https' ) ) );
		$this->assertSame( set_url_scheme( $initial_home_url, 'https' ), get_option( 'home' ) );
		$this->assertSame( set_url_scheme( $initial_site_url, 'https' ), get_option( 'siteurl' ) );
		$this->assertSame( set_url_scheme( $initial_home_url, 'https' ), home_url() );
		$this->assertSame( set_url_scheme( $initial_site_url, 'https' ), site_url() );
	}

	/**
	 * Test convert_to_https.
	 *
	 * @covers WP_HTTPS_UI::convert_to_https()
	 */
	public function test_convert_to_https() {
		// If the URL is already HTTPS, this shouldn't change it.
		$this->assertEquals( self::HTTPS_URL, $this->instance->convert_to_https( self::HTTPS_URL ) );

		// If the URL is protocol-relative, this shouldn't change it.
		$this->assertEquals( self::PROTOCOL_RELATIVE_URL, $this->instance->convert_to_https( self::PROTOCOL_RELATIVE_URL ) );

		// If the URL begins with HTTP, this should change it to HTTPS.
		$this->assertEquals( self::HTTPS_URL, $this->instance->convert_to_https( self::HTTP_URL ) );

		// If the URL doesn't have a protocol, this shouldn't change it.
		$this->assertEquals( self::NO_PROTOCOL_URL, $this->instance->convert_to_https( self::NO_PROTOCOL_URL ) );

		// If the URL has http in 2 places, this should only replace the one at the beginning.
		$url_without_protocol = 'example.com/other/url/http://bar.com';
		$this->assertEquals( 'https://' . $url_without_protocol, $this->instance->convert_to_https( 'http://' . $url_without_protocol ) );
	}

	/**
	 * Test filter_header.
	 *
	 * @covers WP_HTTPS_UI::filter_header()
	 */
	public function test_filter_header() {
		// If the option to upgrade to HTTPS is not true, this should not add the filter.
		update_option( WP_HTTPS_UI::UPGRADE_HTTPS_OPTION, '' );
		$this->instance->filter_header();
		$this->assertFalse( has_filter( 'wp_headers', array( $this->instance, 'upgrade_insecure_requests' ) ) );

		// If the option to upgrade to HTTPS is true, this should add the filter.
		update_option( WP_HTTPS_UI::UPGRADE_HTTPS_OPTION, true );
		$this->instance->filter_header();
		$this->assertEquals( 10, has_filter( 'wp_headers', array( $this->instance, 'upgrade_insecure_requests' ) ) );
		remove_filter( 'wp_headers', array( $this->instance, 'upgrade_insecure_requests' ) );

		// If the siteurl and home use HTTPS, this should not add the filter, as this site already uses HTTPS.
		add_filter( 'option_siteurl', array( $this->instance, 'convert_to_https' ), 11 );
		add_filter( 'option_home', array( $this->instance, 'convert_to_https' ), 11 );
		$this->assertFalse( has_filter( 'wp_headers', array( $this->instance, 'upgrade_insecure_requests' ) ) );
	}

	/**
	 * Test upgrade_insecure_requests.
	 *
	 * @covers WP_HTTPS_UI::upgrade_insecure_requests()
	 */
	public function test_upgrade_insecure_requests() {
		$initial_header = array(
			'Cache-Control' => 'max-age=0',
			'Host'          => 'example.com',
		);

		$this->assertEquals(
			array_merge(
				$initial_header,
				array(
					'Content-Security-Policy' => 'upgrade-insecure-requests',
				)
			),
			$this->instance->upgrade_insecure_requests( $initial_header )
		);
	}

	/**
	 * Test conditionally_redirect_to_https.
	 *
	 * @covers WP_HTTPS_UI::conditionally_redirect_to_https()
	 */
	public function test_conditionally_redirect_to_https() {
		// If the request is for HTTPS, this should not redirect.
		$_SERVER['HTTPS'] = 'on';
		$this->assertFalse( $this->did_redirect() );

		// The request is for HTTP, but the options to upgrade to HTTPS and whether HTTPS is supported aren't correct.
		$home_url_http          = home_url( '/', 'http' );
		$_SERVER['HTTPS']       = '';
		$_SERVER['REQUEST_URI'] = $home_url_http;
		$this->assertFalse( $this->did_redirect() );

		// The checkbox to upgrade to HTTPS is checked, but the option for whether HTTPS is supported isn't correct.
		update_option( WP_HTTPS_UI::UPGRADE_HTTPS_OPTION, WP_HTTPS_UI::OPTION_CHECKED_VALUE );
		$this->assertFalse( $this->did_redirect() );

		// The option for whether HTTPS is supported is now correct, so this should redirect.
		update_option( WP_HTTPS_Detection::HTTPS_SUPPORT_OPTION_NAME, true );
		$this->assertTrue( $this->did_redirect() );

		// If is_ssl() is true, this should not redirect.
		$_SERVER['HTTPS'] = 'on';
		$this->assertFalse( $this->did_redirect() );
		$_SERVER['HTTPS'] = '';
	}

	/**
	 * Gets whether there was a redirect on calling conditionally_redirect_to_https().
	 * Redirecting causes an Exception in PHPUnit: Cannot modify header information - headers already sent...
	 *
	 * @return bool Whether there was a redirect.
	 */
	public function did_redirect() {
		try {
			$this->instance->conditionally_redirect_to_https();
		} catch ( Exception $e ) {
			return true;
		}
		return false;
	}

	/**
	 * Converts a URL to HTTP.
	 *
	 * @param string $url The URL to filter.
	 * @return string $url The filtered URL.
	 */
	public function convert_to_http( $url ) {
		return preg_replace( '#^https(?=://)#', 'http', $url );
	}
}
