<?php
/**
 * Tests for template.php
 *
 * @package PWA
 */

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Tests for template.php
 */
class Test_Template extends TestCase {

	/**
	 * Test if function adding script on GET method, is_offline() or is_500 is true
	 *
	 * @covers ::wp_service_worker_offline_page_reload()
	 */
	public function test_wp_service_worker_offline_page_reload() {
		$this->assertEquals( 10, has_action( 'wp_footer', 'wp_service_worker_offline_page_reload' ) );
		$this->assertEquals( 10, has_action( 'error_footer', 'wp_service_worker_offline_page_reload' ) );

		// Check when method is GET but not offline or 500.
		$actual_script = get_echo( 'wp_service_worker_offline_page_reload' );
		$this->assertFalse( is_offline() );
		$this->assertFalse( is_500() );
		$this->assertEmpty( $actual_script );

		// Check if script is added when offline.
		$error_template_url = add_query_arg( 'wp_error_template', 'offline', home_url( '/', 'relative' ) );
		$this->go_to( $error_template_url );

		$actual_script = get_echo( 'wp_service_worker_offline_page_reload' );
		$this->assertTrue( is_offline() );
		$this->assertFalse( is_500() );
		$this->assertStringContainsString( '<script id="wp-offline-page-reload" type="module">', $actual_script );
		$this->assertStringContainsString( 'await fetch', $actual_script );

		// Check if script is added when 500.
		$error_template_url = add_query_arg( 'wp_error_template', '500', home_url( '/', 'relative' ) );
		$this->go_to( $error_template_url );

		$actual_script = get_echo( 'wp_service_worker_offline_page_reload' );
		$this->assertFalse( is_offline() );
		$this->assertTrue( is_500() );
		$this->assertStringContainsString( '<script id="wp-offline-page-reload" type="module">', $actual_script );
		$this->assertStringContainsString( 'await fetch', $actual_script );
	}
}
