<?php
/**
 * Tests for class PWAWP_APP_Manifest.
 *
 * @package PWA
 */

/**
 * Tests for class PWAWP_APP_Manifest.
 */
class Test_PWAWP_APP_Manifest extends WP_Ajax_UnitTestCase {

	/**
	 * Tested instance.
	 *
	 * @var PWAWP_APP_Manifest
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
		$this->instance = new PWAWP_APP_Manifest();
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
		parent::tearDown();
	}

	/**
	 * Test init().
	 *
	 * @covers PWAWP_APP_Manifest::init()
	 */
	public function test_init() {
		$this->instance->init();
		$this->assertEquals( 'PWAWP_APP_Manifest', get_class( $this->instance ) );
		$this->assertEquals( 10, has_action( 'wp_head', array( $this->instance, 'manifest_link_and_meta' ) ) );
		$this->assertEquals( 10, has_action( 'amp_post_template_head', array( $this->instance, 'manifest_link_and_meta' ) ) );
		$this->assertEquals( 2, has_action( 'template_redirect', array( $this->instance, 'send_manifest_json' ) ) );
	}

	/**
	 * Test manifest_link_and_meta().
	 *
	 * @covers PWAWP_APP_Manifest::manifest_link_and_meta()
	 */
	public function test_manifest_link_and_meta() {
		ob_start();
		$this->instance->manifest_link_and_meta();
		$output = ob_get_clean();

		$this->assertContains( '<link rel="manifest"', $output );
		$this->assertContains( add_query_arg( PWAWP_APP_Manifest::MANIFEST_QUERY_ARG, '1', home_url( '/' ) ), $output );
		$this->assertContains( '<meta name="theme-color" content="', $output );
		$this->assertContains( PWAWP_APP_Manifest::FALLBACK_THEME_COLOR, $output );
	}

	/**
	 * Test get_theme_color().
	 *
	 * @covers PWAWP_APP_Manifest::get_theme_color()
	 */
	public function test_get_theme_color() {
		$test_background_color = '2a7230';
		add_theme_support( 'custom-background' );
		set_theme_mod( 'background_color', $test_background_color );
		$this->assertEquals( "#{$test_background_color}", $this->instance->get_theme_color() );

		// If the theme mod is an empty string, this should return the fallback color.
		set_theme_mod( 'background_color', '' );
		$this->assertEquals( PWAWP_APP_Manifest::FALLBACK_THEME_COLOR, $this->instance->get_theme_color() );

		// Ensure the filter at the end of the method works.
		add_filter( 'pwawp_background_color', array( $this, 'mock_background_color' ) );
		$this->assertEquals( self::MOCK_BACKGROUND_COLOR, $this->instance->get_theme_color() );
	}

	/**
	 * Test send_manifest_json().
	 *
	 * @covers PWAWP_APP_Manifest::send_manifest_json()
	 */
	public function test_send_manifest_json() {
		global $wp_query;

		// This isn't on the front page, and the query arg isn't present, so it shouldn't send the manifest.
		ob_start();
		$this->instance->send_manifest_json();
		$this->assertEmpty( ob_get_clean() );

		// Even though is_front_page() is true, this still shouldn't send the manifest because the query arg isn't present.
		update_option( 'show_on_front', 'posts' );
		$wp_query->is_home = true;
		ob_start();
		$this->instance->send_manifest_json();
		$this->assertEmpty( ob_get_clean() );

		// This now has the query arg and is_front_page() is true, so it should send the manifest.
		$_GET[ PWAWP_APP_Manifest::MANIFEST_QUERY_ARG ] = 1;
		$this->mock_site_icon();
		ob_start();
		try {
			$this->instance->send_manifest_json();
			$this->_last_response = ob_get_clean();
		} catch ( Exception $e ) {
			unset( $e );
		}

		$actual_manifest   = json_decode( $this->_last_response, true );
		$expected_manifest = array(
			'background_color' => PWAWP_APP_Manifest::FALLBACK_THEME_COLOR,
			'description'      => get_bloginfo( 'description' ),
			'display'          => 'standalone',
			'name'             => get_bloginfo( 'name' ),
			'short_name'       => substr( get_bloginfo( 'name' ), 0, 12 ),
			'start_url'        => get_home_url(),
			'theme_color'      => PWAWP_APP_Manifest::FALLBACK_THEME_COLOR,
			'icons'            => array_map(
				array( $this->instance, 'build_icon_object' ),
				$this->instance->default_manifest_icon_sizes
			),
		);
		$this->assertEquals( $expected_manifest, $actual_manifest );

		// Test that the filter at the end of the method works.
		add_filter( 'pwawp_manifest_json', array( $this, 'mock_manifest' ) );
		ob_start();
		try {
			$this->instance->send_manifest_json();
			$this->_last_response = ob_get_clean();
		} catch ( Exception $e ) {
			unset( $e );
		}
		$this->assertContains( self::MOCK_THEME_COLOR, $this->_last_response );
	}

	/**
	 * Test build_icon_object().
	 *
	 * @covers PWAWP_APP_Manifest::build_icon_object()
	 */
	public function test_build_icon_object() {
		$this->mock_site_icon();
		$sizes = array( 192, 250, 512 );
		foreach ( $sizes as $size ) {
			$expected_icon_object = array(
				'src'   => $this->expected_site_icon_img_url,
				'sizes' => sprintf( '%1$dx%1$d', $size ),
			);
			$this->assertEquals( $expected_icon_object, $this->instance->build_icon_object( $size ) );
		}
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
			'file' => 'foo/site-icon.jpeg',
		) );
		update_option( 'site_icon', $mock_site_icon_id );

		$attachment_image                 = wp_get_attachment_image_src( $mock_site_icon_id, 'full', false );
		$this->expected_site_icon_img_url = $attachment_image[0];
	}
}
