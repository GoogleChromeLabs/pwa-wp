<?php
/**
 * PWA
 *
 * @package      PWA
 * @license      GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: PWA
 * Plugin URI:  https://github.com/GoogleChromeLabs/pwa-wp
 * Description: Feature plugin to bring Progressive Web App (PWA) capabilities to Core
 * Version:     0.7.0-alpha
 * Author:      PWA Plugin Contributors
 * Author URI:  https://github.com/GoogleChromeLabs/pwa-wp/graphs/contributors
 * Text Domain: pwa
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

define( 'PWA_VERSION', '0.7.0-alpha' );
define( 'PWA_PLUGIN_FILE', __FILE__ );
define( 'PWA_PLUGIN_DIR', dirname( __FILE__ ) );
define( 'PWA_WORKBOX_VERSION', json_decode( file_get_contents( PWA_PLUGIN_DIR . '/package.json' ), true )['devDependencies']['workbox-cli'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,PHPCompatibility.Syntax.NewFunctionArrayDereferencing.Found -- Replaced with version literal build.
define( 'PWA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Print admin notice regarding having an old version of PHP.
 *
 * @since 0.2
 */
function _pwa_print_php_version_admin_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: required PHP version */
				esc_html__( 'The pwa plugin requires PHP %s. Please contact your host to update your PHP version.', 'pwa' ),
				'5.6+'
			);
			?>
		</p>
	</div>
	<?php
}
if ( version_compare( phpversion(), '5.6', '<' ) ) {
	add_action( 'admin_notices', '_pwa_print_php_version_admin_notice' );
	return;
}

/**
 * Print admin notice if plugin installed with incorrect slug (which impacts WordPress's auto-update system).
 *
 * @since 0.2
 */
function _pwa_incorrect_plugin_slug_admin_notice() {
	$actual_slug = basename( PWA_PLUGIN_DIR );
	?>
	<div class="notice notice-warning">
		<p>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %1$s is the current directory name, and %2$s is the required directory name */
					__( 'You appear to have installed the PWA plugin incorrectly. It is currently installed in the <code>%1$s</code> directory, but it needs to be placed in a directory named <code>%2$s</code>. Please rename the directory. This is important for WordPress plugin auto-updates.', 'pwa' ),
					$actual_slug,
					'pwa'
				)
			);
			?>
		</p>
	</div>
	<?php
}
if ( 'pwa' !== basename( PWA_PLUGIN_DIR ) ) {
	add_action( 'admin_notices', '_pwa_incorrect_plugin_slug_admin_notice' );
}

/**
 * Print admin notice when a build has not been been performed.
 *
 * @since 0.2
 */
function _pwa_print_build_needed_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: composer install && npm install && npm run build */
				__( 'You appear to be running the PWA plugin from source. Please do %s to finish installation.', 'pwa' ), // phpcs:ignore WordPress.Security.EscapeOutput
				'<code>composer install &amp;&amp; npm install &amp;&amp; npm run build</code>'
			);
			?>
		</p>
	</div>
	<?php
}
if ( ! file_exists( PWA_PLUGIN_DIR . '/wp-includes/js/workbox-v' . PWA_WORKBOX_VERSION ) || ! file_exists( PWA_PLUGIN_DIR . '/wp-includes/js/workbox-v' . PWA_WORKBOX_VERSION . '/workbox-sw.js' ) ) {
	add_action( 'admin_notices', '_pwa_print_build_needed_notice' );
	return;
}

/**
 * Register test for navigation preload being erroneously disabled.
 *
 * @since 0.3
 *
 * @param array $tests Tests.
 * @return array Tests.
 */
function _pwa_add_disabled_navigation_preload_site_status_test( $tests ) {
	$tests['direct']['navigation_preload_enabled'] = array(
		'label' => __( 'Navigation Preload Enabled', 'pwa' ),
		'test'  => '_pwa_check_disabled_navigation_preload',
	);
	return $tests;
}
add_filter( 'site_status_tests', '_pwa_add_disabled_navigation_preload_site_status_test' );

/**
 * Flag navigation preload incorrectly being disabled.
 *
 * This is temporary measure to correct a mistake in the example for how navigation request caching strategies.
 *
 * @todo Eventually add a test for enabling a navigation caching strategy.
 * @since 0.3
 *
 * @return array|null Test results.
 */
function _pwa_check_disabled_navigation_preload() {

	/** This filter is documented in wp-includes/components/class-wp-service-worker-navigation-routing-component.php */
	$navigation_route_precache_entry = apply_filters(
		'wp_service_worker_navigation_route',
		array(
			'url'      => null,
			'revision' => '',
		)
	);

	// Skip adding the navigation-preload test when using app shell since navigation preload is forcibly-disabled.
	if ( ! empty( $navigation_route_precache_entry['url'] ) ) {
		return null;
	}

	/** This filter is documented in wp-includes/components/class-wp-service-worker-navigation-routing-component.php */
	$navigation_preload_enabled = apply_filters( 'wp_service_worker_navigation_preload', true, WP_Service_Workers::SCOPE_FRONT );

	if ( $navigation_preload_enabled ) {
		$result = array(
			'label'       => __( 'Navigation preload is enabled in service worker', 'pwa' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance', 'pwa' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				esc_html__( 'Navigation preload speeds up performance for return visitors when the service worker has been suspended.', 'pwa' )
			),
		);
	} else {
		$result = array(
			'label'       => __( 'Navigation preload is being disabled in service worker', 'pwa' ),
			'status'      => 'recommended',
			'badge'       => array(
				'label' => __( 'Performance', 'pwa' ),
				'color' => 'orange',
			),
			'description' => sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: the wp_service_worker_navigation_preload filter call */
					esc_html__( 'A theme or a plugin appears to have disabled navigation preload in order to enable a navigation caching strategy. This was a workaround that is now no longer needed, and it is actually being ignored. Remove the following code from your theme/plugin to improve performance: %s.', 'pwa' ),
					'<code>add_filter( \'wp_service_worker_navigation_preload\', \'__return_false\' </code>'
				)
			),
			'actions'     => sprintf(
				'<a href="https://developers.google.com/web/tools/workbox/modules/workbox-navigation-preload#who_should_enable_navigation_preloads">%s</a>',
				esc_html__( 'Learn about enabling navigation preload.', 'pwa' )
			),
		);
	}

	$result['test'] = 'navigation_preload_enabled';

	return $result;
}

