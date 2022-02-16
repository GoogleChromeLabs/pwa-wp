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
		$actual_script = wp_service_worker_offline_page_reload();
		$this->assertEquals( $_SERVER['REQUEST_METHOD'], 'GET' );
		$this->assertFalse( is_offline() );
		$this->assertFalse( is_500() );
		$this->assertEmpty( $actual_script );

		// Check if script is added when offline.
		$error_template_url = add_query_arg( 'wp_error_template', 'offline', home_url( '/', 'relative' ) );
		$this->go_to( $error_template_url );

		ob_start();
		wp_service_worker_offline_page_reload();
		$actual_script = ob_get_clean();
		$this->assertEquals( $_SERVER['REQUEST_METHOD'], 'GET' );
		$this->assertTrue( is_offline() );
		$this->assertFalse( is_500() );
		$this->assertStringContainsString( '<script type="module">', $actual_script );
		$this->assertStringContainsString( 'await fetch(location.href, {method: \'HEAD\'})', $actual_script );

		// Check if script is added when 500.
		$error_template_url = add_query_arg( 'wp_error_template', '500', home_url( '/', 'relative' ) );
		$this->go_to( $error_template_url );

		ob_start();
		wp_service_worker_offline_page_reload();
		$actual_script = ob_get_clean();
		$this->assertEquals( $_SERVER['REQUEST_METHOD'], 'GET' );
		$this->assertFalse( is_offline() );
		$this->assertTrue( is_500() );
		$this->assertStringContainsString( '<script type="module">', $actual_script );
		$this->assertStringContainsString( 'await fetch(location.href, {method: \'HEAD\'})', $actual_script );

		$this->go_to( home_url( '/', 'relative' ) );

		// Check when method is not GET.
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$actual_script             = wp_service_worker_offline_page_reload();
		$this->assertEquals( $_SERVER['REQUEST_METHOD'], 'POST' );
		$this->assertFalse( is_offline() );
		$this->assertFalse( is_500() );
		$this->assertEmpty( $actual_script );
	}
}
