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

		$this->assertTrue( has_action( 'admin_action_create-offline-page' ) );
	}

	/**
	 * Test get_offline_page_id.
	 *
	 * @covers WP_Offline_Page::get_offline_page_id()
	 */
	public function test_get_offline_page_id() {
		$this->assertSame( 0, $this->instance->get_offline_page_id() );

		add_option( WP_Offline_Page::OPTION_NAME, 5 );
		$this->assertSame( 5, $this->instance->get_offline_page_id() );
	}

	/**
	 * Test get_static_pages.
	 *
	 * @covers WP_Offline_Page::get_static_pages()
	 */
	public function test_get_static_pages() {
		$expected = array(
			'page_on_front'              => 0,
			'page_for_posts'             => 0,
			'wp_page_for_privacy_policy' => (int) get_option( 'wp_page_for_privacy_policy', 0 ),
			WP_Offline_Page::OPTION_NAME => 0,
		);
		$this->assertSame( $expected, $this->instance->get_static_pages() );

		$expected[ WP_Offline_Page::OPTION_NAME ] = 6;
		add_option( WP_Offline_Page::OPTION_NAME, 6 );
		$this->assertSame( $expected, $this->instance->get_static_pages() );
	}
}