/** WP_Web_App_Manifest Class */
require_once PWA_PLUGIN_DIR . '/wp-includes/class-wp-web-app-manifest.php';

/** WP_Service_Workers Class */
require_once PWA_PLUGIN_DIR . '/wp-includes/class-wp-service-workers.php';

/** WP_Service_Worker_Scripts Class */
require_once PWA_PLUGIN_DIR . '/wp-includes/class-wp-service-worker-scripts.php';

/** WP_Service_Worker_Component Interface */
require_once PWA_PLUGIN_DIR . '/wp-includes/components/interface-wp-service-worker-component.php';

/** WP_Service_Worker_Component Implementation Classes */
require_once PWA_PLUGIN_DIR . '/wp-includes/components/class-wp-service-worker-configuration-component.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/components/class-wp-service-worker-navigation-routing-component.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/components/class-wp-service-worker-core-asset-caching-component.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/components/class-wp-service-worker-theme-asset-caching-component.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/components/class-wp-service-worker-plugin-asset-caching-component.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/components/class-wp-service-worker-uploaded-image-caching-component.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/components/class-wp-service-worker-precaching-routes-component.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/components/class-wp-service-worker-precaching-routes.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/components/class-wp-service-worker-caching-routes-component.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/components/class-wp-service-worker-caching-routes.php';

/** WordPress Service Worker Functions */
require_once PWA_PLUGIN_DIR . '/wp-includes/service-workers.php';

/** WordPress Service Worker Deprecated */
require_once PWA_PLUGIN_DIR . '/wp-includes/deprecated.php';

/** Amend default filters */
require_once PWA_PLUGIN_DIR . '/wp-includes/default-filters.php';

/** Functions to add to query.php file. */
require_once PWA_PLUGIN_DIR . '/wp-includes/query.php';

/** Functions to add to template.php */
require_once PWA_PLUGIN_DIR . '/wp-includes/template.php';

/** Functions to add to general-template.php */
require_once PWA_PLUGIN_DIR . '/wp-includes/general-template.php';

/** Function to add to post-template.php */
require_once PWA_PLUGIN_DIR . '/wp-includes/post-template.php';

/** Patch behavior in template-loader.php */
require_once PWA_PLUGIN_DIR . '/wp-includes/template-loader.php';

/** Patch behavior in class-wp.php */
require_once PWA_PLUGIN_DIR . '/wp-includes/class-wp.php';

/** Patch behavior in class-wp-query.php */
require_once PWA_PLUGIN_DIR . '/wp-includes/class-wp-query.php';

/** Function to register maskable icon setting in customizer */
require_once PWA_PLUGIN_DIR . '/wp-includes/class-wp-customize-manager.php';

/** Hooks to add for when accessing admin. */
require_once PWA_PLUGIN_DIR . '/wp-admin/admin.php';

/**
 * Plugin activation hook.
 */
function _pwa_activate_plugin() {
	pwa_add_rewrite_rules();
	flush_rewrite_rules( false ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules -- Not theme code.
}

register_activation_hook( PWA_PLUGIN_FILE, '_pwa_activate_plugin' );

/**
 * Plugin deactivation hook.
 *
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 */
function _pwa_deactivate_plugin() {
	global $wp_rewrite;
	unset( $wp_rewrite->extra_rules_top['^wp\.serviceworker$'] );
	flush_rewrite_rules( false ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules -- Not theme code.
}

register_deactivation_hook( PWA_PLUGIN_FILE, '_pwa_deactivate_plugin' );

/**
 * Load service worker integrations.
 *
 * @since 0.2.0
 *
 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
 */
function pwa_load_service_worker_integrations( WP_Service_Worker_Scripts $scripts ) {
	if ( ! current_theme_supports( 'service_worker' ) ) {
		return;
	}

	/** WordPress Service Worker Integration Functions */
	require_once PWA_PLUGIN_DIR . '/integrations/functions.php';

	pwa_register_service_worker_integrations( $scripts );
}
add_action( 'wp_default_service_workers', 'pwa_load_service_worker_integrations', -1 );

/**
 * Remove `novalidate` attribute from the comment form tag.
 *
 * This is a fix for the `novalidate` attribute being added to the comment form by WordPress
 * which disables HTML client-side validation for the form.
 */
function pwa_remove_novalidate_attribute_from_comments_form() {
	?>
	<script type="module">
		const commentForm = document.getElementById( 'commentform' );
		if ( commentForm ) {
			commentForm.removeAttribute( 'novalidate' );
		}
	</script>
	<?php
}
add_action( 'comment_form_after', 'pwa_remove_novalidate_attribute_from_comments_form', 10, 0 );

$wp_web_app_manifest = new WP_Web_App_Manifest();
$wp_web_app_manifest->init();

require_once PWA_PLUGIN_DIR . '/wp-admin/options-reading-offline-browsing.php';
