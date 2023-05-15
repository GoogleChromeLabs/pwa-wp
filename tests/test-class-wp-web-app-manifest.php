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
	 * Test manifest_link_and_meta when using the default minimal-ui display.
	 *
	 * @covers ::manifest_link_and_meta()
	 */
	public function test_manifest_link_and_meta_non_browser_display() {
		$this->mock_site_icon();
		update_option( 'short_name', 'WP Dev' );

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

		$this->assertStringContainsString( '<meta name="apple-mobile-web-app-capable" content="yes">', $output );
		$this->assertStringContainsString( '<meta name="mobile-web-app-capable" content="yes">', $output );

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

		$this->assertStringContainsString( '<meta name="apple-mobile-web-app-title" content="WP Dev">', $output );
		$this->assertStringContainsString( '<meta name="application-name" content="WP Dev">', $output );
	}

	/**
	 * Test manifest_link_and_meta when using the browser display.
	 *
	 * @covers ::manifest_link_and_meta()
	 */
	public function test_manifest_link_and_meta_browser_display() {
		$this->mock_site_icon();
		update_option( 'blogname', 'WordPress Develop' );
		update_option( 'short_name', 'WP Dev' );

		add_filter(
			'web_app_manifest',
			static function ( $manifest ) {
				$manifest['display'] = 'browser';
				return $manifest;
			}
		);

		ob_start();
		$this->instance->manifest_link_and_meta();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( '<meta name="apple-mobile-web-app-capable"', $output );
		$this->assertStringNotContainsString( '<meta name="mobile-web-app-capable"', $output );
		$this->assertStringNotContainsString( '<link rel="apple-touch-startup-image"', $output );

		$this->assertStringContainsString( '<link rel="manifest"', $output );
		$this->assertStringContainsString( rest_url( WP_Web_App_Manifest::REST_NAMESPACE . WP_Web_App_Manifest::REST_ROUTE ), $output );
		$this->assertStringContainsString( '<meta name="theme-color" content="', $output );
		$this->assertStringContainsString( $this->instance->get_theme_color(), $output );

		$this->assertStringContainsString( '<meta name="apple-mobile-web-app-title" content="WP Dev">', $output );
		$this->assertStringContainsString( '<meta name="application-name" content="WP Dev">', $output );
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
		$tagline  = 'This is an installable website!';
		update_option( 'blogname', $blogname );
		update_option( 'blogdescription', $tagline );
		update_option( 'site_icon_maskable', false );
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

		// Check that icon purpose is `any maskable` if site icon is maskable.
		$actual_manifest = $this->instance->get_manifest();
		$this->assertEquals( $expected_manifest, $actual_manifest );
		$purposes = array();
		foreach ( $actual_manifest['icons'] as $icon ) {
			if ( ! isset( $purposes[ $icon['purpose'] ] ) ) {
				$purposes[ $icon['purpose'] ] = 0;
			} else {
				$purposes[ $icon['purpose'] ]++;
			}
		}
		$this->assertEquals(
			array(
				'any' => 1,
			),
			$purposes
		);

		// Make sure maskable is properly checked.
		update_option( 'site_icon_maskable', true );
		$actual_manifest = $this->instance->get_manifest();
		$purposes        = array();
		foreach ( $actual_manifest['icons'] as $icon ) {
			if ( ! isset( $purposes[ $icon['purpose'] ] ) ) {
				$purposes[ $icon['purpose'] ] = 0;
			} else {
				$purposes[ $icon['purpose'] ]++;
			}
		}
		$this->assertEquals(
			array(
				'any'      => 1,
				'maskable' => 1,
			),
			$purposes
		);

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
	 * Test Site icon validation when icon is not set.
	 *
	 * @covers ::validate_site_icon()
	 */
	public function test_validate_site_icon_not_set() {
		$actual_site_icon_validation_errors   = $this->instance->validate_site_icon()->get_error_code();
		$expected_site_icon_validation_errors = 'site_icon_not_set';
		$this->assertEquals( $expected_site_icon_validation_errors, $actual_site_icon_validation_errors );
	}

	/**
	 * Test Site icon validation when icon is not found.
	 *
	 * @covers ::validate_site_icon()
	 */
	public function test_validate_site_icon_metadata_not_found() {
		$attachment_id = '123456';
		update_option( 'site_icon', $attachment_id );
		$actual_site_icon_validation_errors   = $this->instance->validate_site_icon()->get_error_code();
		$expected_site_icon_validation_errors = 'site_icon_metadata_not_found';
		$this->assertEquals( $expected_site_icon_validation_errors, $actual_site_icon_validation_errors );
	}

	/**
	 * Test site icon size validation.
	 *
	 * @covers ::validate_site_icon()
	 */
	public function test_validate_site_icon_too_small() {
		$attachment_id = $this->factory()->attachment->create_upload_object( __DIR__ . '/data/images/100x100.png' );
		update_option( 'site_icon', $attachment_id );
		$actual_site_icon_validation_errors   = $this->instance->validate_site_icon()->get_error_code();
		$expected_site_icon_validation_errors = 'site_icon_too_small';
		$this->assertEquals( $expected_site_icon_validation_errors, $actual_site_icon_validation_errors );
	}

	/**
	 * Test site icon as square validation.
	 *
	 * @covers ::validate_site_icon()
	 */
	public function test_validate_site_icon_not_square() {
		$attachment_id = $this->factory()->attachment->create_upload_object( __DIR__ . '/data/images/512x720.png' );
		update_option( 'site_icon', $attachment_id );
		$actual_site_icon_validation_errors   = $this->instance->validate_site_icon()->get_error_code();
		$expected_site_icon_validation_errors = 'site_icon_not_square';
		$this->assertEquals( $expected_site_icon_validation_errors, $actual_site_icon_validation_errors );
	}

	/**
	 * Test site icon not being PNG.
	 *
	 * @covers ::validate_site_icon()
	 */
	public function test_validate_site_icon_not_png() {
		if ( PHP_MAJOR_VERSION === 7 && PHP_MINOR_VERSION === 1 ) {
			$this->markTestSkipped( 'See https://github.com/GoogleChromeLabs/pwa-wp/pull/702#issuecomment-1042776987' );
		}
		$attachment_id = $this->factory()->attachment->create_upload_object( __DIR__ . '/data/images/512x512.jpg' );
		update_option( 'site_icon', $attachment_id );
		$actual_site_icon_validation_errors   = $this->instance->validate_site_icon()->get_error_code();
		$expected_site_icon_validation_errors = 'site_icon_not_png';
		$this->assertEquals( $expected_site_icon_validation_errors, $actual_site_icon_validation_errors );
	}

	/**
	 * Test site icon as valid.
	 *
	 * @covers ::validate_site_icon()
	 */
	public function test_validate_site_icon_good() {
		$attachment_id = $this->factory()->attachment->create_upload_object( __DIR__ . '/data/images/512x512.png' );
		update_option( 'site_icon', $attachment_id );
		$this->assertTrue( $this->instance->validate_site_icon() );
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
				'src'     => $this->expected_site_icon_img_url,
				'sizes'   => sprintf( '%1$dx%1$d', $size ),
				'type'    => self::MIME_TYPE,
				'purpose' => 'any',
			);
		}
		$this->assertEquals( $expected_icons, $this->instance->get_icons() );
	}

	/**
	 * Test get_url.
	 *
	 * @covers ::get_url()
	 */
	public function test_get_url() {
		$this->assertEquals(
			rest_url( WP_Web_App_Manifest::REST_NAMESPACE . WP_Web_App_Manifest::REST_ROUTE ),
			$this->instance->get_url()
		);
	}

	/**
	 * Test register_short_name_setting.
	 *
	 * @covers ::register_short_name_setting()
	 */
	public function test_register_short_name_setting() {
		global $wp_registered_settings;
		unset( $wp_registered_settings['short_name'] );

		$this->assertArrayNotHasKey( 'short_name', $wp_registered_settings );
		$this->instance->register_short_name_setting();
		$this->assertArrayHasKey( 'short_name', $wp_registered_settings );
		$setting = $wp_registered_settings['short_name'];

		$this->assertEquals( 'string', $setting['type'] );
		$this->assertEquals( 'general', $setting['group'] );
		$this->assertEquals( array( $this->instance, 'sanitize_short_name' ), $setting['sanitize_callback'] );
		$this->assertEquals( true, $setting['show_in_rest'] );
	}

	/**
	 * Test add_short_name_settings_field.
	 *
	 * @covers ::add_short_name_settings_field()
	 */
	public function test_add_short_name_settings_field() {
		global $wp_settings_fields;
		unset( $wp_settings_fields['general']['default']['short_name'] );
		$this->instance->add_short_name_settings_field();
		$this->assertTrue( isset( $wp_settings_fields['general']['default']['short_name'] ) );
		$field = $wp_settings_fields['general']['default']['short_name'];

		$this->assertEquals( 'short_name', $field['id'] );
		$this->assertEquals( 'Short Name', $field['title'] );
		$this->assertEquals( array( $this->instance, 'render_short_name_settings_field' ), $field['callback'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array Test cases.
	 */
	public function get_data_to_test_sanitize_short_name() {
		return array(
			'int'                => array( 0, '' ),
			'array'              => array( array(), '' ),
			'string'             => array( '', '' ),
			'whitespace_padding' => array( '     WP Dev ', 'WP Dev' ),
			'script_contains'    => array( 'WP <script>evil</script> Dev ', 'WP Dev' ),
			'too_long'           => array( 'WordPress Develop', 'WordPress De' ),
			'multi-byte'         => array( 'On the Rhône', 'On the Rhône' ),
			'emoji'              => array( '👌👌👌👌👌👌👌👌👌👌👌👌👌👌', '👌👌👌👌👌👌👌👌👌👌👌👌' ), // 👌 takes 4 bytes.
		);
	}

	/**
	 * Test sanitize_short_name.
	 *
	 * @dataProvider get_data_to_test_sanitize_short_name
	 * @covers ::sanitize_short_name()
	 *
	 * @param mixed  $input    Input.
	 * @param string $expected Expected.
	 */
	public function test_sanitize_short_name( $input, $expected ) {
		$this->assertEquals( $expected, $this->instance->sanitize_short_name( $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array Test cases.
	 */
	public function get_data_to_test_render_short_name_settings_field() {
		return array(
			'short_name_absent'   => array(
				static function () {
					update_option( 'short_name', '' );
				},
				function ( $output ) {
					$this->assertStringContainsString( '<input type="text" id="short_name" name="short_name" value="" class="regular-text " maxlength="12">', $output );
				},
			),
			'short_name_set'      => array(
				static function () {
					update_option( 'short_name', 'WP\'s Dev' );
				},
				function ( $output ) {
					$this->assertStringContainsString( '<input type="text" id="short_name" name="short_name" value="WP&#039;s Dev" class="regular-text " maxlength="12">', $output );
				},
			),
			'short_name_disabled' => array(
				static function () {
					add_filter(
						'web_app_manifest',
						static function ( $manifest ) {
							$manifest['short_name'] = 'Short';
							return $manifest;
						}
					);
				},
				function ( $output ) {
					$this->assertStringContainsString( '<input type="text" id="short_name" name="short_name" value="Short" class="regular-text disabled" maxlength="12" disabled=\'disabled\'>', $output );
				},
			),
		);
	}

	/**
	 * Test render_short_name_settings_field.
	 *
	 * @covers ::render_short_name_settings_field()
	 * @dataProvider get_data_to_test_render_short_name_settings_field
	 *
	 * @param callable $setup  Set up.
	 * @param callable $assert Assert.
	 */
	public function test_render_short_name_settings_field( $setup, $assert ) {
		$setup();
		$output = trim( get_echo( array( $this->instance, 'render_short_name_settings_field' ) ) );
		$output = preg_replace( '/\s+/', ' ', $output );
		$output = preg_replace( '/\s+>/', '>', $output );
		$output = preg_replace( '/>\s+</', '><', $output );
		$output = preg_replace( '/>\s*/', '>', $output );
		$output = preg_replace( '/\s*</', '<', $output );

		$assert( $output );

		$this->assertStringContainsString( '<script>', $output );
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
