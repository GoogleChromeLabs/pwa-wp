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
		$this->assertEquals( 10, has_action( 'admin_init', array( $this->instance, 'init_admin' ) ) );
		$this->assertEquals( 10, has_action( 'admin_notices', array( $this->instance, 'add_settings_error' ) ) );
		$this->assertEquals( 10, has_filter( 'display_post_states', array( $this->instance, 'add_post_state' ) ) );
		$this->assertEquals( 10, has_filter( 'wp_dropdown_pages', array( $this->instance, 'exclude_from_page_dropdown' ) ) );
	}

	/**
	 * Test get_offline_page_id.
	 *
	 * @covers WP_Offline_Page::get_offline_page_id()
	 */
	public function test_get_offline_page_id() {
		$this->assertSame( 0, $this->instance->get_offline_page_id() );
		$this->assertSame( 0, $this->instance->get_offline_page_id( true ) );

		add_option( WP_Offline_Page::OPTION_NAME, 5 );
		$this->assertSame( 5, $this->instance->get_offline_page_id() );
		$this->assertSame( 5, $this->instance->get_offline_page_id( true ) );
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
					'message' => 'The currently offline page is in the trash. Please select or create one or <a href="edit.php?post_status=trash&post_type=page">restore the current page</a>.',
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
	 * Test add_settings_field.
	 *
	 * @covers WP_Offline_Page::add_settings_field()
	 */
	public function test_add_settings_field() {
		global $wp_settings_fields;

		$this->instance->add_settings_field();
		$this->assertEquals(
			array(
				'id'       => WP_Offline_Page::SETTING_ID,
				'title'    => 'Page displays when offline',
				'callback' => array( $this->instance, 'render_settings' ),
				'args'     => array(),
			),
			$wp_settings_fields[ WP_Offline_Page::OPTION_GROUP ]['default'][ WP_Offline_Page::SETTING_ID ]
		);
	}

	/**
	 * Test render_settings.
	 *
	 * @covers WP_Offline_Page::render_settings()
	 */
	public function test_render_settings() {
		$number_pages = 10;
		$page_ids     = array();
		for ( $i = 0; $i < $number_pages; $i ++ ) {
			$page_ids[] = $this->factory()->post->create( array( 'post_type' => 'page' ) );
		}
		ob_start();
		$this->instance->render_settings();
		$output = ob_get_clean();
		$this->assertContains( 'Select an existing page:', $output );

		// All of the pages should appear in the <select> element from wp_dropdown_pages().
		foreach ( $page_ids as $page_id ) {
			$this->assertContains( strval( $page_id ), $output );
		}

		// Test that the description renders.
		$this->assertContains( 'This page is for the Progressive Web App (PWA)', $output );
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

	/**
	 * Test remove_page_attributes.
	 *
	 * @covers WP_Offline_Page::remove_page_attributes()
	 */
	public function test_remove_page_attributes() {
		$this->assertArrayNotHasKey( 'post', $_GET ); // WPCS: CSRF ok.
		$this->assertFalse( $this->instance->remove_page_attributes() );

		set_current_screen( 'edit-page' );
		$page_id      = $this->factory()->post->create( array( 'post_type' => 'page' ) );
		$_GET['post'] = $page_id; // WPCS: CSRF ok.

		// Check that false returns when the page is not the Offline Page.
		$this->assertFalse( $this->instance->remove_page_attributes() );
		add_option( WP_Offline_Page::OPTION_NAME, $page_id + 99 );
		$this->instance->get_offline_page_id( true );
		$this->assertFalse( $this->instance->remove_page_attributes() );

		// Check that the page attributes were removed for the Offline Page.
		update_option( WP_Offline_Page::OPTION_NAME, $page_id );
		$this->instance->get_offline_page_id( true );
		$this->assertArrayHasKey( 'page-attributes', get_all_post_type_supports( 'page' ) );
		$this->assertTrue( $this->instance->remove_page_attributes() );
		$this->assertArrayNotHasKey( 'page-attributes', get_all_post_type_supports( 'page' ) );
	}

	/**
	 * Test add_post_state.
	 *
	 * @covers WP_Offline_Page::add_post_state()
	 */
	public function test_add_post_state() {
		$page = $this->factory()->post->create_and_get( array( 'post_type' => 'page' ) );
		$this->assertEmpty( $this->instance->add_post_state( array(), $page ) );

		add_option( WP_Offline_Page::OPTION_NAME, $page->ID );
		$this->instance->get_offline_page_id( true );
		$this->assertSame( array( 'Offline Page' ), $this->instance->add_post_state( array(), $page ) );

		update_option( WP_Offline_Page::OPTION_NAME, $page->ID + 10 );
		$this->instance->get_offline_page_id( true );
		$this->assertEmpty( $this->instance->add_post_state( array(), $page ) );
	}

	/**
	 * Test add_settings_error.
	 *
	 * @covers WP_Offline_Page::add_settings_error()
	 */
	public function test_add_settings_error() {
		global $wp_settings_errors;

		$settings_error = array(
			'setting' => WP_Offline_Page::OPTION_NAME,
			'code'    => WP_Offline_Page::OPTION_NAME,
			'type'    => 'error',
		);

		// Check when the page does not exist.
		$this->assertTrue( $this->instance->add_settings_error( null ) );
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

		// Check when the page is in the trash.
		$trashed_page = $this->factory()->post->create_and_get( array(
			'post_type'   => 'page',
			'post_status' => 'trash',
		) );
		$this->assertTrue( $this->instance->add_settings_error( $trashed_page ) );
		$this->assertEquals(
			array_merge(
				$settings_error,
				array(
					'message' => 'The currently offline page is in the trash. Please select or create one or <a href="edit.php?post_status=trash&post_type=page">restore the current page</a>.',
				)
			),
			reset( $wp_settings_errors )
		);
		$wp_settings_errors = array(); // WPCS: global override OK.

		// Check when the page does exist and is not in the trash.
		$offline_page = $this->factory()->post->create_and_get( array( 'post_type' => 'page' ) );
		$this->assertFalse( $this->instance->add_settings_error( $offline_page ) );
		$this->assertEquals( array(), $wp_settings_errors );

		// Check when no offline page is passed (e.g. doing 'admin_notices') and no offline page has been configured.
		$this->assertFalse( $this->instance->add_settings_error() );
		$this->assertEquals( array(), $wp_settings_errors );

		// Check when no offline page is passed (e.g. doing 'admin_notices') and the offline page has been configured but does not exist.
		update_option( WP_Offline_Page::OPTION_NAME, 999999 );
		$this->instance->get_offline_page_id( true );
		$this->assertTrue( $this->instance->add_settings_error() );
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

		// Check when no offline page is passed (e.g. doing 'admin_notices') and the page is in the trash.
		$trashed_page = $this->factory()->post->create_and_get( array(
			'post_type'   => 'page',
			'post_status' => 'trash',
		) );
		update_option( WP_Offline_Page::OPTION_NAME, $trashed_page->ID );
		$this->instance->get_offline_page_id( true );
		$this->assertTrue( $this->instance->add_settings_error() );
		$this->assertEquals(
			array_merge(
				$settings_error,
				array(
					'message' => 'The currently offline page is in the trash. Please select or create one or <a href="edit.php?post_status=trash&post_type=page">restore the current page</a>.',
				)
			),
			reset( $wp_settings_errors )
		);
		$wp_settings_errors = array(); // WPCS: global override OK.

		// Check when no offline page is passed (e.g. doing 'admin_notices') and the offline page is configured.
		$offline_page_id = $this->factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( WP_Offline_Page::OPTION_NAME, $offline_page_id );
		$this->instance->get_offline_page_id( true );
		$this->assertFalse( $this->instance->add_settings_error() );
		$this->assertEquals( array(), $wp_settings_errors );
	}
}
