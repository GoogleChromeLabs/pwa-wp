<?php
/**
 * Contains the generic error base template
 *
 * When the user goes offline or the website goes down, the error template is served by the service worker.
 * This error template is the base in this template hierarchy. To specify a template specifically when
 * offline, you can either use the is_offline() conditional or define an offline.php in your theme. Otherwise,
 * when there is an internal server error, you can use the is_500() conditional or define a 500.php in your theme.
 *
 * @package PWA
 * @since 0.2.0
 */

pwa_get_header( 'error' );

?>
<main>
	<h1><?php esc_html_e( 'Oops! Something went wrong.', 'pwa' ); ?></h1>
	<p><?php esc_html_e( 'Something prevented the page from being rendered. Please try again.', 'pwa' ); ?></p>
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
</main>
<?php

pwa_get_footer( 'error' );
