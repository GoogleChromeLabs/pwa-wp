<?php
/**
 * The template for displaying offline pages
 *
 * @package WordPress
 * @subpackage Twenty_Fifteen
 */

// Prevent showing nav menus.
add_filter( 'has_nav_menu', '__return_false' );
add_filter( 'pre_wp_nav_menu', '__return_empty_string' );
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_add_inline_style( 'twentyfifteen-style', '.secondary-toggle { display: none; }' );
	},
	20
);

// Prevent showing widgets.
add_filter( 'sidebars_widgets', '__return_empty_array' );

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
