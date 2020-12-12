<?php
/**
 * Offline browsing field for the reading settings administration panel.
 *
 * As part of a core merge, the code in this file would go inside wp-admin/options-reading.php
 *
 * @package PWA
 */

namespace PWA_WP;

/**
 * Register offline browsing setting.
 */
function register_offline_browsing_setting() {
	register_setting(
		'reading',
		'offline_browsing',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\register_offline_browsing_setting' );

/**
 * Register offline browsing setting field.
 */
function add_offline_browsing_setting_field() {
	add_settings_field(
		'offline_browsing',
		__( 'Offline browsing', 'pwa' ),
		__NAMESPACE__ . '\render_offline_browsing_setting_field',
		'reading'
	);
}
add_action( 'admin_init', __NAMESPACE__ . '\add_offline_browsing_setting_field' );

/**
 * Render offline browsing setting field.
 */
function render_offline_browsing_setting_field() {
	?>
	<fieldset>
		<legend class="screen-reader-text"><span><?php esc_html_e( 'Offline browsing', 'pwa' ); ?> </span></legend>
		<label for="offline_browsing">
			<input name="offline_browsing" type="checkbox" id="offline_browsing" value="1" <?php checked( '1', get_option( 'offline_browsing' ) ); ?> />
			<?php esc_html_e( 'Cache visited pages in the browser so visitors can re-access then when offline.', 'pwa' ); ?>
		</label>
		<p class="description">
			<?php
			echo wp_kses(
				__( 'This makes your site dependable regardless of the network. Such reliability is important for the site to be a <a href="https://web.dev/what-are-pwas/" rel="noreferrer noopener" target="_blank">Progressive Web App</a>.', 'pwa' ),
				array( 'a' => array_fill_keys( array( 'href', 'rel', 'target' ), true ) )
			);
			?>
		</p>
	</fieldset>
	<?php
}
