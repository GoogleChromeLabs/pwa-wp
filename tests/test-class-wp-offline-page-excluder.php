<?php
/**
 * Tests for class WP_Offline_Page_Excluder.
 *
 * @package PWA
 */

/**
 * Tests for class WP_Offline_Page_Excluder.
 */
class Test_WP_Offline_Page_Excluder extends WP_UnitTestCase {

	/**
	 * Tested instance.
	 *
	 * @var WP_Offline_Page_Excluder
	 */
	public $instance;

	/**
	 * Array of post IDs to exclude.
	 *
	 * @var array
	 */
	private $post__not_in = array();

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->instance = new WP_Offline_Page_Excluder( new WP_Offline_Page() );
	}

	/**
	 * Test init.
	 *
	 * @covers WP_Offline_Page_Excluder::init()
	 */
	public function test_init() {
		$this->instance->init();
		$this->assertEquals( 10, has_filter( 'wp_dropdown_pages', array( $this->instance, 'exclude_from_page_dropdown' ) ) );
		$this->assertEquals( 10, has_filter( 'parse_query', array( $this->instance, 'exclude_from_query' ) ) );
	}

	/**
	 * Test exclude_from_page_dropdown.
	 *
	 * @covers WP_Offline_Page_Excluder::exclude_from_page_dropdown()
	 */
	public function test_exclude_from_page_dropdown() {
		$page_id = $this->factory()->post->create( array( 'post_type' => 'page' ) );
		$html    = <<<EOB
<select name="page_on_front" id="page_on_front">
	<option value="0">— Select —</option>
	<option class="level-0" value="7">Blog Page</option>
	<option class="level-0" value="5" selected="selected">Home page</option>
	<option class="level-0" value="13">Offline Page</option>	
	<option class="level-0" value="3">Privacy Policy</option>
	<option class="level-0" value="2">Sample Page</option>
</select>
EOB;
		$html    = str_replace( '13', $page_id, $html );

		// Check that the HTML is unchanged when no Offline Page exists.
		$this->assertSame(
			$html,
			$this->instance->exclude_from_page_dropdown( $html, array( 'name' => 'page_on_front' ) )
		);

		// Add the Offline Page and recheck.
		add_option( WP_Offline_Page::OPTION_NAME, $page_id );
		$this->assertNotContains(
			'<option class="level-0" value="' . $page_id . '">Offline Page</option>',
			$this->instance->exclude_from_page_dropdown( $html, array( 'name' => 'page_on_front' ) )
		);
	}

	/**
	 * Test is_exclude_from_query.
	 *
	 * @covers WP_Offline_Page_Excluder::is_exclude_from_query()
	 */
	public function test_is_exclude_from_query() {
		set_current_screen( 'front' );

		$offline_id = $this->factory()->post->create( array(
			'post_type'  => 'page',
			'post_title' => 'Offline page',
		) );
		$page_id    = $this->factory()->post->create( array(
			'post_type'  => 'page',
			'post_title' => 'Accessing via Offline',
		) );
		add_option( WP_Offline_Page::OPTION_NAME, $offline_id );
		add_action( 'parse_query', array( $this->instance, 'exclude_from_query' ) );

		// Check a page.
		$this->go_to( get_permalink( $page_id ) );
		$this->assertEquals( array( $offline_id ), get_query_var( 'post__not_in' ) );
		$this->assertSame( $page_id, get_queried_object()->ID );

		// Check search.
		$this->go_to( '?s=Offline' );
		$this->assertEquals( array( $offline_id ), get_query_var( 'post__not_in' ) );
		$this->assertTrue( have_posts() );
		$this->assertEquals( 1, $GLOBALS['wp_query']->post_count );

		// Check edit.php.
		set_current_screen( 'edit.php' );
		$this->go_to( admin_url( 'edit.php' ) );
		$this->assertEquals( array(), get_query_var( 'post__not_in' ) );

		// Check that is is excluded on the nav-menu.php query.
		set_current_screen( 'nav-menus.php' );
		$this->go_to( admin_url( 'nav-menus.php' ) );
		$this->assertEquals( array( $offline_id ), get_query_var( 'post__not_in' ) );

		set_current_screen( 'front' );

		// Check that the offline page is found when using the plan permalink.
		$this->go_to( "?page_id={$offline_id}" );
		$this->assertFalse( $GLOBALS['wp_query']->is_404() );
		$this->assertSame( $offline_id, get_queried_object()->ID );

		// Check that the offline page is found on the frontend.
		$this->set_permalink_structure( '/%postname%/' );
		$this->go_to( get_permalink( $offline_id ) );
		$this->assertEmpty( get_query_var( 'post__not_in' ) );
		$this->assertFalse( $GLOBALS['wp_query']->is_404() );
		$this->assertSame( $offline_id, get_queried_object()->ID );

		// Check that current 'post__not_in' merges with offline page id.
		$this->post__not_in   = array();
		$this->post__not_in[] = $this->factory()->post->create( array( 'post_type' => 'page' ) );
		$this->post__not_in[] = $this->factory()->post->create( array( 'post_type' => 'page' ) );
		add_action( 'parse_query', array( $this, 'set_post__not_in' ), 5 );
		$this->go_to( get_permalink( $this->post__not_in[0] ) );
		remove_action( 'parse_query', array( $this, 'set_post__not_in' ), 5 );
		$this->assertEquals( array_merge( $this->post__not_in, array( $offline_id ) ), get_query_var( 'post__not_in' ) );
	}

	/**
	 * Callback to set the "post__not_in" var.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 */
	public function set_post__not_in( $query ) {
		$query->set( 'post__not_in', $this->post__not_in );
	}
}
