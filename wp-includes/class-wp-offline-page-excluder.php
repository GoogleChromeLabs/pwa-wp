<?php
/**
 * WP_Offline_Page_Excluder class.
 *
 * @package PWA
 */

/**
 * Class handles excluding the Offline Page's from the backend and frontend.
 */
class WP_Offline_Page_Excluder {

	/**
	 * Instance of the Offline Page Manager.
	 *
	 * @var WP_Offline_Page
	 */
	protected $manager;

	/**
	 * WP_Offline_Page_Filter constructor.
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
		add_action( 'parse_query', array( $this, 'exclude_from_query' ) );
	}

	/**
	 * Filters the HTML output of a list of pages as a drop down.
	 *
	 * @param string $html HTML output for drop down list of pages.
	 * @param array  $args The parsed arguments array.
	 *
	 * @return string Filtered content for wp_dropdown_pages.
	 */
	public function exclude_from_page_dropdown( $html, $args ) {
		// Bail out if this is the offline page dropdown.
		if ( WP_Offline_Page::OPTION_NAME === $args['name'] ) {
			return $html;
		}

		return preg_replace( '/<option .*? value="' . $this->manager->get_offline_page_id() . '">.*?<\/option>/', '', $html );
	}

	/**
	 * Exclude the offline page from the query when doing a search on the frontend or on the backend Menus page.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 */
	public function exclude_from_query( WP_Query $query ) {
		if ( $this->is_offline_page_query( $query ) ) {
			$query->is_404      = true;
			$query->set( 'page_id', 0 );
		} elseif ( $this->is_okay_to_exclude( $query ) ) {
			$offline      = array( $this->manager->get_offline_page_id() );
			$post__not_in = $query->get( 'post__not_in' );
			if ( ! empty( $post__not_in ) ) {
				$query->set( 'post__not_in', array_unique( array_merge( $post__not_in, $offline ) ) );
			} else {
				$query->set( 'post__not_in', $offline );
			}
		}
	}

	/**
	 * Checks if the query is for the offline page.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 *
	 * @return bool Whether an offline page query.
	 */
	protected function is_offline_page_query( WP_Query $query ) {
		if ( $query->is_admin ) {
			return false;
		}

		if ( ! $query->is_page || ! $query->is_singular ) {
			return false;
		}

		return ( $this->manager->get_offline_page_id() === $query->get( 'get_id' ) );
	}

	/**
	 * Checks if the offline page should be excluded or not.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 *
	 * @return bool OK to exclude.
	 */
	protected function is_okay_to_exclude( WP_Query $query ) {
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
		if ( $query->is_admin ) {
			$screen = get_current_screen();

			return ( 'nav-menus' === $screen->id );
		}

		return ( $query->is_page && $query->is_singular );
	}
}
