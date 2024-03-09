<?php
/**
 * Web App manifest display field for the reading settings administration panel.
 *
 * As part of a core merge, the code in this file would go inside wp-admin/options-reading.php
 *
 * @package PWA
 */

namespace PWA_WP;

/**
 * Register web app manifest display setting.
 */
function register_web_app_manifest_display_setting() {
	register_setting(
		'reading',
		'web_app_manifest_display',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\register_web_app_manifest_display_setting' );

/**
 * Register web app manifest display setting field.
 */
function add_web_app_manifest_display_setting_field() {
	add_settings_field(
		'web_app_manifest_display',
		__( 'Display', 'pwa' ),
		__NAMESPACE__ . '\render_web_app_manifest_display_setting_field',
		'reading'
	);
}
add_action( 'admin_init', __NAMESPACE__ . '\add_web_app_manifest_display_setting_field' );

/**
 * Render web app manifest display setting field.
 */
function render_web_app_manifest_display_setting_field() {
	// See: <https://developer.mozilla.org/en-US/docs/Web/Manifest/display#values>.
	$allowed_values = array( 'fullscreen', 'standalone', 'minimal-ui', 'browser' );

	?>
	<fieldset>
		<legend class="screen-reader-text"><span><?php esc_html_e( 'Web App manifest display', 'pwa' ); ?> </span></legend>

		<label for="web_app_manifest_display">
			<?php esc_html_e( 'Web App manifest display', 'pwa' ); ?>
		</label>

		<select name="web_app_manifest_display" id="web_app_manifest_display">
			<?php foreach ( $allowed_values as $value ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, get_option( 'web_app_manifest_display', 'minimal-ui' ) ); ?>><?php echo esc_html( $value ); ?></option>
			<?php endforeach; ?>
		</select>

		<p class="description">
			<?php
			echo wp_kses(
				__( 'This controls how the web app is displayed when launched from the home screen. See <a href="https://developer.mozilla.org/en-US/docs/Web/Manifest/display" rel="noreferrer noopener" target="_blank">MDN documentation</a> for more information.', 'pwa' ),
				array( 'a' => array_fill_keys( array( 'href', 'rel', 'target' ), true ) )
			);
			?>
		</p>
	</fieldset>
	<?php
}
