<?php
/**
 * PWA
 *
 * @package      PWA
 * @author       XWP
 * @license      GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name:       PWA
 * Plugin URI:        https://github.com/xwp/pwa-wp
 * Description:       Feature plugin to bring Progressive Web App (PWA) capabilities to Core
 * Version:           0.2-alpha1
 * Author:            XWP, Google, and contributors
 * Author URI:        https://github.com/xwp/pwa-wp/graphs/contributors
 * Text Domain:       pwa
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/xwp/pwa-wp
 * Requires PHP:      5.2
 * Requires WP:       4.9
 */

define( 'PWA_VERSION', '0.2-alpha1' );
define( 'PWA_PLUGIN_FILE', __FILE__ );
define( 'PWA_PLUGIN_DIR', dirname( __FILE__ ) );
define( 'PWA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

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

/** WP_Web_App_Manifest Class */
require_once PWA_PLUGIN_DIR . '/wp-includes/class-wp-web-app-manifest.php';

/** WP_HTTPS_Detection Class */
require_once PWA_PLUGIN_DIR . '/wp-includes/class-wp-https-detection.php';

/** WP_HTTPS_UI Class */
require_once PWA_PLUGIN_DIR . '/wp-includes/class-wp-https-ui.php';

/** WP_Service_Worker_Registry Interface */
require_once PWA_PLUGIN_DIR . '/wp-includes/interface-wp-service-worker-registry.php';

/** WP_Service_Worker_Registry_Aware Interface */
require_once PWA_PLUGIN_DIR . '/wp-includes/interface-wp-service-worker-registry-aware.php';

/** WP_Service_Workers Class */
require_once PWA_PLUGIN_DIR . '/wp-includes/class-wp-service-workers.php';

/** WP_Service_Worker_Scripts Class */
require_once PWA_PLUGIN_DIR . '/wp-includes/class-wp-service-worker-scripts.php';

/** WP_Service_Worker_Component Interface */
require_once PWA_PLUGIN_DIR . '/wp-includes/components/interface-wp-service-worker-component.php';

/** WP_Service_Worker_Component Implementation Classes */
require_once PWA_PLUGIN_DIR . '/wp-includes/components/class-wp-service-worker-configuration-component.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/components/class-wp-service-worker-navigation-routing-component.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/components/class-wp-service-worker-precaching-routes-component.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/components/class-wp-service-worker-precaching-routes.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/components/class-wp-service-worker-caching-routes-component.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/components/class-wp-service-worker-caching-routes.php';

/** WordPress Service Worker Functions */
require_once PWA_PLUGIN_DIR . '/wp-includes/service-workers.php';

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

/** Hooks to add for when accessing admin. */
require_once PWA_PLUGIN_DIR . '/wp-admin/admin.php';

/**
 * Filters whether service worker integrations should be enabled.
 *
 * As these are experimental, they are kept separate from the service worker core code and hidden behind a feature flag.
 *
 * Instead of using this filter, you can also use a constant `WP_SERVICE_WORKER_INTEGRATIONS_ENABLED`.
 *
 * @since 0.2
 *
 * @param bool $enabled Whether or not service worker integrations are enabled.
 */
if ( apply_filters( 'wp_service_worker_integrations_enabled', defined( 'WP_SERVICE_WORKER_INTEGRATIONS_ENABLED' ) && WP_SERVICE_WORKER_INTEGRATIONS_ENABLED ) ) {
	/** WP_Service_Worker_Integration Interface */
	require_once PWA_PLUGIN_DIR . '/wp-includes/integrations/interface-wp-service-worker-integration.php';

	/** WP_Service_Worker_Base_Integration Class */
	require_once PWA_PLUGIN_DIR . '/wp-includes/integrations/class-wp-service-worker-base-integration.php';

	/** WP_Service_Worker_Integration Implementation Classes */
	require_once PWA_PLUGIN_DIR . '/wp-includes/integrations/class-wp-service-worker-site-icon-integration.php';
	require_once PWA_PLUGIN_DIR . '/wp-includes/integrations/class-wp-service-worker-custom-logo-integration.php';
	require_once PWA_PLUGIN_DIR . '/wp-includes/integrations/class-wp-service-worker-custom-header-integration.php';
	require_once PWA_PLUGIN_DIR . '/wp-includes/integrations/class-wp-service-worker-custom-background-integration.php';
	require_once PWA_PLUGIN_DIR . '/wp-includes/integrations/class-wp-service-worker-scripts-integration.php';
	require_once PWA_PLUGIN_DIR . '/wp-includes/integrations/class-wp-service-worker-styles-integration.php';
	require_once PWA_PLUGIN_DIR . '/wp-includes/integrations/class-wp-service-worker-fonts-integration.php';
	require_once PWA_PLUGIN_DIR . '/wp-includes/integrations/class-wp-service-worker-admin-assets-integration.php';

	/** WordPress Service Worker Integration Functions */
	require_once PWA_PLUGIN_DIR . '/wp-includes/service-worker-integrations.php';
}

$wp_web_app_manifest = new WP_Web_App_Manifest();
$wp_web_app_manifest->init();

$wp_https_detection = new WP_HTTPS_Detection();
$wp_https_detection->init();
