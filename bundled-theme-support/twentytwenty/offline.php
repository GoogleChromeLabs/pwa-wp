<?php
/**
 * The template for displaying offline pages
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 */

// Prevent showing nav menus.
add_filter( 'pre_wp_nav_menu', '__return_empty_string' );
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_add_inline_style( 'twentytwenty-style', '.header-toggles, .header-inner .mobile-search-toggle, .header-inner .nav-toggle, .to-the-top { display: none; }' );
	},
	20
);

// Prevent showing search form.
add_filter( 'get_search_form', '__return_empty_string' );

// Add the body class for the 404 template for the sake of styling.
add_filter(
	'body_class',
	function( $body_classes ) {
		$body_classes[] = 'error404';
		return $body_classes;
	}
);

get_header();
?>

<main id="site-content" role="main">

	<div class="section-inner thin error404-content">

		<h1 class="entry-title"><?php esc_html_e( 'Offline', 'pwa' ); ?></h1>

		<div class="intro-text">
			<?php
			if ( function_exists( 'wp_service_worker_error_message_placeholder' ) ) {
				wp_service_worker_error_message_placeholder();
			}
			?>
		</div>

	</div><!-- .section-inner -->

</main><!-- #site-content -->

<?php
get_footer();
