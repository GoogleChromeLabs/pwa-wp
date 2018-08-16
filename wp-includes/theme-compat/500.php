<?php
/**
 * Contains the server error base template
 *
 * When the website goes down, this template is served as the response instead of the service worker.
 * This template can be overridden by including an offline.php in the theme.
 *
 * @package PWA
 * @since 0.2.0
 */

get_header( 'offline' );

?>
	<main>
	<h1><?php esc_html_e( 'Oops! It looks like we&#8217;re offline.', 'pwa' ); ?></h1>
	<p><?php esc_html_e( 'We seem to be experiencing technical difficulties. Please try again.', 'pwa' ); ?></p>
	<details id="error-details" hidden></details>
</main>
<?php

get_footer( 'offline' );
