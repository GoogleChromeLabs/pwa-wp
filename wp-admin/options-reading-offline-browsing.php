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
			<?php esc_html_e( 'Cache visited pages in the browser so visitors can re-access them when offline.', 'pwa' ); ?>
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

/**
 * Print admin pointer.
 */
function print_admin_pointer() {
	// Skip showing admin pointer if not relevant.
	if (
		get_option( 'offline_browsing' )
		||
		'options-reading' === get_current_screen()->id
		||
		! current_user_can( 'manage_options' )
	) {
		return;
	}

	$pointer = 'pwa_offline_browsing';

	// Skip showing admin pointer if dismissed.
	$dismissed_pointers = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
	if ( in_array( $pointer, $dismissed_pointers, true ) ) {
		return;
	}

	wp_print_scripts( array( 'wp-pointer' ) );
	wp_print_styles( array( 'wp-pointer' ) );

	$content  = '<h3>' . esc_html__( 'PWA', 'pwa' ) . '</h3>';
	$content .= '<p>' . esc_html__( 'Offline browsing is now available in Reading settings.', 'pwa' ) . '</p>';

	$args = array(
		'content'  => $content,
		'position' => array(
			'align' => 'middle',
			'edge'  => is_rtl() ? 'right' : 'left',
		),
	);

	?>
	<script type="text/javascript">
		jQuery( function( $ ) {
			const menuSettingsItem = $( '#menu-settings' );
			const readingSettingsItem = menuSettingsItem.find( 'li:has( a[href="options-reading.php"] )' );
			if ( readingSettingsItem.length === 0 ) {
				return;
			}

			const options = $.extend( <?php echo wp_json_encode( $args ); ?>, {
				close: function() {
					$.post( ajaxurl, {
						pointer: <?php echo wp_json_encode( $pointer ); ?>,
						action: 'dismiss-wp-pointer'
					});
				}
			});

			let target;
			if ( menuSettingsItem.hasClass( 'wp-menu-open' ) ) {
				target = readingSettingsItem;
			} else {
				target = menuSettingsItem;
			}

			target.pointer( options ).pointer( 'open' );
		} );
	</script>
	<?php

}
add_action( 'admin_print_footer_scripts', __NAMESPACE__ . '\print_admin_pointer' );
