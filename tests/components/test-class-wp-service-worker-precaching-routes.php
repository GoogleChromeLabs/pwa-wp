<?php
/**
 * Tests for class WP_Service_Worker_Precaching_Routes.
 *
 * @package PWA
 */

/**
 * Tests for class WP_Service_Worker_Precaching_Routes.
 *
 * @coversDefaultClass WP_Service_Worker_Precaching_Routes
 */
class Test_WP_Service_Worker_Precaching_Routes extends WP_UnitTestCase {

	/**
	 * Tested instance.
	 *
	 * @var WP_Service_Worker_Precaching_Routes
	 */
	private $instance;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->instance = new WP_Service_Worker_Precaching_Routes();
	}

	/**
	 * Test registering a route.
	 *
	 * @param string $url  URL.
	 * @param array  $args URL arguments.
	 *
	 * @dataProvider data_register
	 * @covers ::register()
	 */
	public function test_register( $url, $args = array() ) {
		$this->instance->register( $url, $args );

		$routes = $this->instance->get_all();
		$this->assertNotEmpty( $routes );

		$expected = array(
			'revision' => null,
		);
		if ( ! is_array( $args ) ) {
			$expected['revision'] = $args;
		} else {
			$expected = array_merge( $expected, $args );
		}

		$expected['url'] = $url;

		$this->assertEqualSetsWithIndex(
			$expected,
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
	 * Test registering a route that is empty.
	 *
	 * @covers ::register()
	 */
	public function test_register_empty_url() {
		$this->setExpectedIncorrectUsage( 'WP_Service_Worker_Precaching_Routes::register' );
		$this->instance->register( '' );

		$routes = $this->instance->get_all();
		$this->assertEmpty( $routes );
	}
}
