<?php
/**
 * WP_Offline_Page_Exclusion class.
 *
 * @package PWA
 */

/**
 * Class is used to handle the Offline Page's exclusion from the backend and frontend.
 */
class WP_Offline_Page_Exclusion {

	/**
	 * Instance of the Offline Page Manager.
	 *
	 * @var WP_Offline_Page
	 */
	protected $manager;

	/**
	 * Array of the dropdown page names
	 *
	 * @var array
	 */
	protected $dropdown_page_names = array(
		'page_on_front',
		'page_for_posts',
		'page_for_privacy_policy',
		'_customize-dropdown-pages-page_on_front',
		'_customize-dropdown-pages-page_for_posts',
	);

	/**
	 * WP_Offline_Page_UI constructor.
	 *
	 * @param WP_Offline_Page $manager Instance of the manager.
	 */
	public function __construct( $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Initializes the instance.
	 */
	public function init() {
		add_filter( 'wp_dropdown_pages', array( $this, 'exclude_from_page_dropdown' ), 10, 2 );

		// Strategy 2 - Exclude from query.
		add_action( 'pre_get_posts', array( $this, 'exclude_from_query' ) );
	}

	/**
	 * Filters the HTML output of a list of pages as a drop down.
	 *
	 * @param string $html HTML output for drop down list of pages.
	 * @param array  $args The parsed arguments array.
	 *
	 * @return string
	 */
	public function exclude_from_page_dropdown( $html, $args ) {
		// Bail out if this is the offline page dropdown.
		if ( WP_Offline_Page::OPTION_NAME === $args['name'] ) {
			return $html;
		}

		return preg_replace( '/<option .* value="' . $this->manager->get_offline_page_id() . '">.*<\/option>/', '', $html );
	}

	/**
	 * Exclude the offline page from the query when doing a search on the frontend or on the backend Menus page.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 */
	public function exclude_from_query( $query ) {
		if ( $this->is_okay_to_exclude( $query ) ) {
			$query->set( 'post__not_in', array( $this->manager->get_offline_page_id() ) );
		}
	}

	/**
	 * Checks if the offline page should be excluded or not.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 *
	 * @return bool
	 */
	protected function is_okay_to_exclude( $query ) {
		// All searches should be excluded.
		if ( $query->is_search ) {
			return true;
		}

		// Handle Customizer.
		global $wp_customize;
		if ( is_object( $wp_customize ) && ! $query->is_page ) {
			return true;
		}

		// Only exclude when on the Menus page in the backend.
		if ( is_admin() ) {
			$screen = get_current_screen();

			return ( 'nav-menus' === $screen->id );
		}

		return ( $query->is_page && $query->is_singular );
	}
}
