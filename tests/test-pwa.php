<?php
/**
 * Tests for pwa.php.
 *
 * @package PWA
 */

/**
 * Tests for amp.php.
 */
class Test_PWA extends WP_UnitTestCase {

	/**
	 * Test constants.
	 */
	public function test_constants() {
		$this->assertTrue( defined( 'PWAWP_VERSION' ) );
		$this->assertTrue( defined( 'PWAWP_PLUGIN_FILE' ) );
		$this->assertTrue( defined( 'PWAWP_PLUGIN_DIR' ) );
	}

	/**
	 * Test pwawp_init().
	 *
	 * @covers pwawp_init()
	 */
	public function test_pwawp_init() {
		$this->assertTrue( class_exists( 'PWAWP_APP_Manifest' ) );
	}
}
