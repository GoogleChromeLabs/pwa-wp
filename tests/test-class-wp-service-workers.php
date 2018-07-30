<?php
/**
 * Tests for class WP_Service_Workers.
 *
 * @package PWA
 */

/**
 * Tests for class WP_Web_App_Manifest.
 */
class Test_WP_Service_Workers extends WP_UnitTestCase {

	/**
	 * Tested instance.
	 *
	 * @var WP_Service_Workers
	 */
	public $instance;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		global $wp_actions, $wp_default_service_workers;
		parent::setUp();
		unset( $wp_default_service_workers );
		unset( $wp_actions['wp_default_service_workers'] );
		$this->instance = wp_service_workers();
	}

	/**
	 * Tear down.
	 */
	public function tearDown() {
		parent::tearDown();
		unset( $GLOBALS['wp_service_workers'] );
	}

	/**
	 * Test class constructor.
	 *
	 * @covers WP_Service_Workers::__construct()
	 * @covers WP_Service_Workers::init()
	 * @covers wp_service_workers()
	 */
	public function test_construct() {
		global $wp_service_workers;
		$this->assertEquals( 1, did_action( 'wp_default_service_workers' ) );
		$this->assertSame( $wp_service_workers, $this->instance );
		$this->assertInstanceOf( 'WP_Service_Workers', $this->instance );
		$this->instance->init();

		unset( $wp_service_workers );
		wp_service_workers();
		$this->assertEquals( 2, did_action( 'wp_default_service_workers' ) );
		wp_service_workers();
		$this->assertEquals( 2, did_action( 'wp_default_service_workers' ) );
	}

	/**
	 * Get scripts.
	 */
	public function get_scripts() {
		return array(
			'default' => array(
				'foo-all',
				'/test-sw.js',
				array( 'bar' ),
				null,
			),
			'all'     => array(
				'foo',
				'/test-sw.js',
				array(),
				WP_Service_Workers::SCOPE_ALL,
			),
			'front'   => array(
				'foo',
				'/test-sw.js',
				array(),
				WP_Service_Workers::SCOPE_FRONT,
			),
			'admin'   => array(
				'foo',
				'/test-sw.js',
				array(),
				WP_Service_Workers::SCOPE_ADMIN,
			),
		);
	}

	/**
	 * Test adding new service worker.
	 *
	 * @param string $handle Handle.
	 * @param string $src    Source.
	 * @param array  $deps   Dependencies.
	 * @param string $scope  Scope.
	 * @dataProvider get_scripts
	 * @covers WP_Service_Workers::add()
	 */
	public function test_register( $handle, $src, $deps, $scope = null ) {
		if ( $scope ) {
			$this->instance->register( $handle, $src, $deps, $scope );
		} else {
			$this->instance->register( $handle, $src, $deps );
		}
		if ( ! $scope ) {
			$scope = WP_Service_Workers::SCOPE_ALL;
		}
		$this->assertEquals( $scope, $this->instance->registered[ $handle ]->args['scope'] );
		$this->assertArrayHasKey( $handle, $this->instance->registered );
		$registered_sw = $this->instance->registered[ $handle ];
		$this->assertEquals( $src, $registered_sw->src );
		$this->assertEquals( $deps, $registered_sw->deps );
	}

	/**
	 * Test using invalid scope.
	 *
	 * @expectedIncorrectUsage WP_Service_Workers::register
	 */
	public function test_register_invalid_scope() {
		$this->instance->register( 'foo', '/test-sw.js', array( 'bar' ), 'bad' );
		$this->assertEquals( WP_Service_Workers::SCOPE_ALL, $this->instance->registered['foo']->args['scope'] );
	}

	/**
	 * Test serve_request.
	 *
	 * @covers WP_Service_Workers::serve_request()
	 * @covers WP_Service_Workers::do_items()
	 */
	public function test_serve_request() {
		wp_service_workers()->register( 'bar', array( $this, 'return_bar_sw' ), array( 'foo' ), WP_Service_Workers::SCOPE_FRONT );
		wp_service_workers()->register( 'baz', array( $this, 'return_baz_sw' ), array( 'foo' ), WP_Service_Workers::SCOPE_ADMIN );
		wp_service_workers()->register( 'foo', array( $this, 'return_foo_sw' ), array(), WP_Service_Workers::SCOPE_ALL );

		ob_start();
		wp_service_workers()->serve_request( 'bad' );
		$this->assertContains( 'invalid_scope_requested', ob_get_clean() );

		ob_start();
		wp_service_workers()->serve_request( WP_Service_Workers::SCOPE_FRONT );
		$output = ob_get_clean();
		$this->assertContains( $this->return_foo_sw(), $output );
		$this->assertContains( $this->return_bar_sw(), $output );
		$this->assertNotContains( $this->return_baz_sw(), $output );
		$this->assertTrue(
			strpos( $output, $this->return_foo_sw() ) < strpos( $output, $this->return_bar_sw() )
		);

		ob_start();
		wp_service_workers()->serve_request( WP_Service_Workers::SCOPE_ADMIN );
		$output = ob_get_clean();

		$this->assertContains( $this->return_foo_sw(), $output );
		$this->assertNotContains( $this->return_bar_sw(), $output );
		$this->assertContains( $this->return_baz_sw(), $output );
		$this->assertTrue(
			strpos( $output, $this->return_foo_sw() ) < strpos( $output, $this->return_baz_sw() )
		);
	}

	/**
	 * Test serve_request for bad src callback.
	 *
	 * @expectedIncorrectUsage WP_Service_Workers::register
	 * @covers WP_Service_Workers::serve_request()
	 * @covers WP_Service_Workers::do_items()
	 */
	public function test_serve_request_bad_src_callback() {
		wp_service_workers()->register( 'bar', array( 'Does_Not_Exist', 'return_bar_sw' ) );
		ob_start();
		wp_service_workers()->serve_request( WP_Service_Workers::SCOPE_ADMIN );
		$output = ob_get_clean();
		$this->assertContains( 'Service worker src is invalid', $output );
	}

	/**
	 * Test serve_request bad src URL.
	 *
	 * @expectedIncorrectUsage WP_Service_Workers::register
	 * @covers WP_Service_Workers::serve_request()
	 * @covers WP_Service_Workers::do_items()
	 */
	public function test_serve_request_bad_src_url() {
		wp_service_workers()->register( 'bar', '/food.png' );
		ob_start();
		wp_service_workers()->serve_request( WP_Service_Workers::SCOPE_FRONT );
		$output = ob_get_clean();
		$this->assertContains( 'Service worker src is invalid', $output );
	}

	/**
	 * Get some JS code.
	 *
	 * @return string JS example code.
	 */
	public function return_foo_sw() {
		return 'console.info("Hello Foo World");';
	}

	/**
	 * Get some JS code.
	 *
	 * @return string JS example code.
	 */
	public function return_bar_sw() {
		return 'console.info("Hello Bar World");';
	}

	/**
	 * Get some JS code.
	 *
	 * @return string JS example code.
	 */
	public function return_baz_sw() {
		return 'console.info("Hello Baz World");';
	}
}