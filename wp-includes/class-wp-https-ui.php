<?php
/**
 * WP_HTTPS_UI class.
 *
 * @package PWA
 */

/**
 * WP_HTTPS_UI class.
 */
class WP_HTTPS_UI {

	/**
	 * The form action for this UI.
	 *
	 * @var string
	 */
	const FORM_ACTION = 'update-core.php?action=do-enable-https';

	/**
	 * The nonce action.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'wp_https_enable';

	/**
	 * The name of the value to enable HTTPS.
	 *
	 * @var string
	 */
	const ENABLE_HTTPS_NAME = 'wp_enable_https';

	/**
	 * Inits the class.
	 */
	public function init() {
		add_action( 'core_upgrade_preamble', array( $this, 'render_ui' ) );
	}

	/**
	 * Renders the HTTPS UI in /wp-admin on the Dashboard > WordPress Updates page.
	 */
	public function render_ui() {
		?>
		<h2><?php esc_html_e( 'HTTPS', 'pwa' ); ?></h2>
		<form method="post" action="<?php echo esc_url( self::FORM_ACTION ); ?>" name="enable-https" class="upgrade">
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<table class="form-table">
				<tbody>
					<tr class="option-site-visibility">
						<th scope="row"><?php esc_html_e( 'Enable HTTPS', 'pwa' ); ?></th>
						<td>
							<fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'Options To Enable HTTPS', 'pwa' ); ?></span></legend>
								<label><input name="<?php echo esc_attr( self::ENABLE_HTTPS_NAME ); ?>" type="radio" value="0" checked="checked"><?php esc_html_e( 'Yes', 'pwa' ); ?></label>
								<label><input name="<?php echo esc_attr( self::ENABLE_HTTPS_NAME ); ?>" type="radio" value="0" checked="checked"><?php esc_html_e( 'No', 'pwa' ); ?></label>
								<p class="description"><?php esc_html_e( 'Your site appears to support HTTPS', 'pwa' ); ?></p>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>
		</form>
		<?php
	}

}
