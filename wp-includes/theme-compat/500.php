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
		<iframe style="width:100%;" srcdoc=""></iframe>
		<script>
			{
				// Broadcast a request to obtain the original response text from the internal server error response and
				// display it inside a details iframe if the 500 response included any body (such as an error message).
				const clientUrl = location.href;
				const channel = new BroadcastChannel( 'wordpress-server-errors' );
				channel.onmessage = ( event ) => {
					if ( event.data && event.data.requestUrl && clientUrl === event.data.requestUrl ) {
						channel.onmessage = null;
						channel.close();

						// Populate the details with the information if available.
						if ( event.data.bodyText.trim().length > 0 ) {
							const details = document.getElementById( 'error-details' );
							const iframe = details.querySelector( 'iframe' );
							iframe.srcdoc = event.data.bodyText;
							details.hidden = false;
						}
					}
				};
				channel.postMessage( { clientUrl } )
			}
		</script>
	</details>
</main>
<?php

pwa_get_footer( 'error' );
