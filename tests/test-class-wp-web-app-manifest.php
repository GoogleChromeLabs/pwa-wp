<?php
/**
 * Tests for class WP_Web_App_Manifest.
 *
 * @package PWA
 */

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Tests for class WP_Web_App_Manifest.
 *
 * @coversDefaultClass WP_Web_App_Manifest
 */
class Test_WP_Web_App_Manifest extends TestCase {

	/**
	 * Tested instance.
	 *
	 * @var WP_Web_App_Manifest
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
	const EXPECTED_ROUTE = '/wp/v2/web-app-manifest';

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
		$this->instance = new WP_Web_App_Manifest();
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
		remove_filter( 'rest_api_init', array( $this->instance, 'register_manifest_rest_route' ) );
		parent::tearDown();
	}

	/**
	 * Test init.
	 *
	 * @covers ::init()
	 */
	public function test_init() {
		$this->instance->init();
		$this->assertEquals( 'WP_Web_App_Manifest', get_class( $this->instance ) );
		$this->assertEquals( 10, has_action( 'wp_head', array( $this->instance, 'manifest_link_and_meta' ) ) );
		$this->assertEquals( 10, has_action( 'rest_api_init', array( $this->instance, 'register_manifest_rest_route' ) ) );
	}

	/**
	 * Test manifest_link_and_meta.
	 *
	 * @covers ::manifest_link_and_meta()
	 */
	public function test_manifest_link_and_meta() {
		$this->mock_site_icon();

		$added_images = array(
			array(
				'href'  => home_url( '/340.png' ),
				'media' => '(device-width: 340px)',
			),
			array(
				'href' => home_url( '/480.png' ),
			),
		);

		add_filter(
			'apple_touch_startup_images',
			function ( $images ) use ( $added_images ) {
				$this->assertIsArray( $images );
				$this->assertCount( 1, $images );
				$images   = array_merge( $images, $added_images );
				$images[] = array( 'bad' => 'yes' );
				return $images;
			}
		);

		ob_start();
		$this->instance->manifest_link_and_meta();
		$output = ob_get_clean();

		$this->assertSame( 3, substr_count( $output, '<link rel="apple-touch-startup-image"' ) );
		$this->assertStringContainsString( sprintf( '<link rel="apple-touch-startup-image" href="%s">', esc_url( get_site_icon_url() ) ), $output );
		foreach ( $added_images as $added_image ) {
			$tag = sprintf( '<link rel="apple-touch-startup-image" href="%s"', esc_url( $added_image['href'] ) );
			if ( isset( $added_image['media'] ) ) {
				$tag .= sprintf( ' media="%s"', esc_attr( $added_image['media'] ) );
			}
			$tag .= '>';
			$this->assertStringContainsString( $tag, $output );
		}

		$this->assertStringContainsString( '<link rel="manifest"', $output );
		$this->assertStringContainsString( rest_url( WP_Web_App_Manifest::REST_NAMESPACE . WP_Web_App_Manifest::REST_ROUTE ), $output );
		$this->assertStringContainsString( '<meta name="theme-color" content="', $output );
		$this->assertStringContainsString( $this->instance->get_theme_color(), $output );
	}

	/**
	 * Test get_theme_color.
	 *
	 * @covers ::get_theme_color()
	 */
	public function test_get_theme_color() {
		$test_background_color = '2a7230';
		add_theme_support( 'custom-background' );
		set_theme_mod( 'background_color', $test_background_color );
		$this->assertEquals( "#{$test_background_color}", $this->instance->get_theme_color() );

		// If the theme mod is an empty string, this should return the fallback theme color.
		set_theme_mod( 'background_color', '' );
		$this->assertEquals( WP_Web_App_Manifest::FALLBACK_THEME_COLOR, $this->instance->get_theme_color() );
	}

	/**
	 * Test get_manifest.
	 *
	 * @covers ::get_manifest()
	 */
	public function test_get_manifest() {
		$this->mock_site_icon();
		$blogname = 'PWA & Test "First" and \'second\' and “third”';
		update_option( 'blogname', $blogname );
		$actual_manifest = $this->instance->get_manifest();

		// Verify that there are now entities.
		$this->assertEquals( 'PWA &amp; Test &quot;First&quot; and &#039;second&#039; and “third”', get_option( 'blogname' ) );

		$expected_manifest = array(
			'background_color' => WP_Web_App_Manifest::FALLBACK_THEME_COLOR,
			'description'      => get_bloginfo( 'description' ),
			'display'          => 'minimal-ui',
			'name'             => $blogname, // No HTML entities should be in the manifest.
			'lang'             => get_bloginfo( 'language' ),
			'dir'              => is_rtl() ? 'rtl' : 'ltr',
			'start_url'        => home_url( '/' ),
			'theme_color'      => WP_Web_App_Manifest::FALLBACK_THEME_COLOR,
			'icons'            => $this->instance->get_icons(),
		);
		$this->assertEquals( $expected_manifest, $actual_manifest );

		// Check that long names do not automatically copy to short name.
		$blogname = str_repeat( 'x', 13 );
		update_option( 'blogname', $blogname );
		$actual_manifest = $this->instance->get_manifest();
		$this->assertEquals( $blogname, $actual_manifest['name'] );
		$this->assertArrayNotHasKey( 'short_name', $actual_manifest );

		// Test that the filter at the end of the method overrides the value.
		add_filter( 'web_app_manifest', array( $this, 'mock_manifest' ) );
		$actual_manifest = $this->instance->get_manifest();
		$this->assertStringContainsString( self::MOCK_THEME_COLOR, $actual_manifest['theme_color'] );
	}

	/**
	 * Test register_manifest_rest_route.
	 *
	 * @covers ::register_manifest_rest_route()
	 */
	public function test_register_manifest_rest_route() {
		add_action( 'rest_api_init', array( $this->instance, 'register_manifest_rest_route' ) );
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( self::EXPECTED_ROUTE, $routes );
		$route   = $routes[ self::EXPECTED_ROUTE ][0];
		$methods = array(
			'GET' => true,
		);

		$this->assertEmpty( $route['args'] );
		$this->assertEquals( $methods, $route['methods'] );
		$this->assertEquals( array( $this->instance, 'rest_serve_manifest' ), $route['callback'] );
		$this->assertEquals( array( $this->instance, 'rest_permission' ), $route['permission_callback'] );
	}

	/**
	 * Test rest_permission.
	 *
	 * @see WP_Web_App_Manifest::rest_permission()
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
	 * Test get_icons.
	 *
	 * @covers ::get_icons()
	 */
	public function test_get_icons() {

		// There's no site icon yet, so this should return null.
		$this->assertEquals( array(), $this->instance->get_icons() );

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
		$mock_site_icon_id = $this->factory()->attachment->create_object(
			array(
				'file'           => 'foo/site-icon.jpeg',
				'post_mime_type' => self::MIME_TYPE,
			)
		);
		update_option( 'site_icon', $mock_site_icon_id );

		$attachment_image                 = wp_get_attachment_image_src( $mock_site_icon_id, 'full' );
		$this->expected_site_icon_img_url = $attachment_image[0];
	}
}
