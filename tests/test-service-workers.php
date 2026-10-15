<?php
/**
 * Tests for wp-includes/service-workers.php.
 *
 * @package PWA
 */

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Tests for service worker functions.
 */
class Test_Service_Workers_Includes extends TestCase {

	/**
	 * Tear down.
	 */
	public function tear_down() {
		parent::tear_down();
		$this->disable_permalinks();
	}

	/**
	 * Disable permalinks.
	 */
	private function disable_permalinks() {
		global $wp_rewrite;

		delete_option( 'permalink_structure' );
		$wp_rewrite->use_trailing_slashes = true;
		$wp_rewrite->init();
		$wp_rewrite->flush_rules();
	}

	/**
	 * Enable permalinks.
	 */
	private function enable_permalinks() {
		global $wp_rewrite;

		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );
		$wp_rewrite->use_trailing_slashes = true;
		$wp_rewrite->init();
		$wp_rewrite->flush_rules();
	}

	/**
	 * Test wp_get_service_worker_url() when permalinks are disabled.
	 *
	 * @covers ::wp_get_service_worker_url()
	 */
	public function test_wp_get_service_worker_url_without_permalinks() {
		global $wp_rewrite;
		$this->disable_permalinks();
		$this->assertFalse( $wp_rewrite->using_permalinks() );

		$this->assertEquals(
			add_query_arg(
				array( WP_Service_Workers::QUERY_VAR => WP_Service_Workers::SCOPE_FRONT ),
				home_url( '/', 'relative' )
			),
			wp_get_service_worker_url( WP_Service_Workers::SCOPE_FRONT )
		);

		$this->assertEquals(
			add_query_arg(
				array( 'action' => WP_Service_Workers::QUERY_VAR ),
				admin_url( 'admin-ajax.php' )
			),
			wp_get_service_worker_url( WP_Service_Workers::SCOPE_ADMIN )
		);
	}

	/**
	 * Test wp_get_service_worker_url() when called with a bad argument.
	 *
	 * @covers ::wp_get_service_worker_url()
	 * @expectedIncorrectUsage wp_get_service_worker_url
	 */
	public function test_wp_get_service_worker_url_without_permalinks_bad_scope() {
		global $wp_rewrite;
		$this->disable_permalinks();
		$this->assertFalse( $wp_rewrite->using_permalinks() );

		$this->assertEquals(
			add_query_arg(
				array( WP_Service_Workers::QUERY_VAR => WP_Service_Workers::SCOPE_FRONT ),
				home_url( '/', 'relative' )
			),
			wp_get_service_worker_url( 'bad' )
		);
	}

	/**
	 * Test wp_get_service_worker_url() when permalinks are enabled.
	 *
	 * @covers ::wp_get_service_worker_url()
	 */
	public function test_wp_get_service_worker_url_with_permalinks() {
		global $wp_rewrite;
		$this->enable_permalinks();
		$this->assertTrue( $wp_rewrite->using_permalinks() );

		$this->assertEquals(
			home_url( '/wp.serviceworker' ),
			wp_get_service_worker_url( WP_Service_Workers::SCOPE_FRONT )
		);

		$this->assertEquals(
			add_query_arg(
				array( 'action' => WP_Service_Workers::QUERY_VAR ),
				admin_url( 'admin-ajax.php' )
			),
			wp_get_service_worker_url( WP_Service_Workers::SCOPE_ADMIN )
		);
	}

	/**
	 * Test that in the admin the service worker script module is printed after the importmap.
	 *
	 * @see https://github.com/GoogleChromeLabs/pwa-wp/issues/1241
	 * @covers ::wp_print_service_workers()
	 */
	public function test_admin_service_worker_printed_after_importmap() {
		$this->assertFalse( has_action( 'admin_print_scripts', 'wp_print_service_workers' ) );

		$priority = has_action( 'admin_print_footer_scripts', 'wp_print_service_workers' );
		$this->assertIsInt( $priority );

		if ( ! function_exists( 'wp_script_modules' ) ) {
			$this->markTestSkipped( 'Script modules require WordPress 6.5.' );
		}
		$importmap_priority = has_action( 'admin_print_footer_scripts', array( wp_script_modules(), 'print_import_map' ) );
		$this->assertIsInt( $importmap_priority );
		$this->assertGreaterThan( $importmap_priority, $priority );
	}
}
