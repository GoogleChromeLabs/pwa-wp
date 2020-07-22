<?php
/**
 * Template loading functions.
 *
 * These are patched versions of the corresponding functions in core. They are needed because locate_template()
 * in core does not allow for the template search path to be filtered.
 *
 * @link https://core.trac.wordpress.org/ticket/13239
 *
 * @package PWA
 * @subpackage Template
 * @since 0.2.0
 */

// phpcs:disable WordPress.WP.DiscouragedConstants

/**
 * Retrieve the name of the highest priority template file that exists.
 *
 * Searches in the STYLESHEETPATH before TEMPLATEPATH and wp-includes/theme-compat
 * so that themes which inherit from a parent theme can just overload one file.
 *
 * @since 0.2.0
 * @see locate_template() This is a clone of the core function but adds the plugin's theme-compat directory to the template search path.
 *
 * @param string|array $template_names Template file(s) to search for, in order.
 * @param bool         $load           If true the template file will be loaded if it is found.
 * @param bool         $require_once   Whether to require_once or require. Default true. Has no effect if $load is false.
 * @return string The template filename if one is located.
 */
function pwa_locate_template( $template_names, $load = false, $require_once = true ) {
	$located = '';
	foreach ( (array) $template_names as $template_name ) {
		if ( ! $template_name ) {
			continue;
		}
		$theme_slug = get_template();
		if ( file_exists( STYLESHEETPATH . '/' . $template_name ) ) {
			$located = STYLESHEETPATH . '/' . $template_name;
			break;
		} elseif ( file_exists( TEMPLATEPATH . '/' . $template_name ) ) {
			$located = TEMPLATEPATH . '/' . $template_name;
			break;
		} elseif ( preg_match( '/^twenty\w+$/', $theme_slug ) && file_exists( PWA_PLUGIN_DIR . '/bundled-theme-support/' . $theme_slug . '/offline.php' ) ) {
			$located = PWA_PLUGIN_DIR . '/bundled-theme-support/' . $theme_slug . '/offline.php';
			break;
			// Begin core patch.
		} elseif ( file_exists( PWA_PLUGIN_DIR . '/' . WPINC . '/theme-compat/' . $template_name ) ) {
			$located = PWA_PLUGIN_DIR . '/' . WPINC . '/theme-compat/' . $template_name;
			break;
			// Begin core patch.
		} elseif ( file_exists( ABSPATH . WPINC . '/theme-compat/' . $template_name ) ) {
			$located = ABSPATH . WPINC . '/theme-compat/' . $template_name;
			break;
		}
		// End core patch.
	}

	if ( $load && $located ) {
		load_template( $located, $require_once );
	}

	return $located;
}

/**
 * Retrieve path to a template
 *
 * Used to quickly retrieve the path of a template without including the file
 * extension. It will also check the parent theme, if the file exists, with
 * the use of locate_template(). Allows for more generic template location
 * without the use of the other get_*_template() functions.
 *
 * @since 0.2.0
 * @see get_query_template() This is a clone of the core function but uses `pwa_locate_template()` instead of `locate_template()`.
 *
 * @param string $type      Filename without extension.
 * @param array  $templates An optional list of template candidates.
 * @return string Full path to template file.
 */
function pwa_get_query_template( $type, $templates = array() ) {
	$type = preg_replace( '|[^a-z0-9-]+|', '', $type );

	if ( empty( $templates ) ) {
		$templates = array( "{$type}.php" );
	}

	/** This filter is documented in wp-includes/template.php */
	$templates = apply_filters( "{$type}_template_hierarchy", $templates );

	$template = pwa_locate_template( $templates );

	/** This filter is documented in wp-includes/template.php */
	return apply_filters( "{$type}_template", $template, $type, $templates );
}

/**
 * Retrieve path of offline error template in current or parent template.
 *
 * The template hierarchy and template path are filterable via the {@see '$type_template_hierarchy'}
 * and {@see '$type_template'} dynamic hooks, where `$type` is 'archive'.
 *
 * @since 0.2
 * @see get_query_template()
 *
 * @return string Full path to archive template file.
 */
function get_offline_template() {
	$templates = array(
		'offline.php',
		'error.php',
	);

	return pwa_get_query_template( 'offline', $templates );
}

/**
 * Retrieve path of 500 server error template in current or parent template.
 *
 * The template hierarchy and template path are filterable via the {@see '$type_template_hierarchy'}
 * and {@see '$type_template'} dynamic hooks, where `$type` is 'archive'.
 *
 * @since 0.2
 * @see get_query_template()
 *
 * @return string Full path to archive template file.
 */
function get_500_template() {
	$templates = array(
		'500.php',
		'error.php',
	);

	return pwa_get_query_template( 'offline', $templates );
}

/**
 * Get service worker error messages.
 *
 * @return array Array of error messages: default, comment.
 */
function wp_service_worker_get_error_messages() {
	return apply_filters(
		'wp_service_worker_error_messages',
		array(
			'clientOffline' => __( 'It seems you are offline. Please check your internet connection and try again.', 'pwa' ),
			'serverOffline' => __( 'The server appears to be down, or your connection isn\'t working as expected. Please try again later.', 'pwa' ),
			'error'         => __( 'Something prevented the page from being rendered. Please try again.', 'pwa' ),
			'comment'       => __( 'Your comment will be submitted once you are back online!', 'pwa' ),
		)
	);
}

/**
 * Display service worker error details template.
 *
 * @param string $output Error details template output.
 */
function wp_service_worker_error_details_template( $output = '' ) {
	if ( empty( $output ) ) {
		$output = '<details id="error-details"><summary>' . esc_html__( 'More Details', 'pwa' ) . '</summary>{{{error_details_iframe}}}</details>';
	}
	echo '<!--WP_SERVICE_WORKER_ERROR_TEMPLATE_BEGIN-->';
	echo wp_kses_post( $output );
	echo '<!--WP_SERVICE_WORKER_ERROR_TEMPLATE_END-->';
}

/**
 * Display service worker error message template tag.
 */
function wp_service_worker_error_message_placeholder() {
	echo '<p><!--WP_SERVICE_WORKER_ERROR_MESSAGE--></p>';
}
