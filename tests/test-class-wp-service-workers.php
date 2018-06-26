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
		parent::setUp();
		$this->instance = new WP_Service_Workers();
	}

	/**
	 * Test class constructor.
	 *
	 * @covers WP_Service_Workers::__construct()
	 */
	public function test_construct() {
		$service_workers = new WP_Service_Workers();
		$this->assertEquals( 'WP_Service_Workers', get_class( $service_workers ) );
	}

	/**
	 * Test adding new service worker.
	 *
	 * @covers WP_Service_Workers::add()
	 */
	public function test_register() {
		$this->instance->register( 'foo', '/test-sw.js', array( 'bar' ) );

		$default_scope = site_url( '/', 'relative' );

		$this->assertTrue( in_array( $default_scope, $this->instance->get_scopes(), true ) );
		$this->assertTrue( isset( $this->instance->registered['foo'] ) );

		$registered_sw = $this->instance->registered['foo'];

		$this->assertEquals( '/test-sw.js', $registered_sw->src );
		$this->assertTrue( in_array( $default_scope, $registered_sw->args['scopes'], true ) );
		$this->assertEquals( array( 'bar' ), $registered_sw->deps );
	}
}
