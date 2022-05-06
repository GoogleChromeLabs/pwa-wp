<?php
/**
 * Tests for class WP_Service_Worker_Navigation_Routing_Component.
 *
 * @package PWA
 */

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Tests for class WP_Service_Worker_Navigation_Routing_Component.
 *
 * @coversDefaultClass WP_Service_Worker_Navigation_Routing_Component
 */
class Test_WP_Service_Worker_Navigation_Routing_Component extends TestCase {

	/**
	 * Test registering a route.
	 *
	 * @covers ::serve()
	 */
	public function test_serve() {
		$this->markTestIncomplete();
	}

	/**
	 * Test get_priority.
	 *
	 * @covers ::get_priority()
	 */
	public function test_get_priority() {
		$instance = new WP_Service_Worker_Navigation_Routing_Component();
		$this->assertSame( 99, $instance->get_priority() );
	}

	/**
	 * Get data to test get_navigation_route_denylist_patterns.
	 *
	 * @return array
	 */
	public function get_data_to_test_get_navigation_route_denylist_patterns() {
		return array(
			'many_query_params'           => array(
				home_url( '/?ab=4&v0=56&u0=degrees+Celsius+%28°C%29&f0=C&v1=60&u1=degrees+Celsius+%28°C%29&f1=C&v2=0.0005&u2=per+degree+Celsius+%28%2F°C%29&f2=CdT&v3=100&u3=centimetres+%28cm%29&f3=cm&u4=centimetres+%28cm%29&f4=cm&v5=100&u5=sq+centimetres+%28cm²%29&f5=sqcm&v6=100.4&u6=sq+centimetres+%28cm²%29&f6=sqcm&v7=100&u7=cu+centimetres+%28cc%2C+cm³%29&f7=cucm&v8=100.6&u8=cu+centimetres+%28cc%2C+cm³%29&f8=cucm' ),
				false,
			),
			'admin_url'                   => array(
				admin_url(),
				true,
			),
			'admin_url_index'             => array(
				admin_url( '/index.php' ),
				true,
			),
			'admin_url_slash'             => array(
				admin_url( '/' ),
				true,
			),
			'login'                       => array(
				wp_login_url(),
				true,
			),
			'service_worker_front_pretty' => array(
				home_url( '/wp.serviceworker' ),
				true,
			),
			'service_worker_front_false'  => array(
				home_url( '/info/?url=/wp.serviceworker' ),
				false,
			),
			'service_worker_front_ugly'   => array(
				add_query_arg(
					array( WP_Service_Workers::QUERY_VAR => WP_Service_Workers::SCOPE_ADMIN ),
					home_url( '/', 'relative' )
				),
				true,
			),
			'service_worker_admin'        => array(
				wp_get_service_worker_url( WP_Service_Workers::SCOPE_ADMIN ),
				true,
			),
			'feed_url'                    => array(
				home_url( '/feed/' ),
				true,
			),
			'feed_rss2_url'               => array(
				home_url( '/feed/rss2/' ),
				true,
			),
			'customize_preview_1'         => array(
				home_url( '/?wp_customize=1' ),
				true,
			),
			'customize_preview_2'         => array(
				home_url( '/?foo=bar&wp_customize=1' ),
				true,
			),
			'customize_preview_4'         => array(
				home_url( '/?customize_changeset_uuid=...' ),
				true,
			),
			'customize_preview_5'         => array(
				home_url( '/?foo=bar&customize_changeset_uuid=...' ),
				true,
			),
			'about_page'                  => array(
				home_url( '/about/' ),
				false,
			),
			'php_file_in_query_param'     => array(
				home_url( '/inspect/?file=foo.php' ),
				false,
			),
			'rest_api'                    => array(
				get_rest_url(),
				true,
			),
			'filter'                      => array(
				home_url( '/denied/' ),
				true,
				static function () {
					add_filter(
						'wp_service_worker_navigation_route_denylist_patterns',
						static function ( $patterns ) {
							$patterns[] = '^.*?\/denied\/';
							return $patterns;
						}
					);
				},
			),
		);
	}

	/**
	 * Test get_navigation_route_denylist_patterns.
	 *
	 * @covers ::get_navigation_route_denylist_patterns()
	 * @dataProvider get_data_to_test_get_navigation_route_denylist_patterns
	 *
	 * @param string  $url      URL.
	 * @param bool    $expected Expected match.
	 * @param Closure $setup    Custom setup.
	 */
	public function test_get_navigation_route_denylist_patterns( $url, $expected, $setup = null ) {
		if ( $setup ) {
			$setup();
		}

		// Only the path and search are considered by Workbox in navigation requests.
		$parsed_url = wp_parse_url( $url );
		$url        = $parsed_url['path'];
		if ( ! empty( $parsed_url['query'] ) ) {
			$url .= '?' . $parsed_url['query'];
		}

		$instance  = new WP_Service_Worker_Navigation_Routing_Component();
		$patterns  = $instance->get_navigation_route_denylist_patterns();
		$matched   = null;
		$delimiter = chr( 1 );
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $delimiter . $pattern . $delimiter, $url ) ) {
				$matched = $pattern;
				break;
			}
		}
		$this->assertSame(
			$expected,
			(bool) $matched,
			wp_json_encode( compact( 'matched', 'url' ), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT )
		);
	}
}
