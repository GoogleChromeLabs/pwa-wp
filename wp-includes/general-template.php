<?php
/**
 * General template tags that can go anywhere in a template.
 *
 * Code in this file would be added/amended to wp-includes/general-template.php in core.
 *
 * @package PWA
 * @subpackage Template
 * @since 0.2.0
 */

/**
 * Load header template.
 *
 * Includes the header template for a theme or if a name is specified then a
 * specialised header will be included.
 *
 * For the parameter, if the file is called "header-special.php" then specify
 * "special".
 *
 * @since 0.2
 * @see get_header() This is a clone of the core function but replaces `locate_template()` with `pwa_locate_template()`.
 * @link https://core.trac.wordpress.org/ticket/13239 The reason why this patched function is needed.
 *
 * @param string $name The name of the specialised header.
 */
function pwa_get_header( $name = null ) {
	/**
	 * Fires before the header template file is loaded.
	 *
	 * @since 2.1.0
	 * @since 2.8.0 $name parameter added.
	 *
	 * @param string|null $name Name of the specific header file to use. null for the default header.
	 */
	do_action( 'get_header', $name );

	$templates = array();
	$name      = (string) $name;
	if ( '' !== $name ) {
		$templates[] = "header-{$name}.php";
	}

	$templates[] = 'header.php';

	// Begin core patch.
	pwa_locate_template( $templates, true );
	// End core patch.
}

/**
 * Load footer template.
 *
 * Includes the footer template for a theme or if a name is specified then a
 * specialised footer will be included.
 *
 * For the parameter, if the file is called "footer-special.php" then specify
 * "special".
 *
 * @since 0.2
 * @see get_footer() This is a clone of the core function but replaces `locate_template()` with `pwa_locate_template()`.
 * @link https://core.trac.wordpress.org/ticket/13239 The reason why this patched function is needed.
 *
 * @param string $name The name of the specialised footer.
 */
function pwa_get_footer( $name = null ) {
	/**
	 * Fires before the footer template file is loaded.
	 *
	 * @since 2.1.0
	 * @since 2.8.0 $name parameter added.
	 *
	 * @param string|null $name Name of the specific footer file to use. null for the default footer.
	 */
	do_action( 'get_footer', $name );

	$templates = array();
	$name      = (string) $name;
	if ( '' !== $name ) {
		$templates[] = "footer-{$name}.php";
	}

	$templates[] = 'footer.php';

	// Begin core patch.
	pwa_locate_template( $templates, true );
	// End core patch.
}

/**
 * Add no-robots meta tag to error template.
 *
 * @todo Is this right? Should we add_action when we find out that the filter is present?
 * @see wp_no_robots()
 * @since 0.2
 */
function wp_add_error_template_no_robots() {
	if ( is_offline() || is_500() ) {
		wp_no_robots();
	}
}

/**
 * Ensure current user is unset when serving an error page (either offline or server error).
 *
 * This is important so that what the service worker initially precaches for the user when first accessing the site
 * will persist even after they have authenticated.
 *
 * @since 0.5
 */
function wp_unauthenticate_error_template_requests() {
	if ( is_offline() || is_500() ) {
		wp_set_current_user( 0 );
	}
}

/**
 * Filter the document title for the offline/500 error template.
 *
 * In core merge, this would amend `wp_get_document_title()`.
 *
 * @see wp_get_document_title()
 * @since 2.0
 *
 * @param array $parts {
 *     The document title parts.
 *
 *     @type string $title   Title of the viewed page.
 *     @type string $page    Optional. Page number if paginated.
 *     @type string $tagline Optional. Site description when on home page.
 *     @type string $site    Optional. Site title when not on home page.
 * }
 * @return array $title Filtered title.
 */
function pwa_filter_document_title_parts( $parts ) {
	if ( ! empty( $parts['title'] ) ) {
		return $parts;
	}
	if ( is_offline() ) {
		$parts['title'] = __( 'Offline', 'pwa' );
	} elseif ( is_500() ) {
		$parts['title'] = __( 'Internal Server Error', 'pwa' );
	}
	return $parts;
}
add_filter( 'document_title_parts', 'pwa_filter_document_title_parts' );
