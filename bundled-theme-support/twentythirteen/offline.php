<?php
/**
 * The template for displaying offline pages
 *
 * @package WordPress
 * @subpackage Twenty_Thirteen
 */

// Prevent showing nav menus.
add_filter( 'pre_wp_nav_menu', '__return_empty_string' );
add_filter( 'has_nav_menu', '__return_false' );

// Prevent showing search form.
add_filter( 'get_search_form', '__return_empty_string' );

// Prevent showing widgets.
add_filter( 'sidebars_widgets', '__return_empty_array' );

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_add_inline_style( 'twentythirteen-style', '#site-navigation { display: none; } .page-content { padding: 20px; }' );
	},
	20
);

get_header(); ?>

<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">

		<div class="page-wrapper">
			<div class="page-content">
				<h1 class="offline-page-title"><?php esc_html_e( 'Offline', 'pwa' ); ?></h1>

				<?php
				if ( function_exists( 'wp_service_worker_error_message_placeholder' ) ) {
					wp_service_worker_error_message_placeholder();
				}
				?>
			</div><!-- .page-content -->
		</div><!-- .page-wrapper -->

	</main><!-- .site-main -->
</div><!-- .content-area -->

<?php get_footer(); ?>
