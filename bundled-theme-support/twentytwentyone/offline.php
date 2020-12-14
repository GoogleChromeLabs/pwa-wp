<?php
/**
 * The template for displaying offline pages
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_One
 */

// Prevent showing nav menus.
add_filter( 'pre_wp_nav_menu', '__return_empty_string' );
add_filter( 'has_nav_menu', '__return_false' );

// Prevent showing widgets.
add_filter( 'sidebars_widgets', '__return_empty_array' );

get_header();
?>

	<header class="page-header alignwide">
		<h1 class="page-title"><?php esc_html_e( 'Offline', 'pwa' ); ?></h1>
	</header><!-- .page-header -->

	<div class="default-max-width">
		<div class="page-content">
			<?php
			if ( function_exists( 'wp_service_worker_error_message_placeholder' ) ) {
				wp_service_worker_error_message_placeholder();
			}
			?>
		</div><!-- .page-content -->
	</div><!-- .error-404 -->

<?php
get_footer();
