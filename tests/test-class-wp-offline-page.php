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
}
