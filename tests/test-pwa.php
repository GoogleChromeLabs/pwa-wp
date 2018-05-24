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
		$this->assertTrue( defined( 'PWA_VERSION' ) );
		$this->assertTrue( defined( 'PWA_PLUGIN_FILE' ) );
		$this->assertTrue( defined( 'PWA_PLUGIN_DIR' ) );
	}
}
