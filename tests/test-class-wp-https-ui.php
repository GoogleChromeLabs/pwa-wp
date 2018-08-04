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
		$this->instance = new WP_HTTPS_UI();
	}

	/**
	 * Test init.
	 *
	 * @covers WP_HTTPS_UI::init()
	 */
	public function test_init() {
		$this->instance->init();
		$this->assertEquals( 10, has_action( 'admin_init', array( $this->instance, 'init_admin' ) ) );
		$this->assertEquals( 10, has_action( 'admin_init', array( $this->instance, 'filter_site_url_and_home' ) ) );
		$this->assertEquals( 10, has_action( 'admin_init', array( $this->instance, 'filter_header' ) ) );
	}

	/**
	 * Test register_settings.
	 *
	 * @covers WP_HTTPS_UI::register_settings()
	 */
	public function test_register_settings() {
		global $new_whitelist_options, $wp_registered_settings;

		$base_expected_settings = array(
			'type'         => 'string',
			'group'        => WP_HTTPS_UI::OPTION_GROUP,
			'description'  => '',
			'show_in_rest' => false,
		);

		$expected_https_settings = array_merge(
			$base_expected_settings,
			array(
				'sanitize_callback' => array( $this->instance, 'upgrade_https_sanitize_callback' ),
			)
		);
		$this->instance->register_settings();
		$this->assertTrue( in_array( WP_HTTPS_UI::UPGRADE_HTTPS_OPTION, $new_whitelist_options[ WP_HTTPS_UI::OPTION_GROUP ], true ) );
		$this->assertEquals(
			$expected_https_settings,
			$wp_registered_settings[ WP_HTTPS_UI::UPGRADE_HTTPS_OPTION ]
		);
	}

	/**
	 * Test upgrade_https_sanitize_callback.
	 *
	 * @covers WP_HTTPS_UI::upgrade_https_sanitize_callback()
	 */
	public function test_upgrade_https_sanitize_callback() {
		/**
		 * Test the <input type="checkbox"> being checked.
		 * This callback does not evaluate the argument, so it could be anything.
		 */
		$_POST[ WP_HTTPS_UI::UPGRADE_HTTPS_OPTION ] = WP_HTTPS_UI::OPTION_CHECKED_VALUE;
		$this->assertTrue( $this->instance->upgrade_https_sanitize_callback( 'foo' ) );

		// The checkbox value could be anything, as long as the $_POST value isset().
		$_POST[ WP_HTTPS_UI::UPGRADE_HTTPS_OPTION ] = 'baz';
		$this->assertTrue( $this->instance->upgrade_https_sanitize_callback( 'bar' ) );

		// If the $_POST value is not set, the callback should return false.
		unset( $_POST[ WP_HTTPS_UI::UPGRADE_HTTPS_OPTION ] );
		$this->assertFalse( $this->instance->upgrade_https_sanitize_callback( 'baz' ) );
	}

	/**
	 * Test add_settings_field.
	 *
	 * @covers WP_HTTPS_UI::add_settings_field()
	 */
	public function test_add_settings_field() {
		global $wp_settings_fields;

		add_filter( 'set_url_scheme', array( $this->instance, 'convert_to_https' ) );
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
		update_option( WP_HTTPS_UI::UPGRADE_HTTPS_OPTION, WP_HTTPS_UI::OPTION_CHECKED_VALUE );
		update_option( 'siteurl', self::HTTPS_URL );
		update_option( 'home', self::HTTPS_URL );
		add_filter( 'set_url_scheme', array( $this->instance, 'convert_to_https' ) );
		update_option(
			WP_HTTPS_Detection::INSECURE_CONTENT_OPTION_NAME,
			array(
				'active'  => array( self::HTTP_URL ),
				'passive' => array( self::HTTP_URL ),
			)
		);
		ob_start();
		$this->instance->render_https_settings();
		$output = ob_get_clean();

		$this->assertContains( WP_HTTPS_UI::OPTION_CHECKED_VALUE, $output );
		$this->assertContains( WP_HTTPS_UI::UPGRADE_HTTPS_OPTION, $output );
		$this->assertContains( 'HTTPS is essential to securing your WordPress site, we strongly suggest upgrading to HTTPS.', $output );
		$this->assertContains( 'There are 2 non-HTTPS URLs on your home page.', $output );
		$this->assertContains( self::HTTP_URL, $output );
		$this->assertNotContains( 'class="hidden"', $output );
	}

	/**
	 * Test is_currently_https.
	 *
	 * @covers WP_HTTPS_UI::is_currently_https()
	 */
	public function test_is_currently_https() {
		// If both of these options have an HTTP URL, the method should return false.
		update_option( 'siteurl', self::HTTP_URL );
		update_option( 'home', self::HTTP_URL );
		$this->assertFalse( $this->instance->is_currently_https() );

		// If one of these options has an HTTP URL, the method should return false.
		update_option( 'siteurl', self::HTTPS_URL );
		$this->assertFalse( $this->instance->is_currently_https() );

		// If both of these options have an HTTPS URL, the method should return true.
		update_option( 'siteurl', self::HTTPS_URL );
		update_option( 'home', self::HTTPS_URL );
		add_filter( 'set_url_scheme', array( $this->instance, 'convert_to_https' ) );
		$this->assertTrue( $this->instance->is_currently_https() );
	}

	/**
	 * Test filter_site_url_and_home.
	 *
	 * @covers WP_HTTPS_UI::filter_site_url_and_home()
	 */
	public function test_filter_site_url_and_home() {
		$initial_url = 'http://foo.com';

		// Set the siteurl and home values to HTTP, to test that this method converts them to HTTPS.
		add_filter( 'option_home', array( $this, 'convert_to_http' ), 11 );
		add_filter( 'option_siteurl', array( $this, 'convert_to_http' ), 11 );

		// Simulate 'HTTPS Upgrade' not being selected in the UI, where the filters shouldn't convert the URLs to HTTPS.
		$this->instance->filter_site_url_and_home();
		$this->assertNotEquals( 11, has_filter( 'option_home', array( $this->instance, 'convert_to_https' ) ) );
		$this->assertNotEquals( 11, has_filter( 'option_siteurl', array( $this->instance, 'convert_to_https' ) ) );
		$this->assertEquals( $initial_url, apply_filters( 'option_home', $initial_url ) );
		$this->assertEquals( $initial_url, apply_filters( 'option_siteurl', $initial_url ) );

		// Simulate 'HTTPS Upgrade' being selected, where the filters should convert the URLs to HTTPS.
		update_option( WP_HTTPS_UI::UPGRADE_HTTPS_OPTION, WP_HTTPS_UI::OPTION_CHECKED_VALUE );
		$this->instance->filter_site_url_and_home();

		$this->assertEquals( 11, has_filter( 'option_home', array( $this->instance, 'convert_to_https' ) ) );
		$this->assertEquals( 11, has_filter( 'option_siteurl', array( $this->instance, 'convert_to_https' ) ) );
		$this->assertContains( 'https', apply_filters( 'option_home', $initial_url ) );
		$this->assertContains( 'https', apply_filters( 'option_siteurl', $initial_url ) );
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
	 * Converts a URL to HTTP.
	 *
	 * @param string $url The URL to filter.
	 * @return string $url The filtered URL.
	 */
	public function convert_to_http( $url ) {
		return str_replace( 'https', 'http', $url );
	}

	/**
	 * Test filter_header.
	 *
	 * @covers WP_HTTPS_UI::filter_header()
	 */
	public function test_filter_header() {
		// If there is no insecure content at all, this should not add the filter.
		update_option( WP_HTTPS_Detection::INSECURE_CONTENT_OPTION_NAME, '' );
		$this->instance->filter_header();
		$this->assertFalse( has_filter( 'wp_headers', array( $this->instance, 'upgrade_insecure_requests' ) ) );

		// If there is only passive insecure content, this should not add the filter.
		update_option(
			WP_HTTPS_Detection::INSECURE_CONTENT_OPTION_NAME,
			array(
				'passive' => array( self::HTTP_URL ),
			)
		);
		$this->instance->filter_header();
		$this->assertFalse( has_filter( 'wp_headers', array( $this->instance, 'upgrade_insecure_requests' ) ) );

		// If there is active insecure content, this should add the filter.
		update_option(
			WP_HTTPS_Detection::INSECURE_CONTENT_OPTION_NAME,
			array(
				'active' => array(
					'http://example.com/active-foo',
				),
			)
		);
		$this->instance->filter_header();
		$this->assertEquals( 10, has_filter( 'wp_headers', array( $this->instance, 'upgrade_insecure_requests' ) ) );
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
					'Upgrade-Insecure-Requests' => '1',
				)
			),
			$this->instance->upgrade_insecure_requests( $initial_header )
		);
	}
}
