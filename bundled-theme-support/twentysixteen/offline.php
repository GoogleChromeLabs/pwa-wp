<?php
/**
 * The template for displaying offline pages
 *
 * @package WordPress
 * @subpackage Twenty_Sixteen
 */

// Prevent showing nav menus.
add_filter( 'has_nav_menu', '__return_false' );
add_filter( 'pre_wp_nav_menu', '__return_empty_string' );

// Add the body class for the 404 template for the sake of styling.
add_filter(
	'body_class',
	function( $body_classes ) {
		$body_classes[] = 'error404';
		return $body_classes;
	}
);

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

			<section class="error-404 not-found">
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
			</section><!-- .error-404 -->

		</main><!-- .site-main -->

	</div><!-- .content-area -->

<?php get_footer(); ?>
