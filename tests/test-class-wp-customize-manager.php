<?php
/**
 * Tests for customizer settings.
 *
 * @package PWA
 */

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Tests for class WP_Customize_Manager.
 */
class Test_WP_Customize_Manager extends TestCase {

	/**
	 * Tested instance.
	 *
	 * @var WP_Customize_Manager
	 */
	public $wp_customize;

	/**
	 * User ID.
	 *
	 * @var int
	 */
	public $user_id;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->user_id );

		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		// @codingStandardsIgnoreStart
		$GLOBALS['wp_customize'] = new WP_Customize_Manager();
		// @codingStandardsIgnoreStop
		$this->wp_customize = $GLOBALS['wp_customize'];
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		$this->wp_customize = null;
		unset( $GLOBALS['wp_customize'] );
		parent::tear_down();
	}

	/**
	 * @covers ::pwa_customize_register_site_icon_maskable
	 */
	public function test_pwa_customize_register_site_icon_maskable() {
		do_action( 'customize_register', $this->wp_customize );
		pwa_customize_register_site_icon_maskable( $this->wp_customize );

		$this->assertEquals( 1000, has_action( 'customize_register', 'pwa_customize_register_site_icon_maskable' ) );
		$this->assertInstanceOf( 'WP_Customize_Setting', $this->wp_customize->get_setting( 'site_icon_maskable' ) );
		$this->assertInstanceOf( 'WP_Customize_Control', $this->wp_customize->get_control( 'site_icon_maskable' ) );
	}

	/**
	 * @covers ::pwa_customize_controls_enqueue_site_icon_script
	 */
	public function test_pwa_customize_controls_enqueue_site_icon_script() {
		pwa_customize_controls_enqueue_site_icon_script();

		$this->assertEquals( 10, has_action( 'customize_controls_enqueue_scripts', 'pwa_customize_controls_enqueue_site_icon_script' ) );
		$this->assertTrue( wp_script_is( 'customize-controls', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'customize-controls-site-icon-pwa', 'enqueued' ) );
	}

}
