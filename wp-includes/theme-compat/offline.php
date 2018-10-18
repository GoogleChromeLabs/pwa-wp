<?php
/**
 * Contains the offline base template
 *
 * When the client's internet connection goes down, this template is served as the response
 * instead of the service worker. This template can be overridden by including an offline.php
 * in the theme.
 *
 * @package PWA
 * @since 0.2.0
 */

pwa_get_header( 'error' );

?>
<main>
	<h1><?php esc_html_e( 'Oops! It looks like you&#8217;re offline.', 'pwa' ); ?></h1>
	<p><?php esc_html_e( 'Please check your internet connection, and try again.', 'pwa' ); ?></p>
	<p><!-- WP_OFFLINE_COMMENT --></p>
</main>
<?php

pwa_get_footer( 'error' );
