<?php
/**
 * Loads the correct template based on the visitor's url
 *
 * This contains code that will amend wp-includes/template-loader.php in core.
 *
 * @package PWA
 * @since 0.2
 */

/**
 * Filter template_include to select the offline page.
 *
 * @since 0.2
 *
 * @param string $template Template.
 * @return string Template to include.
 */
function _pwa_filter_template_include( $template ) {
	$located_template = null;
	if ( is_offline() ) {
		$located_template = get_offline_template();
	} elseif ( is_500() ) {
		$located_template = get_500_template();
	}
	if ( $located_template ) {
		$template = $located_template;
	}
	return $template;
}
add_filter( 'template_include', '_pwa_filter_template_include' );
