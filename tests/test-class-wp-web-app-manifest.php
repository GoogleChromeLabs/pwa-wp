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
		$this->assertInstanceOf( WP_Web_App_Manifest::class, $this->instance );
		$this->assertEquals( 10, has_action( 'wp_head', array( $this->instance, 'manifest_link_and_meta' ) ) );
		$this->assertEquals( 10, has_action( 'rest_api_init', array( $this->instance, 'register_manifest_rest_route' ) ) );

		$this->assertEquals( 10, has_action( 'rest_api_init', array( $this->instance, 'register_short_name_setting' ) ) );
		$this->assertEquals( 10, has_action( 'admin_init', array( $this->instance, 'register_short_name_setting' ) ) );
		$this->assertEquals( 10, has_action( 'admin_init', array( $this->instance, 'add_short_name_settings_field' ) ) );
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
	 * Data provider.
	 *
	 * @return array
	 */
	public function get_data_to_test_is_name_short() {
		return array(
			array( '0123456789ab', true ),
			array( 'áâàéêèíîìóôò', true ),
			array( ' áâàéêèíîìóôò ', true ),
			array( ' 0123456789ab ', true ),
			array( '0123456789abc', false ),
			array( 'áâàéêèíîìóôò2', false ),
		);
	}

	/**
	 * Test is_name_short.
	 *
	 * @dataProvider get_data_to_test_is_name_short
	 * @covers ::is_name_short()
	 *
	 * @param string $name     Short name to test.
	 * @param bool   $is_short Whether short is expected.
	 */
	public function test_is_name_short( $name, $is_short ) {
		$this->assertSame( $is_short, $this->instance->is_name_short( $name ) );
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
	 * Data provider.
	 *
	 * @return array
	 */
	public function get_data_to_test_get_manifest_short_name() {
		$set_long_blogname                  = static function () {
			update_option( 'blogname', 'WordPress Develop' );
		};
		$set_short_blogname                 = static function () {
			update_option( 'blogname', 'WP Dev' );
		};
		$set_short_blogname_with_whitespace = static function () {
			update_option( 'blogname', 'WP Dev                ' );
		};
		$set_short_name                     = static function () {
			update_option( 'short_name', 'WP Dev' );
		};

		return array(
			'long_blogname'             => array( $set_long_blogname, null ),
			'short_blogname'            => array( $set_short_blogname, 'WP Dev' ),
			'short_blogname_whitespace' => array( $set_short_blogname_with_whitespace, 'WP Dev' ),
			'short_name_option'         => array(
				static function () use ( $set_long_blogname, $set_short_name ) {
					$set_long_blogname();
					$set_short_name();
				},
				'WP Dev',
			),
			'short_name_filtered'       => array(
				static function () use ( $set_long_blogname, $set_short_name ) {
					$set_long_blogname();
					add_filter(
						'web_app_manifest',
						static function ( $manifest ) {
							$manifest['short_name'] = 'So short!';
							return $manifest;
						}
					);
				},
				'So short!',
			),
		);
	}

	/**
	 * Test get_manifest for short_name.
	 *
	 * @covers ::get_manifest()
	 * @dataProvider get_data_to_test_get_manifest_short_name
	 *
	 * @param callable    $setup Setup callback.
	 * @param string|null $expected Expected short name.
	 */
	public function test_get_manifest_short_name( $setup, $expected ) {
		$setup();
		$manifest = $this->instance->get_manifest();
		if ( null === $expected ) {
			$this->assertArrayNotHasKey( 'short_name', $manifest );
		} else {
			$this->assertArrayHasKey( 'short_name', $manifest );
			$this->assertEquals( $expected, $manifest['short_name'] );
		}
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
	 * Test sort_icons_callback.
	 *
	 * @covers ::sort_icons_callback()
	 */
	public function test_sort_icons_callback() {
		$this->markTestIncomplete();
	}

	/**
	 * Test get_url.
	 *
	 * @covers ::get_url()
	 */
	public function test_get_url() {
		$this->markTestIncomplete();
	}

	/**
	 * Test register_short_name_setting.
	 *
	 * @covers ::register_short_name_setting()
	 */
	public function test_register_short_name_setting() {
		$this->markTestIncomplete();
	}

	/**
	 * Test add_short_name_settings_field.
	 *
	 * @covers ::add_short_name_settings_field()
	 */
	public function test_add_short_name_settings_field() {
		$this->markTestIncomplete();
	}

	/**
	 * Test sanitize_short_name.
	 *
	 * @covers ::sanitize_short_name()
	 */
	public function test_sanitize_short_name() {
		$this->markTestIncomplete();
	}

	/**
	 * Test render_short_name_settings_field.
	 *
	 * @covers ::render_short_name_settings_field()
	 */
	public function test_render_short_name_settings_field() {
		$this->markTestIncomplete();
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
