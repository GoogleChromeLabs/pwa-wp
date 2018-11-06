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
	<?php wp_service_worker_error_message_placeholder(); ?>
	<?php wp_service_worker_error_details_template(); ?>
</main>
<?php

pwa_get_footer( 'error' );
