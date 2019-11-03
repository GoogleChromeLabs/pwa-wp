<?php
/**
 * The template for displaying offline pages
 *
 * @package WordPress
 * @subpackage Twenty_Seventeen
 */

// Prevent showing nav menus.
add_filter( 'has_nav_menu', '__return_false' );
add_filter( 'pre_wp_nav_menu', '__return_empty_string' );

get_header(); ?>

<div class="wrap">
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

			<section class="error-offline">
				<header class="page-header">
					<h1 class="page-title"><?php esc_html_e( 'Offline', 'pwa' ); ?></h1>
				</header><!-- .page-header -->

				<div class="page-content">
					<?php
					if ( function_exists( 'wp_service_worker_error_message_placeholder' ) ) {
						wp_service_worker_error_message_placeholder();
					}
					?>
				</div><!-- .page-content -->
			</section><!-- .error-offline -->
		</main><!-- #main -->
	</div><!-- #primary -->
</div><!-- .wrap -->

<?php
get_footer();
