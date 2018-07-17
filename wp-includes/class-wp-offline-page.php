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
	 * Offline Page post ID.
	 *
	 * @var int
	 */
	protected $offline_page_id = 0;

	/**
	 * Array of the static pages.
	 *
	 * @var array
	 */
	protected $static_pages = array(
		'page_on_front'              => 0,
		'page_for_posts'             => 0,
		'wp_page_for_privacy_policy' => 0,
	);

	/**
	 * Instance of UI handler.
	 *
	 * @var WP_Offline_Page_UI
	 */
	protected $ui_handler;

	/**
	 * Instance of the filter handler.
	 *
	 * @var WP_Offline_Page_Filter
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
		add_action( 'admin_init', array( $this, 'init_admin' ) );

		$this->ui_handler->init();
		$this->excluder->init();
	}

	/**
	 * Initializes the admin tasks.
	 */
	public function init_admin() {
		$this->get_offline_page_id( true );
		$this->get_static_pages( true );
	}

	/**
	 * Gets the offline page's ID.
	 *
	 * @param bool $hard Optional. When true or no ID, requests ID from `get_option`; else, uses the ID property.
	 *                   Default is `false`.
	 *
	 * @return int
	 */
	public function get_offline_page_id( $hard = false ) {
		if ( $hard || $this->offline_page_id < 1 ) {
			$this->offline_page_id = (int) get_option( self::OPTION_NAME, 0 );
		}

		return $this->offline_page_id;
	}

	/**
	 * Gets the static pages by storing each page's ID into a property.
	 *
	 * @param bool $hard Optional. When true, rebuilds the static pages cache by getting the option; else, it returns
	 *                   the property.  Default is `false`.
	 *
	 * @return array
	 */
	public function get_static_pages( $hard = false ) {
		if ( ! $hard ) {
			return $this->static_pages;
		}

		foreach ( $this->static_pages as $option_name => $default ) {
			$this->static_pages[ $option_name ] = get_option( $option_name, $default );
		}

		return $this->static_pages;
	}
}
