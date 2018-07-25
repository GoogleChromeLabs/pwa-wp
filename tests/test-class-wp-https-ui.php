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
		$this->assertEquals( 10, has_action( 'core_upgrade_preamble', array( $this->instance, 'render_ui' ) ) );
	}

	/**
	 * Test render_ui.
	 *
	 * @covers WP_HTTPS_UI::render_ui()
	 */
	public function test_render_ui() {
		ob_start();
		$this->instance->render_ui();
		$output = ob_get_clean();
		$this->assertContains( 'Enable HTTPS', $output );
		$this->assertContains( '<form method="post" action="', $output );
	}
}
