<?php
/**
 * Tests for class WP_Offline_Page.
 *
 * @package PWA
 */

/**
 * Tests for class WP_Offline_Page.
 */
class Test_WP_Offline_Page extends WP_UnitTestCase {

	/**
	 * Tested instance.
	 *
	 * @var WP_Offline_Page
	 */
	public $instance;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->instance = new WP_Offline_Page();
	}

	/**
	 * Test init.
	 *
	 * @covers WP_Offline_Page::init()
	 */
	public function test_init() {
		$this->instance->init();
		$this->assertEquals( 10, has_action( 'admin_init', array( $this->instance, 'register_setting' ) ) );
		$this->assertEquals( 10, has_action( 'admin_init', array( $this->instance, 'settings_field' ) ) );
	}

	/**
	 * Test register_setting.
	 *
	 * @covers WP_Offline_Page::register_setting()
	 */
	public function test_register_setting() {
		global $new_whitelist_options, $wp_registered_settings;

		$this->instance->register_setting();
		$this->assertTrue( in_array( WP_Offline_Page::OPTION_NAME, $new_whitelist_options['reading'], true ) );
		$this->assertEquals(
			array(
				'type'              => 'integer',
				'group'             => WP_Offline_Page::OPTION_GROUP,
				'description'       => '',
				'sanitize_callback' => array( $this->instance, 'sanitize_callback' ),
				'show_in_rest'      => false,
			),
			$wp_registered_settings[ WP_Offline_Page::OPTION_NAME ]
		);
	}

	/**
	 * Test sanitize_callback.
	 *
	 * @covers WP_Offline_Page::sanitize_callback()
	 */
	public function test_sanitize_callback() {
		global $wp_settings_errors;

		$settings_error = array(
			'setting' => WP_Offline_Page::OPTION_NAME,
			'code'    => WP_Offline_Page::OPTION_NAME,
			'type'    => 'error',
		);

		// The option isn't a post, so this should add a settings error.
		$this->assertEquals( null, $this->instance->sanitize_callback( true ) );
		$this->assertEquals(
			array_merge(
				$settings_error,
				array(
					'message' => 'The current offline page does not exist. Please select or create one.',
				)
			),
			reset( $wp_settings_errors )
		);
		$wp_settings_errors = array(); // WPCS: global override OK.

		// The option isn't a post, so this should add a settings error.
		$trashed_post_id = $this->factory()->post->create( array( 'post_status' => 'trash' ) );
		$this->assertEquals( null, $this->instance->sanitize_callback( $trashed_post_id ) );
		$this->assertEquals(
			array_merge(
				$settings_error,
				array(
					'message' => 'The current offline page is in the trash. Please select or create one.',
				)
			),
			reset( $wp_settings_errors )
		);
		$wp_settings_errors = array(); // WPCS: global override OK.

		// The argument passed to the sanitize_callback() is a valid page ID, so it should return it.
		$valid_post_id = $this->factory()->post->create( array( 'post_type' => 'page' ) );
		$this->assertEquals( $valid_post_id, $this->instance->sanitize_callback( $valid_post_id ) );
		$this->assertEquals( array(), $wp_settings_errors );
	}

	/**
	 * Test settings_field.
	 *
	 * @covers WP_Offline_Page::settings_field()
	 */
	public function test_settings_field() {
		global $wp_settings_fields;

		$this->instance->settings_field();
		$this->assertEquals(
			array(
				'id'       => WP_Offline_Page::SETTING_ID,
				'title'    => 'Progressive Web App Offline Page',
				'callback' => array( $this->instance, 'settings_callback' ),
				'args'     => array(),
			),
			$wp_settings_fields[ WP_Offline_Page::OPTION_GROUP ]['default'][ WP_Offline_Page::SETTING_ID ]
		);
	}

	/**
	 * Test settings_callback.
	 *
	 * @covers WP_Offline_Page::settings_callback()
	 */
	public function test_settings_callback() {
		$number_pages = 10;
		$page_ids     = array();
		for ( $i = 0; $i < $number_pages; $i++ ) {
			$page_ids[] = $this->factory()->post->create( array( 'post_type' => 'page' ) );
		}
		ob_start();
		$this->instance->settings_callback();
		$output = ob_get_clean();
		$this->assertContains( 'Select an existing page:', $output );

		// All of the pages should appear in the <select> element from wp_dropdown_pages().
		foreach ( $page_ids as $page_id ) {
			$this->assertContains( strval( $page_id ), $output );
		}
	}

	/**
	 * Test has_pages.
	 *
	 * @covers WP_Offline_Page::has_pages()
	 */
	public function test_has_pages() {
		$this->assertFalse( $this->instance->has_pages() );

		// There is a post, but this needs a post type of 'page'.
		$this->factory()->post->create();
		$this->assertFalse( $this->instance->has_pages() );

		// There's a post with the type of 'page,' so this should be true.
		$this->factory()->post->create( array(
			'post_type' => 'page',
		) );
		$this->assertTrue( $this->instance->has_pages() );
	}
}
