<?php
/**
 * Tests for class WP_Service_Workers.
 *
 * @package PWA
 */

/**
 * Tests for class WP_Service_Workers.
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
		global $wp_actions, $wp_service_workers;
		parent::setUp();
		unset( $wp_actions['wp_default_service_workers'] );
		$wp_service_workers = null;

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
	 */
	public function test_construct() {
		global $wp_service_workers;
		$this->assertEquals( 1, did_action( 'wp_default_service_workers' ) );
		$this->assertSame( $wp_service_workers, $this->instance );
		$this->assertInstanceOf( 'WP_Service_Workers', $this->instance );
	}

	/**
	 * Test serve_request for front scope.
	 *
	 * @covers WP_Service_Workers::serve_request()
	 * @covers WP_Service_Worker_Scripts::do_items()
	 */
	public function test_serve_request_front() {
		add_action( 'wp_front_service_worker', array( $this, 'register_bar_sw' ) );
		add_action( 'wp_admin_service_worker', array( $this, 'register_baz_sw' ) );
		add_action( 'wp_front_service_worker', array( $this, 'register_foo_sw' ) );
		add_action( 'wp_admin_service_worker', array( $this, 'register_foo_sw' ) );

		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'subscriber' ) ) );
		ob_start();
		wp_service_workers()->serve_request();
		$output = ob_get_clean();

		$this->assertSame( 0, get_current_user_id() );
		$this->assertStringContainsString( $this->return_foo_sw(), $output );
		$this->assertStringContainsString( $this->return_bar_sw(), $output );
		$this->assertStringNotContainsString( $this->return_baz_sw(), $output );
		$this->assertTrue(
			strpos( $output, $this->return_foo_sw() ) < strpos( $output, $this->return_bar_sw() )
		);
	}

	/**
	 * Test serve_request for admin scope.
	 *
	 * @covers WP_Service_Workers::serve_request()
	 * @covers WP_Service_Worker_Scripts::do_items()
	 */
	public function test_serve_request_admin() {
		add_action( 'wp_front_service_worker', array( $this, 'register_bar_sw' ) );
		add_action( 'wp_admin_service_worker', array( $this, 'register_baz_sw' ) );
		add_action( 'wp_front_service_worker', array( $this, 'register_foo_sw' ) );
		add_action( 'wp_admin_service_worker', array( $this, 'register_foo_sw' ) );

		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		set_current_screen( 'admin-ajax' );
		wp_service_workers()->serve_request();
		$output = ob_get_clean();

		$this->assertSame( 0, get_current_user_id() );
		$this->assertStringContainsString( $this->return_foo_sw(), $output );
		$this->assertStringNotContainsString( $this->return_bar_sw(), $output );
		$this->assertStringContainsString( $this->return_baz_sw(), $output );
		$this->assertTrue(
			strpos( $output, $this->return_foo_sw() ) < strpos( $output, $this->return_baz_sw() )
		);
	}

	/**
	 * Test serve_request for bad src callback.
	 *
	 * @covers WP_Service_Workers::serve_request()
	 * @covers WP_Service_Worker_Scripts::do_items()
	 * @expectedIncorrectUsage WP_Service_Worker_Scripts::register
	 */
	public function test_serve_request_bad_src_callback() {
		wp_register_service_worker_script(
			'bar',
			array(
				'src' => array( 'Does_Not_Exist', 'return_bar_sw' ),
			)
		);

		ob_start();
		set_current_screen( 'admin-ajax' );
		wp_service_workers()->serve_request();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Service worker src is invalid', $output );
	}

	/**
	 * Test serve_request bad src URL.
	 *
	 * @covers WP_Service_Workers::serve_request()
	 * @covers WP_Service_Worker_Scripts::do_items()
	 * @expectedIncorrectUsage WP_Service_Worker_Scripts::register
	 */
	public function test_serve_request_bad_src_url() {
		wp_register_service_worker_script(
			'bar',
			array(
				'src' => '/food.png',
			)
		);

		ob_start();
		wp_service_workers()->serve_request();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Service worker src is invalid', $output );
	}

	/**
	 * Register a service worker.
	 *
	 * @param WP_Service_Worker_Scripts $scripts Registry instance.
	 */
	public function register_foo_sw( $scripts ) {
		$scripts->register(
			'foo',
			array(
				'src'  => array( $this, 'return_foo_sw' ),
				'deps' => array(),
			)
		);
	}

	/**
	 * Register a service worker.
	 *
	 * @param WP_Service_Worker_Scripts $scripts Registry instance.
	 */
	public function register_bar_sw( $scripts ) {
		$scripts->register(
			'bar',
			array(
				'src'  => array( $this, 'return_bar_sw' ),
				'deps' => array( 'foo' ),
			)
		);
	}

	/**
	 * Register a service worker.
	 *
	 * @param WP_Service_Worker_Scripts $scripts Registry instance.
	 */
	public function register_baz_sw( $scripts ) {
		$scripts->register(
			'baz',
			array(
				'src'  => array( $this, 'return_baz_sw' ),
				'deps' => array( 'foo' ),
			)
		);
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
