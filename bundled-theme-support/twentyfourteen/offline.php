<?php
/**
 * The template for displaying offline pages
 *
 * @package WordPress
 * @subpackage Twenty_Fourteen
 */

// Prevent showing nav menus.
add_filter( 'pre_wp_nav_menu', '__return_empty_string' );

// Prevent showing search form.
add_filter( 'get_search_form', '__return_empty_string' );
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_add_inline_style( 'twentyfourteen-style', '.search-toggle { display: none; }' );
	},
	20
);

get_header(); ?>

	<div id="primary" class="content-area">
		<div id="content" class="site-content" role="main">

			<header class="page-header">
				<h1 class="page-title"><?php esc_html_e( 'Offline', 'pwa' ); ?></h1>
			</header>

			<div class="page-content">
				<?php
				if ( function_exists( 'wp_service_worker_error_message_placeholder' ) ) {
					wp_service_worker_error_message_placeholder();
				}
				?>
			</div><!-- .page-content -->

		</div><!-- #content -->
	</div><!-- #primary -->

<?php
get_footer();
