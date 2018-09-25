<?php
/**
 * Tests for class WP_Service_Worker_Scripts.
 *
 * @package PWA
 */

/**
 * Tests for class WP_Service_Worker_Scripts.
 */
class Test_WP_Service_Worker_Scripts extends WP_UnitTestCase {

	/**
	 * Tested instance.
	 *
	 * @var WP_Service_Worker_Scripts
	 */
	private $instance;

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

		$this->instance = wp_service_workers()->get_registry();
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
	 * @covers WP_Service_Worker_Scripts::__construct()
	 * @covers WP_Service_Worker_Scripts::init()
	 */
	public function test_construct() {
		global $wp_service_workers;

		$this->assertEquals( 1, did_action( 'wp_default_service_workers' ) );
		$this->assertSame( $wp_service_workers->get_registry(), $this->instance );
		$this->assertInstanceOf( 'WP_Service_Worker_Scripts', $this->instance );
		$this->instance->init();
	}

	/**
	 * Test registering a service worker script.
	 *
	 * @param string $handle Handle.
	 * @param array  $args   Handle arguments.
	 *
	 * @dataProvider data_register
	 * @covers WP_Service_Worker_Scripts::register()
	 */
	public function test_register( $handle, $args ) {
		$this->instance->register( $handle, $args );
		$this->assertArrayHasKey( $handle, $this->instance->registered );

		if ( is_string( $args ) ) {
			$args = array( 'src' => $args );
		}

		$src  = ! empty( $args['src'] ) ? $args['src'] : '';
		$deps = ! empty( $args['deps'] ) ? $args['deps'] : array();

		$registered_sw = $this->instance->registered[ $handle ];
		$this->assertEquals( $src, $registered_sw->src );
		$this->assertEquals( $deps, $registered_sw->deps );
	}

	/**
	 * Get valid scripts.
	 */
	public function data_register() {
		return array(
			array(
				'foo-all',
				array(
					'src'  => '/test-sw.js',
					'deps' => array( 'bar' ),
				),
			),
			array(
				'foo',
				array(
					'src'  => '/test-sw.js',
					'deps' => array(),
				),
			),
			array(
				'foo',
				'/test-sw.js',
			),
		);
	}

	/**
	 * Test registering a service worker script without a src.
	 *
	 * @covers WP_Service_Worker_Scripts::register()
	 * @expectedIncorrectUsage WP_Service_Worker_Scripts::register
	 */
	public function test_register_invalid_scope() {
		$this->instance->register( 'foo', array() );
	}
}
