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
	public function tearDown() {
		parent::tearDown();
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
}
