<?php
/**
 * Template for displaying offline pages
 *
 * @package WordPress
 * @subpackage Twenty_Ten
 */

add_filter( 'pre_wp_nav_menu', '__return_empty_string' );

get_header(); ?>

<div id="container">
	<div id="content" role="main">

		<div id="post-0" class="post error404 not-found">
			<h1 class="entry-title"><?php esc_html_e( 'Offline', 'pwa' ); ?></h1>
			<div class="entry-content">
				<?php
				if ( function_exists( 'wp_service_worker_error_message_placeholder' ) ) {
					wp_service_worker_error_message_placeholder();
				}
				?>
			</div><!-- .entry-content -->
		</div><!-- #post-0 -->

	</div><!-- #content -->
</div><!-- #container -->

<?php get_footer(); ?>
