<?php
/**
 * Tests for class WP_Service_Worker_Caching_Routes.
 *
 * @package PWA
 */

/**
 * Tests for class WP_Service_Worker_Caching_Routes.
 */
class Test_WP_Service_Worker_Caching_Routes extends WP_UnitTestCase {

	/**
	 * Tested instance.
	 *
	 * @var WP_Service_Worker_Caching_Routes
	 */
	private $instance;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->instance = new WP_Service_Worker_Caching_Routes();
	}

	/**
	 * Test registering a route.
	 *
	 * @param string $route Route.
	 * @param array  $args  Route arguments.
	 *
	 * @dataProvider data_register
	 * @covers WP_Service_Worker_Caching_Routes::register()
	 */
	public function test_register( $route, $args ) {
		$this->instance->register( $route, $args );

		$routes = $this->instance->get_all();
		$this->assertNotEmpty( $routes );

		$strategy = '';
		if ( isset( $args['strategy'] ) ) {
			$strategy = $args['strategy'];
			unset( $args['strategy'] );
		}

		$this->assertEqualSetsWithIndex(
			array(
				'route'         => $route,
				'strategy'      => $strategy,
				'strategy_args' => $args,
			),
			array_pop( $routes )
		);
	}

	/**
	 * Get valid routes.
	 *
	 * @return array List of arguments to pass to test_register().
	 */
	public function data_register() {
		return array(
			array(
				'/\.(?:js|css)$/',
				array(
					'strategy'  => WP_Service_Worker_Caching_Routes::STRATEGY_STALE_WHILE_REVALIDATE,
					'cacheName' => 'static-resources',
				),
			),
			array(
				'/\.(?:png|gif|jpg|jpeg|svg)$/',
				array(
					'strategy'  => WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_FIRST,
					'cacheName' => 'images',
				),
			),
			array(
				'https://hacker-news.firebaseio.com/v0/*',
				array(
					'strategy'              => WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_FIRST,
					'networkTimeoutSeconds' => 3,
					'cacheName'             => 'stories',
				),
			),
			array(
				'/.*(?:googleapis)\.com.*$/',
				array(
					'strategy' => WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_ONLY,
				),
			),
			array(
				'/.*(?:gstatic)\.com.*$/',
				array(
					'strategy' => WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_ONLY,
				),
			),
		);
	}

	/**
	 * Test registering a route with an invalid route.
	 *
	 * @covers WP_Service_Worker_Caching_Routes::register()
	 * @expectedIncorrectUsage WP_Service_Worker_Caching_Routes::register
	 */
	public function test_register_invalid_route() {
		$this->instance->register( 3, WP_Service_Worker_Caching_Routes::STRATEGY_STALE_WHILE_REVALIDATE );
	}

	/**
	 * Test registering a route with an invalid strategy.
	 *
	 * @covers WP_Service_Worker_Caching_Routes::register()
	 * @expectedIncorrectUsage WP_Service_Worker_Caching_Routes::register
	 */
	public function test_register_invalid_strategy() {
		$this->instance->register( '/\.(?:js|css)$/', 'invalid' );
	}

	/**
	 * Test registering a route without a strategy.
	 *
	 * @covers WP_Service_Worker_Caching_Routes::register()
	 * @expectedIncorrectUsage WP_Service_Worker_Caching_Routes::register
	 */
	public function test_register_missing_strategy() {
		$this->instance->register( '/\.(?:js|css)$/', array() );
	}
}
