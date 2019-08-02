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

switch ( isset( $_REQUEST['code'] ) ? sanitize_key( $_REQUEST['code'] ) : null ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.NoNonceVerification
	case 'offline':
		$title_prefix = __( 'Offline', 'pwa' );
		$content      = sprintf( '<h1>%s</h1>', esc_html__( 'Offline', 'pwa' ) );
		$content     .= '<p><!--WP_SERVICE_WORKER_ERROR_MESSAGE--></p>';
		$content     .= sprintf( '<p>%s</p>', esc_html__( 'In the future, this error screen could provide you with actions you can perform while offline, like edit recent drafts in Gutenberg.', 'pwa' ) );
		break;
	case '500':
		$title_prefix = __( 'Internal Server Error', 'pwa' );
		$content      = sprintf( '<h1>%s</h1>', esc_html__( 'A server error occurred.', 'pwa' ) );
		$content     .= '<p><!--WP_SERVICE_WORKER_ERROR_MESSAGE--></p>';
		$content     .= sprintf(
			'<p>%s</p>',
			esc_html__( 'Something went wrong which prevented WordPress from serving a response. Please check your error logs.', 'pwa' )
		);
		ob_start();
		wp_service_worker_error_details_template();
		$content .= ob_get_clean();
		break;
	default:
		$title_prefix = __( 'Unrecognized Error', 'pwa' );
		$content      = sprintf( '<p>%s</p>', esc_html__( 'An unrecognized error occurred.', 'pwa' ) );
}

$admin_title = get_bloginfo( 'name' );

/* translators: Admin screen title. 1: Admin screen name, 2: Network or site name */
$admin_title = sprintf( __( '%1$s &lsaquo; %2$s &#8212; WordPress', 'default' ), $title_prefix, $admin_title );

// Ensure globals are set for admin_title filter to apply.
global $current_screen, $hook_suffix;
$hook_suffix = ''; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
if ( empty( $current_screen ) ) {
	set_current_screen();
}

/** This filter is documented in wp-admin/admin-header.php */
$admin_title = apply_filters( 'admin_title', $admin_title, $title_prefix );

wp_die( $content, $admin_title, array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
