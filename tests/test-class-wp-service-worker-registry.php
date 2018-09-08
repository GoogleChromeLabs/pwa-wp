<?php
/**
 * Tests for class WP_Service_Worker_Cache_Registry.
 *
 * @package PWA
 */

/**
 * Tests for class WP_Service_Worker_Cache_Registry.
 */
class Test_WP_Service_Worker_Cache_Registry extends WP_UnitTestCase {

	/**
	 * Tested instance.
	 *
	 * @var WP_Service_Worker_Cache_Registry
	 */
	public $instance;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->instance = new WP_Service_Worker_Cache_Registry();
	}

	/**
	 * Test registering a cached route.
	 *
	 * @param string $route         Route.
	 * @param string $strategy      Strategy.
	 * @param array  $strategy_args Optional. Strategy arguments. Default empty array.
	 *
	 * @dataProvider data_register_cached_route
	 * @covers WP_Service_Worker_Cache_Registry::register_cached_route()
	 */
	public function test_register_cached_route( $route, $strategy, $strategy_args = array() ) {
		$this->instance->register_cached_route( $route, $strategy, $strategy_args );

		$routes = $this->instance->get_cached_routes();
		$this->assertNotEmpty( $routes );

		$this->assertEqualSetsWithIndex(
			array(
				'route'         => $route,
				'strategy'      => $strategy,
				'strategy_args' => $strategy_args,
			),
			array_pop( $routes )
		);
	}

	/**
	 * Get valid cached routes.
	 *
	 * @return array List of arguments to pass to test_register_cached_route().
	 */
	public function data_register_cached_route() {
		return array(
			array(
				'/\.(?:js|css)$/',
				WP_Service_Worker_Cache_Registry::STRATEGY_STALE_WHILE_REVALIDATE,
				array(
					'cacheName' => 'static-resources',
				),
			),
			array(
				'/\.(?:png|gif|jpg|jpeg|svg)$/',
				WP_Service_Worker_Cache_Registry::STRATEGY_CACHE_FIRST,
				array(
					'cacheName' => 'images',
				),
			),
			array(
				'https://hacker-news.firebaseio.com/v0/*',
				WP_Service_Worker_Cache_Registry::STRATEGY_NETWORK_FIRST,
				array(
					'networkTimeoutSeconds' => 3,
					'cacheName'             => 'stories',
				),
			),
			array(
				'/.*(?:googleapis)\.com.*$/',
				WP_Service_Worker_Cache_Registry::STRATEGY_NETWORK_ONLY,
				array(),
			),
			array(
				'/.*(?:gstatic)\.com.*$/',
				WP_Service_Worker_Cache_Registry::STRATEGY_CACHE_ONLY,
				array(),
			),
		);
	}

	/**
	 * Test registering a cached route with an invalid route.
	 *
	 * @covers WP_Service_Worker_Cache_Registry::register_cached_route()
	 * @expectedIncorrectUsage WP_Service_Worker_Cache_Registry::register_cached_route
	 */
	public function test_register_cached_route_invalid_route() {
		$this->instance->register_cached_route( 3, WP_Service_Worker_Cache_Registry::STRATEGY_STALE_WHILE_REVALIDATE );
	}

	/**
	 * Test registering a cached route with an invalid strategy.
	 *
	 * @covers WP_Service_Worker_Cache_Registry::register_cached_route()
	 * @expectedIncorrectUsage WP_Service_Worker_Cache_Registry::register_cached_route
	 */
	public function test_register_cached_route_invalid_strategy() {
		$this->instance->register_cached_route( '/\.(?:js|css)$/', 'invalid' );
	}

	/**
	 * Test registering a precached route.
	 *
	 * @param string $url     URL.
	 * @param array  $options Optional. Options. Default empty array.
	 *
	 * @dataProvider data_register_precached_route
	 * @covers WP_Service_Worker_Cache_Registry::register_precached_route()
	 */
	public function test_register_precached_route( $url, $options = array() ) {
		$this->instance->register_precached_route( $url, $options );

		$routes = $this->instance->get_precached_routes();
		$this->assertNotEmpty( $routes );

		$expected = array(
			'revision' => null,
		);
		if ( ! is_array( $options ) ) {
			$expected['revision'] = $options;
		} else {
			$expected = array_merge( $expected, $options );
		}

		$expected['url'] = $url;

		$this->assertEqualSetsWithIndex(
			$expected,
			array_pop( $routes )
		);
	}

	/**
	 * Get valid precached routes.
	 *
	 * @return array List of arguments to pass to test_register_precached_route().
	 */
	public function data_register_precached_route() {
		return array(
			array(
				'/assets/style.css',
				array(
					'revision' => '1.0.0',
				),
			),
			array(
				'/assets/script.js',
				'1.0.0',
			),
			array(
				'/assets/font.ttf',
				array(),
			),
		);
	}

	/**
	 * Test registering a precached route with an unrecognized option.
	 *
	 * @covers WP_Service_Worker_Cache_Registry::register_precached_route()
	 * @expectedIncorrectUsage WP_Service_Worker_Cache_Registry::register_precached_route
	 */
	public function test_register_precached_route_invalid_revision() {
		$this->instance->register_precached_route( '/assets/style.css', array(
			'revision' => '1.0.0',
			'bogus'    => 'yes',
		) );
	}
}
