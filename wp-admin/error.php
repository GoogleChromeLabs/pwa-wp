<?php
/**
 * Error admin screen.
 *
 * IMPORTANT: This admin page can be served to authenticated and unauthenticated users alike, since it will be precached when the user accesses
 * the login screen. As such, it should only contain public information. It should be treated as a template which is populated dynamically
 * with the service worker according to the user's authorization level.
 *
 * @package PWA
 * @subpackage Administration
 */

header( 'X-Robots-Tag: noindex' );

switch ( isset( $_REQUEST['code'] ) ? sanitize_key( $_REQUEST['code'] ) : null ) { // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
	case 'offline':
		$title    = __( 'Offline', 'pwa' );
		$content  = sprintf( '<h1>%s</h1>', esc_html__( 'You seem to be offline.', 'pwa' ) );
		$content .= sprintf( '<p>%s</p>', esc_html__( 'Please check your internet connection. In the future, this error screen could provide you with actions you can perform while offline, like edit recent drafts in Gutenberg.', 'pwa' ) );
		break;
	case '500':
		$title    = __( 'Internal Server Error', 'pwa' );
		$content  = sprintf( '<h1>%s</h1>', esc_html__( 'A server error occurred.', 'pwa' ) );
		$content .= sprintf(
			'<p>%s</p>',
			esc_html__( 'Something went wrong which prevented WordPress from serving a response. Please check your error logs.', 'pwa' )
		);
		ob_start();
		?>
		<details id="error-details" hidden>
			<summary><?php esc_html_e( 'More details', 'pwa' ); ?></summary>
			<iframe id="error-details__iframe" style="width:100%;" srcdoc=""></iframe>
			<script>
			function renderErrorDetails( data ) {
				if ( data.bodyText.trim().length ) {
					const details = document.getElementById( 'error-details' );
					details.querySelector( 'iframe' ).srcdoc = data.bodyText;
					details.hidden = false;
				}
			}
			</script>
			<?php wp_print_service_worker_error_details_script( 'renderErrorDetails' ); ?>
		</details>
		<?php
		$content .= ob_get_clean();
		break;
	default:
		$title   = __( 'Unrecognized Error', 'pwa' );
		$content = sprintf( '<p>%s</p>', esc_html__( 'An unrecognized error occurred.', 'pwa' ) );
}

$admin_title = get_bloginfo( 'name' );

/* translators: Admin screen title. 1: Admin screen name, 2: Network or site name */
$admin_title = sprintf( __( '%1$s &lsaquo; %2$s &#8212; WordPress', 'default' ), $title, $admin_title );

/** This filter is documented in wp-admin/admin-header.php */
$admin_title = apply_filters( 'admin_title', $admin_title, $title );

wp_die( $content, $admin_title, array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
