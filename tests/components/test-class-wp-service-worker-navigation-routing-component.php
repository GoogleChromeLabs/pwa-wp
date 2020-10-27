<?php
/**
 * Tests for class WP_Service_Worker_Navigation_Routing_Component.
 *
 * @package PWA
 */

/**
 * Tests for class WP_Service_Worker_Navigation_Routing_Component.
 *
 * @coversDefaultClass WP_Service_Worker_Navigation_Routing_Component
 */
class Test_WP_Service_Worker_Navigation_Routing_Component extends WP_UnitTestCase {

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
}
