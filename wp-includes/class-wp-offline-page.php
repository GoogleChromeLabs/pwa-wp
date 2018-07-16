<?php
/**
 * WP_Offline_Page class.
 *
 * @package PWA
 */

/**
 * WP_Offline_Page class.
 * Much of this is taken from wp-admin/privacy.php.
 */
class WP_Offline_Page {

	/**
	 * The option group for the offline page option.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'reading';

	/**
	 * The option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'page_for_offline';

	/**
	 * The ID of the settings section.
	 *
	 * @var string
	 */
	const SETTING_ID = 'pwa_offline_page';

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
	 * Initializes the instance.
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'init_admin' ) );
		add_action( 'admin_action_create-offline-page', array( $this, 'create_new_page' ) );
		add_action( 'admin_notices', array( $this, 'add_settings_error' ) );
		add_filter( 'display_post_states', array( $this, 'add_post_state' ), 10, 2 );
		add_filter( 'wp_dropdown_pages', array( $this, 'exclude_from_page_dropdown' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'exclude_from_query' ) );
	}

	/**
	 * Initializes the admin tasks.
	 */
	public function init_admin() {
		$this->get_offline_page_id( true );
		$this->init_static_pages();
		$this->register_setting();
		$this->add_settings_field();
	}

	/**
	 * Gets the offline page's ID.
	 *
	 * @param bool $hard Optional. When true or no ID, requests ID from `get_option`; else, uses the ID property.
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
	 * Initialize the static pages property.
	 */
	protected function init_static_pages() {
		foreach ( $this->static_pages as $option_name => $default ) {
			$this->static_pages[ $option_name ] = get_option( $option_name, $default );
		}
	}

	/**
	 * Registers the offline page setting.
	 */
	public function register_setting() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_callback' ),
			)
		);
	}

	/**
	 * Sanitize callback for the setting.
	 *
	 * @param string $raw_setting The setting before sanitizing it.
	 *
	 * @return string|null The sanitized setting, or null if it's invalid.
	 */
	public function sanitize_callback( $raw_setting ) {
		$sanitized_post_id = sanitize_text_field( $raw_setting );
		$offline_page      = get_post( $sanitized_post_id );

		if ( false === $this->add_settings_error( $offline_page ) ) {
			return $sanitized_post_id;
		}
	}

	/**
	 * Adds a settings field to the 'Reading Settings' page.
	 */
	public function add_settings_field() {
		add_settings_field(
			self::SETTING_ID,
			__( 'Page displays when offline', 'pwa' ),
			array( $this, 'render_settings' ),
			self::OPTION_GROUP
		);
	}

	/**
	 * Renders the settings section.
	 */
	public function render_settings() {
		$has_pages = $this->has_pages();

		if ( $has_pages ) :
			?>
			<label for="<?php echo esc_attr( self::OPTION_NAME ); ?>">
				<?php esc_html_e( 'Select an existing page:', 'pwa' ); ?>
			</label>
			<?php
			$this->render_page_dropdown();

			$create_text = __( 'create a new one', 'pwa' );
		else :
			$create_text = __( 'Create a new offline page', 'pwa' );
		endif;
		?>
		<p>
			<span>
				<?php
				if ( $has_pages ) {
					esc_html_e( 'or ', 'pwa' );
				}
				?>
				<a href="<?php echo esc_url( admin_url( 'options-reading.php?action=create-offline-page' ) ); ?>"><?php echo esc_html( $create_text ); ?></a>.
			</span>
		</p>
		<p class="description"><?php esc_html_e( 'This page is for the Progressive Web App (PWA), providing a default offline page. It is similar to a 404 page.  Unlike a 404 page that displays when the content is not found, this offline page shows when the person\'s internet is down, such as during a flight, or intermittent, such as going through tunnel.', 'pwa' ); ?></p>
		<?php
	}

	/**
	 * Renders the page dropdown.
	 */
	protected function render_page_dropdown() {
		wp_dropdown_pages(
			array(
				'name'              => esc_html( self::OPTION_NAME ),
				/* Translators: %1$s: A long dash */
				'show_option_none'  => sprintf( esc_html__( '%1$s Select %1$s', 'pwa' ), '&mdash;' ),
				'option_none_value' => '0',
				'selected'          => intval( $this->get_offline_page_id() ),
				'post_status'       => array( 'draft', 'publish' ),
				'exclude'           => esc_html( $this->get_static_pages( true ) ),
			)
		);
	}

	/**
	 * Creates a new offline page.
	 *
	 * @return bool|null
	 */
	public function create_new_page() {
		// Bail out if this is not the right page.
		$screen = get_current_screen();
		if ( ! $screen instanceof WP_Screen || 'options-reading' !== $screen->id ) {
			return false;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Offline Page', 'pwa' ),
				'post_status'  => 'draft',
				'post_type'    => 'page',
				'post_content' => $this->get_default_content(),
			),
			true
		);

		if ( ! is_wp_error( $page_id ) ) {
			update_option( self::OPTION_NAME, $page_id );
			if ( wp_redirect( admin_url( 'post.php?post=' . $page_id . '&action=edit' ) ) ) {
				exit;
			}

			return;
		}

		add_settings_error(
			self::OPTION_NAME,
			self::OPTION_NAME,
			__( 'Unable to create the offline page.', 'pwa' ),
			'error'
		);
	}

	/**
	 * Get the default content for a new offline page.
	 *
	 * @todo provide content here for shortcode (classic) or block (Gutenberg)
	 */
	protected function get_default_content() {
		return '';
	}

	/**
	 * Add a setting error message when the Offline Page does not exist or is in trash.
	 *
	 * @param WP_Post|null|string $offline_page Optional. Instance of the page's `WP_Post` or `null`. Default is an
	 *                                          empty string. When the default, attempts to look up the offline page.
	 *
	 * @return bool Returns true when setting error is added.
	 */
	public function add_settings_error( $offline_page = '' ) {
		if ( '' === $offline_page ) {
			if ( $this->get_offline_page_id() < 1 ) {
				return false;
			}

			$offline_page = get_post( $this->offline_page_id );
		}

		if ( $this->does_not_exist( $offline_page ) ) {
			add_settings_error(
				self::OPTION_NAME,
				self::OPTION_NAME,
				__( 'The current offline page does not exist. Please select or create one.', 'pwa' )
			);

			return true;
		}

		if ( $this->is_in_trash_error( $offline_page ) ) {
			add_settings_error(
				self::OPTION_NAME,
				self::OPTION_NAME,
				sprintf(
					/* translators: URL to Pages Trash */
					__( 'The currently offline page is in the trash. Please select or create one or <a href="%s">restore the current page</a>.', 'pwa' ),
					'edit.php?post_status=trash&post_type=page'
				)
			);

			return true;
		}

		return false;
	}

	/**
	 * When the given post is the offline page, add the state to the given post states.
	 *
	 * @since 1.0.0
	 *
	 * @param array   $post_states An array of post display states.
	 * @param WP_Post $post        The current post object.
	 *
	 * @return array
	 */
	public function add_post_state( array $post_states, $post ) {
		if ( $this->get_offline_page_id() === $post->ID ) {
			$post_states[] = __( 'Offline Page', 'pwa' );
		}

		return $post_states;
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
		// Bail out if this dropdown is not for a static pages.
		if ( ! in_array( $args['name'], $this->dropdown_page_names, true ) ) {
			return $html;
		}

		return preg_replace( '/<option .* value="' . $this->get_offline_page_id() . '">.*<\/option>/', '', $html );
	}

	/**
	 * Exclude the offline page from the query when doing a search on the frontend or on the backend Menus page.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 */
	public function exclude_from_query( $query ) {
		if ( $this->is_okay_to_exclude( $query ) ) {
			$query->set( 'post__not_in', array( $this->get_offline_page_id() ) );
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

	/**
	 * Gets the configured static pages.
	 *
	 * @param bool $join Optional. When true, a comma-delimited list is returned; else, an array is returned.
	 *
	 * @return array|string
	 */
	protected function get_static_pages( $join = false ) {
		// Remove the duplicates and empties.
		$static_pages = array_filter( array_unique( $this->static_pages ) );

		if ( ! $join ) {
			return $static_pages;
		}

		return $static_pages ? implode( ',', $static_pages ) : '';
	}

	/**
	 * Checks if there are any pages to display in the page dropdown.
	 *
	 * @return boolean
	 */
	protected function has_pages() {
		$query = new WP_Query( array(
			'post_type'      => 'page',
			'posts_per_page' => 1,
			'post_status'    => array( 'publish', 'draft' ),
			'post__not_in'   => $this->get_static_pages(), // exclude the static pages.
		) );

		return $query->found_posts > 0;
	}

	/**
	 * Check if the offline page does not exist.
	 *
	 * @param WP_Post|int $offline_page The offline page to check.
	 *
	 * @return bool
	 */
	protected function does_not_exist( $offline_page ) {
		if ( is_int( $offline_page ) ) {
			$offline_page = get_post( $offline_page );
		}

		return ! ( $offline_page instanceof WP_Post );
	}

	/**
	 * Check if the offline page is in the trash.
	 *
	 * @param WP_Post|int $offline_page The offline page to check.
	 *
	 * @return bool
	 */
	protected function is_in_trash_error( $offline_page ) {
		if ( is_int( $offline_page ) ) {
			$offline_page = get_post( $offline_page );
		}

		return 'trash' === $offline_page->post_status;
	}
}
