<?php
/**
 * The template for displaying offline pages
 *
 * @package WordPress
 * @subpackage Twenty_Nineteen
 */

// Prevent showing nav menus.
add_filter( 'pre_wp_nav_menu', '__return_empty_string' );

// Add the body class for the 404 template for the sake of styling.
add_filter(
	'body_class',
	function( $body_classes ) {
		$body_classes[] = 'error404';
		return $body_classes;
	}
);

// Prevent showing widgets.
add_filter( 'sidebars_widgets', '__return_empty_array' );

get_header();
?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main">

			<div class="error-404 not-found">
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
			</div><!-- .error-404 -->

		</main><!-- #main -->
	</div><!-- #primary -->

<?php
get_footer();
