<?php
/**
 * Tests for pwa.php.
 *
 * @package PWA
 */

/**
 * Tests for pwa.php.
 */
class Test_PWA extends WP_UnitTestCase {

	/**
	 * Test bootstrap.
	 */
	public function test_bootstrap() {
		$this->assertTrue( defined( 'PWA_VERSION' ) );
		$this->assertTrue( defined( 'PWA_PLUGIN_FILE' ) );
		$this->assertTrue( defined( 'PWA_PLUGIN_DIR' ) );

		$this->assertTrue( class_exists( 'WP_Web_App_Manifest' ) );
		$this->assertTrue( class_exists( 'WP_Service_Workers' ) );
	}
}
