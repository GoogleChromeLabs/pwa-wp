<?php
/**
 * WP_Offline_Page_UI class.
 *
 * @package PWA
 */

/**
 * WP_Offline_Page_UI class.
 * Much of this is taken from wp-admin/privacy.php.
 */
class WP_Offline_Page_UI {

	/**
	 * The option group for the default offline page option.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'reading';

	/**
	 * The ID of the settings section.
	 *
	 * @var string
	 */
	const SETTING_ID = 'pwa_offline_page';

	/**
	 * Instance of the Offline Page Manager.
	 *
	 * @var WP_Offline_Page
	 */
	protected $manager;

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
		add_action( 'admin_init', array( $this, 'init_admin' ) );
		add_action( 'admin_action_create-offline-page', array( $this, 'handle_create_offline_page_action' ) );
		add_action( 'admin_notices', array( $this, 'add_settings_error' ) );
		add_filter( 'display_post_states', array( $this, 'add_post_state' ), 10, 2 );
	}

	/**
	 * Initializes the admin tasks.
	 */
	public function init_admin() {
		$this->register_setting();
		$this->add_settings_field();
	}

	/**
	 * Registers the default offline page setting.
	 */
	public function register_setting() {
		register_setting(
			self::OPTION_GROUP,
			WP_Offline_Page::OPTION_NAME,
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

		return null;
	}

	/**
	 * Adds a settings field to the 'Reading Settings' page.
	 */
	public function add_settings_field() {
		add_settings_field(
			self::SETTING_ID,
			__( 'Default offline status page', 'pwa' ),
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
			<label for="<?php echo esc_attr( WP_Offline_Page::OPTION_NAME ); ?>">
				<?php esc_html_e( 'Select an existing page:', 'pwa' ); ?>
			</label>
			<?php
			$this->render_page_dropdown();

			$create_text = __( 'create a new one', 'pwa' );
		else :
			$create_text = __( 'Create a new default offline page', 'pwa' );
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
		<p class="description"><?php esc_html_e( 'This page is for the Progressive Web App (PWA), providing a default offline page. It is similar to a 404 page.  Unlike a 404 page that displays when the content is not found, this default offline page shows when the person\'s internet is down, such as during a flight, or intermittent, such as going through tunnel.', 'pwa' ); ?></p>
		<?php
	}

	/**
	 * Renders the page dropdown.
	 */
	protected function render_page_dropdown() {
		$non_offline_static_pages = $this->manager->get_static_pages();
		unset( $non_offline_static_pages[ WP_Offline_Page::OPTION_NAME ] );

		wp_dropdown_pages(
			array(
				'name'              => esc_attr( WP_Offline_Page::OPTION_NAME ),
				/* Translators: %1$s: A long dash */
				'show_option_none'  => sprintf( esc_html__( '%1$s Select %1$s', 'pwa' ), '&mdash;' ),
				'option_none_value' => '0',
				'selected'          => intval( $this->manager->get_offline_page_id() ),
				'post_status'       => 'publish',
				'exclude'           => implode( ',', $non_offline_static_pages ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.XSS.EscapeOutput.OutputNotEscaped -- This is a false positive.
			)
		);
	}

	/**
	 * Handle the create-offline-page admin action.
	 *
	 * Will redirect to the newly created post upon success (and thus will not return).
	 */
	public function handle_create_offline_page_action() {

		// Bail out if this is not the right page.
		$screen = get_current_screen();
		if ( ! $screen instanceof WP_Screen || 'options-reading' !== $screen->id ) {
			return;
		}

		$r = $this->create_new_page();
		if ( is_wp_error( $r ) ) {
			add_settings_error(
				WP_Offline_Page::OPTION_NAME,
				WP_Offline_Page::OPTION_NAME,
				__( 'Unable to create the default offline page.', 'pwa' ),
				'error'
			);
		} else {
			wp_safe_redirect( get_edit_post_link( $r, 'raw' ) );
			exit;
		}
	}

	/**
	 * Creates a new default offline page.
	 *
	 * @return int|WP_Error The page ID or WP_Error on failure.
	 */
	public function create_new_page() {

		$page_id = wp_insert_post(
			wp_slash( array(
				'post_title'   => __( 'Offline', 'pwa' ),
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => $this->get_default_content(),
			) ),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}

		update_option( WP_Offline_Page::OPTION_NAME, $page_id );

		return $page_id;
	}

	/**
	 * Get the default content for a new default offline page.
	 *
	 * @todo In the future this could provide a shortcode (classic) or block (Gutenberg) that lists out the URLs that
	 *       are available offline.
	 *
	 * @return string Default content.
	 */
	protected function get_default_content() {
		return "<!-- wp:paragraph -->\n<p>" . __( 'It appears either that you are offline or the site is down.', 'pwa' ) . "</p>\n<!-- /wp:paragraph -->";
	}

	/**
	 * Add a setting error message when the default offline page does not exist or is in trash.
	 *
	 * @param WP_Post|null|string $offline_page Optional. Instance of the page's `WP_Post` or `null`. Default is an
	 *                                          empty string. When the default, attempts to look up the default offline
	 *                                          page.
	 *
	 * @return bool Returns true when setting error is added.
	 */
	public function add_settings_error( $offline_page = '' ) {
		if ( '' === $offline_page ) {
			if ( $this->manager->get_offline_page_id() < 1 ) {
				return false;
			}

			$offline_page = get_post( $this->manager->get_offline_page_id() );
		}

		if ( $this->does_not_exist( $offline_page ) ) {
			add_settings_error(
				WP_Offline_Page::OPTION_NAME,
				WP_Offline_Page::OPTION_NAME,
				__( 'The current default offline page does not exist. Please select or create one.', 'pwa' )
			);

			return true;
		}

		if ( $this->is_in_trash_error( $offline_page ) ) {
			add_settings_error(
				WP_Offline_Page::OPTION_NAME,
				WP_Offline_Page::OPTION_NAME,
				sprintf(
					/* translators: URL to Pages Trash */
					__( 'The default offline page is in the trash. Please select or create one or <a href="%s">restore the current page</a>.', 'pwa' ),
					'edit.php?post_status=trash&post_type=page'
				)
			);

			return true;
		}

		return false;
	}

	/**
	 * When the given post is the default offline page, add the state to the given post states.
	 *
	 * @since 0.2.0
	 *
	 * @param array   $post_states An array of post display states.
	 * @param WP_Post $post        The current post object.
	 *
	 * @return array
	 */
	public function add_post_state( array $post_states, $post ) {
		if ( $this->manager->get_offline_page_id() === $post->ID ) {
			$post_states[] = __( 'Default Offline Page', 'pwa' );
		}

		return $post_states;
	}

	/**
	 * Checks if there are any pages to display in the page dropdown.
	 *
	 * @return bool Whether there are pages to show in the dropdown.
	 */
	protected function has_pages() {
		$query = new WP_Query( array(
			'post_type'              => 'page',
			'posts_per_page'         => 1,
			'post_status'            => 'publish',
			'post__not_in'           => array_filter( $this->manager->get_static_pages() ), // exclude the static pages.
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		) );

		return $query->found_posts > 0;
	}

	/**
	 * Check if the default offline page does not exist.
	 *
	 * @param WP_Post|int $offline_page The offline page to check.
	 *
	 * @return bool Whether page does not exist.
	 */
	protected function does_not_exist( $offline_page ) {
		if ( is_int( $offline_page ) && $offline_page > 0 ) {
			$offline_page = get_post( $offline_page );
		}

		return ! ( $offline_page instanceof WP_Post );
	}

	/**
	 * Check if the default offline page is in the trash.
	 *
	 * @param WP_Post|int $offline_page The offline page to check.
	 *
	 * @return bool Whether the page is in the trash.
	 */
	protected function is_in_trash_error( $offline_page ) {
		if ( is_int( $offline_page ) && $offline_page > 0 ) {
			$offline_page = get_post( $offline_page );
		}

		return 'trash' === $offline_page->post_status;
	}
}
