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
	}

	/**
	 * Test register_settings.
	 *
	 * @covers WP_HTTPS_UI::register_settings()
	 */
	public function test_register_settings() {
		global $new_whitelist_options, $wp_registered_settings;

		$expected_settings = array(
			'type'              => 'string',
			'group'             => WP_HTTPS_UI::OPTION_GROUP,
			'description'       => '',
			'sanitize_callback' => array( $this->instance, 'sanitize_callback' ),
			'show_in_rest'      => false,
		);

		$this->instance->register_settings();
		$this->assertTrue( in_array( WP_HTTPS_UI::UPGRADE_HTTPS_OPTION, $new_whitelist_options['reading'], true ) );
		$this->assertEquals(
			$expected_settings,
			$wp_registered_settings[ WP_HTTPS_UI::UPGRADE_HTTPS_OPTION ]
		);

		$this->assertTrue( in_array( WP_HTTPS_UI::UPGRADE_INSECURE_CONTENT_OPTION, $new_whitelist_options['reading'], true ) );
		$this->assertEquals(
			$expected_settings,
			$wp_registered_settings[ WP_HTTPS_UI::UPGRADE_INSECURE_CONTENT_OPTION ]
		);
	}

	/**
	 * Test sanitize_callback.
	 *
	 * @covers WP_HTTPS_UI::sanitize_callback()
	 */
	public function test_sanitize_callback() {
		// Assert that disallowed values aren't returned from the callback.
		$this->assertEquals( null, $this->instance->sanitize_callback( 'foo string' ) );
		$this->assertEquals( null, $this->instance->sanitize_callback( '2345' ) );

		// Assert that allowed values are returned.
		$this->assertEquals( '1', $this->instance->sanitize_callback( '1' ) );
		$this->assertEquals( '0', $this->instance->sanitize_callback( '0' ) );
	}

	/**
	 * Test add_settings_field.
	 *
	 * @covers WP_HTTPS_UI::add_settings_field()
	 */
	public function test_add_settings_field() {
		global $wp_settings_fields;

		$this->instance->add_settings_field();
		$this->assertEquals(
			array(
				'id'       => WP_HTTPS_UI::SETTING_ID,
				'title'    => 'HTTPS',
				'callback' => array( $this->instance, 'render_settings' ),
				'args'     => array(),
			),
			$wp_settings_fields[ WP_HTTPS_UI::OPTION_GROUP ]['default'][ WP_HTTPS_UI::SETTING_ID ]
		);
	}

	/**
	 * Test render_settings.
	 *
	 * @covers WP_HTTPS_UI::render_settings()
	 */
	public function test_render_settings() {
		// Set the option values, which should appear in the <input type="radio"> elements.
		update_option( WP_HTTPS_UI::UPGRADE_HTTPS_OPTION, WP_HTTPS_UI::OPTION_SELECTED_VALUE );
		update_option( WP_HTTPS_UI::UPGRADE_INSECURE_CONTENT_OPTION, WP_HTTPS_UI::OPTION_SELECTED_VALUE );
		ob_start();
		$this->instance->render_settings();
		$output = ob_get_clean();

		$this->assertContains( WP_HTTPS_UI::OPTION_SELECTED_VALUE, $output );
		$this->assertContains( WP_HTTPS_UI::UPGRADE_HTTPS_OPTION, $output );
		$this->assertContains( WP_HTTPS_UI::UPGRADE_INSECURE_CONTENT_OPTION, $output );
		$this->assertContains( 'HTTPS is essential to securing your WordPress site, we strongly suggest enabling HTTPS on your site', $output );
	}
}
