<?php
/**
 * Tests for general-template.php.
 *
 * @package PWA
 */

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Tests for general-template.php.
 */
class Test_General_Template extends TestCase {

	/**
	 * Get data for testing wp_unauthenticate_error_template_requests().
	 *
	 * @return array Data.
	 */
	public function get_error_template_request_data() {
		return array(
			'homepage' => array(
				home_url( '/' ),
				true,
				static function () {
					return ! is_500() && ! is_offline();
				},
			),
			'500'      => array(
				home_url( '/?wp_error_template=500' ),
				false,
				static function () {
					return is_500() && ! is_offline();
				},
			),
			'offline'  => array(
				home_url( '/?wp_error_template=offline' ),
				false,
				static function () {
					return ! is_500() && is_offline();
				},
			),
		);
	}

	/**
	 * Test wp_unauthenticate_error_template_requests.
	 *
	 * @dataProvider get_error_template_request_data
	 * @covers ::wp_unauthenticate_error_template_requests()
	 * @covers ::is_500()
	 * @covers ::is_offline()
	 * @covers ::pwa_add_public_query_vars()
	 *
	 * @param string   $request_url        URL.
	 * @param bool     $authenticated      Authentication expected.
	 * @param callable $check_conditionals Check conditionals.
	 */
	public function test_wp_unauthenticate_error_template_requests( $request_url, $authenticated, $check_conditionals ) {
		$this->assertEquals( 10, has_action( 'parse_query', 'wp_unauthenticate_error_template_requests' ) );
		$initial_parse_query_count = did_action( 'parse_query' );

		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );
		$this->go_to( $request_url );

		$this->assertEquals( $initial_parse_query_count + 1, did_action( 'parse_query' ) );
		$this->assertTrue( $check_conditionals() );

		if ( $authenticated ) {
			$this->assertTrue( is_user_logged_in() );
			$this->assertEquals( $user_id, get_current_user_id() );
		} else {
			$this->assertFalse( is_user_logged_in() );
			$this->assertEquals( 0, get_current_user_id() );
		}
	}

	/**
	 * Test that that `wp_unauthenticate_error_template_requests()` running at the `parse_query` action doesn't cause
	 * an incorrect usage notice if there is not global `$wp_query` yet, such as when doing a subquery early in the WP
	 * execution flow.
	 *
	 * @covers ::wp_unauthenticate_error_template_requests()
	 */
	public function test_wp_unauthenticate_error_template_requests_for_subqueries() {
		global $wp_query;
		$wp_query = null;

		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$query = new WP_Query();
		$query->parse_query( 's=test' );

		$this->assertTrue( is_user_logged_in() );
	}
}
