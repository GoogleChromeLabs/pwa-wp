<?php
/**
 * WP_Offline_Page class.
 *
 * @package PWA
 */

/**
 * This class manages the Offline Page.
 */
class WP_Offline_Page {

	/**
	 * The option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'page_for_offline';

	/**
	 * Instance of UI handler.
	 *
	 * @var WP_Offline_Page_UI
	 */
	protected $ui_handler;

	/**
	 * Instance of the filter handler.
	 *
	 * @var WP_Offline_Page_Excluder
	 */
	protected $excluder;

	/**
	 * WP_Offline_Page constructor.
	 */
	public function __construct() {
		require_once dirname( __FILE__ ) . '/class-wp-offline-page-ui.php';
		require_once dirname( __FILE__ ) . '/class-wp-offline-page-excluder.php';

		$this->ui_handler = new WP_Offline_Page_UI( $this );
		$this->excluder   = new WP_Offline_Page_Excluder( $this );
	}

	/**
	 * Initializes the manager.
	 */
	public function init() {
		$this->ui_handler->init();
		$this->excluder->init();
	}

	/**
	 * Gets the offline page's ID.
	 *
	 * @return int ID for the offline page.
	 */
	public function get_offline_page_id() {
		return (int) get_option( self::OPTION_NAME, 0 );
	}

	/**
	 * Gets the static pages by storing each page's ID into a property.
	 *
	 * @return int[] Static page IDs.
	 */
	public function get_static_pages() {
		return array(
			'page_on_front'   => (int) get_option( 'page_on_front', 0 ),
			'page_for_posts'  => (int) get_option( 'page_for_posts', 0 ),
			self::OPTION_NAME => $this->get_offline_page_id(),
		);
	}
}
