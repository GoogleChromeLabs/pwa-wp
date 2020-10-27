<?php
/**
 * Tests for class WP_Service_Worker_Uploaded_Image_Caching_Component.
 *
 * @package PWA
 */

/**
 * Tests for class WP_Service_Worker_Uploaded_Image_Caching_Component.
 *
 * @coversDefaultClass WP_Service_Worker_Uploaded_Image_Caching_Component
 */
class Test_WP_Service_Worker_Uploaded_Image_Caching_Component extends WP_UnitTestCase {

	/**
	 * Get data for test_serve.
	 *
	 * @return array[]
	 */
	public function get_test_serve_data() {
		$default_route = sprintf(
			'^%s.*\.(%s)(\?.*)?$',
			preg_quote( trailingslashit( wp_get_upload_dir()['baseurl'] ), '/' ),
			implode(
				'|',
				array_map(
					static function ( $image_extension ) {
						return preg_quote( $image_extension, '/' );
					},
					wp_get_ext_types()['image']
				)
			)
		);

		$default_cache_name = 'uploaded-images';

		return array(
			'no_filter'                               => array(
				null,
				array(
					'strategy'   => WP_Service_Worker_Caching_Routes::STRATEGY_STALE_WHILE_REVALIDATE,
					'cache_name' => $default_cache_name,
					'expiration' => array(
						'max_age_seconds' => MONTH_IN_SECONDS,
						'max_entries'     => 100,
					),
					'route'      => $default_route,
				),
			),

			'disabling_filter'                        => array(
				'__return_empty_array',
				null,
			),

			'filtering_out_strategy'                  => array(
				function ( $args ) {
					unset( $args['strategy'] );
					return $args;
				},
				null,
			),

			'filtering_out_route'                     => array(
				function ( $args ) {
					unset( $args['route'] );
					return $args;
				},
				null,
			),

			'cache_first_strategy_and_plugin_changes' => array(
				function ( $args ) {
					$args['strategy'] = WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_FIRST;
					$args['expiration']['max_age_seconds'] = DAY_IN_SECONDS;
					$args['broadcast_update'] = array();
					return $args;
				},
				array(
					'strategy'         => WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_FIRST,
					'cache_name'       => $default_cache_name,
					'expiration'       => array(
						'max_entries'     => 100,
						'max_age_seconds' => DAY_IN_SECONDS,
					),
					'broadcast_update' => array(),
					'route'            => $default_route,
				),
			),
		);
	}

	/**
	 * Test registering a route.
	 *
	 * @dataProvider get_test_serve_data
	 *
	 * @param callable|null $filter_callback Filter callback.
	 * @param array|null    $expected_item   Expected item.
	 *
	 * @covers ::serve()
	 */
	public function test_serve( $filter_callback, $expected_item ) {
		if ( $filter_callback ) {
			add_filter( 'wp_service_worker_uploaded_image_caching', $filter_callback );
		}

		$component = new WP_Service_Worker_Uploaded_Image_Caching_Component();

		$scripts = new WP_Service_Worker_Scripts(
			array(
				'uploaded_image_caching' => $component,
				'caching_routes'         => new WP_Service_Worker_Caching_Routes_Component(),
			)
		);

		$this->assertEmpty( $scripts->caching_routes()->get_all() );

		$component->serve( $scripts );

		$all_scripts = $scripts->caching_routes()->get_all();

		if ( empty( $expected_item ) ) {
			$this->assertCount( 0, $all_scripts );
		} else {
			$this->assertCount( 1, $all_scripts );
			$entry = current( $all_scripts );
			$this->assertEquals( $entry, $expected_item );
		}
	}

	/**
	 * Test get_priority.
	 *
	 * @covers ::get_priority()
	 */
	public function test_get_priority() {
		$instance = new WP_Service_Worker_Uploaded_Image_Caching_Component();
		$this->assertSame( 10, $instance->get_priority() );
	}
}
