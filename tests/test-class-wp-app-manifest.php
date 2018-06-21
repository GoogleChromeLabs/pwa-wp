<?php
/**
 * Tests for class WP_APP_Manifest.
 *
 * @package PWA
 */

/**
 * Tests for class WP_APP_Manifest.
 */
class Test_WP_APP_Manifest extends WP_Ajax_UnitTestCase {

	/**
	 * Tested instance.
	 *
	 * @var WP_APP_Manifest
	 */
	public $instance;

	/**
	 * A mock background color.
	 *
	 * @var string
	 */
	const MOCK_BACKGROUND_COLOR = '#003001';

	/**
	 * A mock theme color.
	 *
	 * @var string
	 */
	const MOCK_THEME_COLOR = '#422508';

	/**
	 * The expected REST API route.
	 *
	 * @var string
	 */
	const EXPECTED_ROUTE = '/wp/v2/pwa-manifest';

	/**
	 * Image mime_type.
	 *
	 * @var string
	 */
	const MIME_TYPE = 'image/jpeg';

	/**
	 * The site icon image url that's expected, based on the mock site icon.
	 *
	 * @var string
	 */
	public $expected_site_icon_img_url;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->instance = new WP_APP_Manifest();
	}

	/**
	 * Teardown.
	 *
	 * @inheritdoc
	 */
	public function tearDown() {
		global $_wp_theme_features;

		// Calling remove_theme_mod( 'custom-background' ) causes an undefined index error unless 'wp-head-callback' is set.
		unset( $_wp_theme_features['custom-background'] );
		set_theme_mod( 'background_color', null );
		delete_option( 'site_icon' );
		remove_filter( 'pwa_background_color', array( $this, 'mock_background_color' ) );
		parent::tearDown();
	}

	/**
	 * Test init().
	 *
	 * @covers WP_APP_Manifest::init()
	 */
	public function test_init() {
		$this->instance->init();
		$this->assertEquals( 'WP_APP_Manifest', get_class( $this->instance ) );
		$this->assertEquals( 10, has_action( 'wp_head', array( $this->instance, 'manifest_link_and_meta' ) ) );
		$this->assertEquals( 10, has_action( 'rest_api_init', array( $this->instance, 'register_manifest_rest_route' ) ) );
	}

	/**
	 * Test manifest_link_and_meta().
	 *
	 * @covers WP_APP_Manifest::manifest_link_and_meta()
	 */
	public function test_manifest_link_and_meta() {
		ob_start();
		$this->instance->manifest_link_and_meta();
		$output = ob_get_clean();

		$this->assertContains( '<link rel="manifest"', $output );
		$this->assertContains( rest_url( WP_APP_Manifest::REST_NAMESPACE . WP_APP_Manifest::REST_ROUTE ), $output );
		$this->assertContains( '<meta name="theme-color" content="', $output );
	}

	/**
	 * Test get_theme_color().
	 *
	 * @covers WP_APP_Manifest::get_theme_color()
	 */
	public function test_get_theme_color() {
		$test_background_color = '2a7230';
		add_theme_support( 'custom-background' );
		set_theme_mod( 'background_color', $test_background_color );
		$this->assertEquals( "#{$test_background_color}", $this->instance->get_theme_color() );

		// If the theme mod is an empty string, this should return the fallback theme color.
		set_theme_mod( 'background_color', '' );
		$this->assertEquals( WP_APP_Manifest::FALLBACK_THEME_COLOR, $this->instance->get_theme_color() );

		// Ensure the filter at the end of the method works.
		add_filter( 'pwa_background_color', array( $this, 'mock_background_color' ) );
		$this->assertEquals( self::MOCK_BACKGROUND_COLOR, $this->instance->get_theme_color() );
	}

	/**
	 * Test get_manifest().
	 *
	 * @covers WP_APP_Manifest::get_manifest()
	 */
	public function test_get_manifest() {
		global $wp_query;

		// This now has the query arg and is_front_page() is true, so it should send the manifest.
		$_GET[ WP_APP_Manifest::MANIFEST_QUERY_ARG ] = 1;
		add_filter( 'pwa_background_color', array( $this, 'mock_background_color' ) );
		$this->mock_site_icon();
		$actual_manifest   = $this->instance->get_manifest();
		$expected_manifest = array(
			'background_color' => self::MOCK_BACKGROUND_COLOR,
			'description'      => get_bloginfo( 'description' ),
			'display'          => 'minimal-ui',
			'name'             => get_bloginfo( 'name' ),
			'short_name'       => substr( get_bloginfo( 'name' ), 0, 12 ),
			'lang'             => get_locale(),
			'dir'              => is_rtl() ? 'rtl' : 'ltr',
			'start_url'        => get_home_url(),
			'theme_color'      => self::MOCK_BACKGROUND_COLOR,
			'icons'            => $this->instance->get_icons(),
		);
		$this->assertEquals( $expected_manifest, $actual_manifest );

		// Test that the filter at the end of the method works.
		add_filter( 'pwa_manifest_json', array( $this, 'mock_manifest' ) );
		$this->assertContains( self::MOCK_THEME_COLOR, $this->instance->get_manifest() );
	}

	/**
	 * Test register_manifest_rest_route.
	 *
	 * @covers WP_APP_Manifest::register_manifest_rest_route()
	 */
	public function test_register_manifest_rest_route() {
		$this->instance->register_manifest_rest_route();
		$routes  = rest_get_server()->get_routes();
		$route   = $routes[ self::EXPECTED_ROUTE ][0];
		$methods = array(
			'GET' => true,
		);

		$this->assertEmpty( $route['args'] );
		$this->assertEquals( $methods, $route['methods'] );
		$this->assertEquals( array( $this->instance, 'get_manifest' ), $route['callback'] );
		$this->assertEquals( array( $this->instance, 'rest_permission' ), $route['permission_callback'] );
	}

	/**
	 * Test rest_permission.
	 *
	 * @see WP_APP_Manifest::rest_permission()
	 */
	public function test_rest_permission() {
		$allowed_request = new WP_REST_Request();
		$this->assertTrue( $this->instance->rest_permission( $allowed_request ) );

		// A request with a 'context' of 'edit' should result in a WP_Error.
		$disallowed_request            = new WP_REST_Request();
		$disallowed_request['context'] = 'edit';
		$permission_result             = $this->instance->rest_permission( $disallowed_request );
		$this->assertEquals( 'WP_Error', get_class( $permission_result ) );
		$this->assertEquals( 'Sorry, you are not allowed to edit the manifest.', $permission_result->errors['rest_forbidden_context'][0] );
	}

	/**
	 * Test get_icons().
	 *
	 * @covers WP_APP_Manifest::get_icons()
	 */
	public function test_get_icons() {

		// There's no site icon yet, so this should return null.
		$this->assertEquals( null, $this->instance->get_icons() );

		$this->mock_site_icon();
		$expected_icons = array();
		foreach ( $this->instance->default_manifest_icon_sizes as $size ) {
			$expected_icons[] = array(
				'src'   => $this->expected_site_icon_img_url,
				'sizes' => sprintf( '%1$dx%1$d', $size ),
				'type'  => self::MIME_TYPE,
			);
		}
		$this->assertEquals( $expected_icons, $this->instance->get_icons() );
	}

	/**
	 * Gets a mock background color.
	 *
	 * @return string $background_color A mock background color.
	 */
	public function mock_background_color() {
		return self::MOCK_BACKGROUND_COLOR;
	}

	/**
	 * Gets mock manifest data.
	 *
	 * @return array $manifest Manifest data.
	 */
	public function mock_manifest() {
		return array(
			'theme_color' => self::MOCK_THEME_COLOR,
		);
	}

	/**
	 * Creates a mock site icon, and stores the expected image URL in a property.
	 */
	public function mock_site_icon() {
		$mock_site_icon_id = $this->factory()->attachment->create_object( array(
			'file'           => 'foo/site-icon.jpeg',
			'post_mime_type' => self::MIME_TYPE,
		) );
		update_option( 'site_icon', $mock_site_icon_id );

		$attachment_image                 = wp_get_attachment_image_src( $mock_site_icon_id, 'full' );
		$this->expected_site_icon_img_url = $attachment_image[0];
	}
}
