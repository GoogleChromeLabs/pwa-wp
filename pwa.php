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
 * Version:           0.1.0
 * Author:            XWP, Google, and contributors
 * Author URI:        https://github.com/xwp/pwa-wp/graphs/contributors
 * Text Domain:       pwa
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/xwp/pwa-wp
 * Requires PHP:      5.2
 * Requires WP:       4.9
 */

define( 'PWA_VERSION', '0.1.0' );
define( 'PWA_PLUGIN_FILE', __FILE__ );
define( 'PWA_PLUGIN_DIR', dirname( __FILE__ ) );
define( 'PWA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/** WP_Web_App_Manifest Class */
require_once PWA_PLUGIN_DIR . '/wp-includes/class-wp-web-app-manifest.php';

/** WP_HTTPS_Detection Class */
require_once PWA_PLUGIN_DIR . '/wp-includes/class-wp-https-detection.php';

/** WP_Service_Workers Class */
require_once PWA_PLUGIN_DIR . '/wp-includes/class-wp-service-workers.php';

/** WordPress Service Worker Functions */
require_once PWA_PLUGIN_DIR . '/wp-includes/service-workers.php';

/** WordPress Offline Functions */
require_once PWA_PLUGIN_DIR . '/wp-includes/offline.php';

/** Amend default filters */
require_once PWA_PLUGIN_DIR . '/wp-includes/default-filters.php';

/** Functions to add to query.php file. */
require_once PWA_PLUGIN_DIR . '/wp-includes/query.php';

require_once PWA_PLUGIN_DIR . '/wp-includes/template.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/general-template.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/post-template.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/template-loader.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/class-wp.php';
require_once PWA_PLUGIN_DIR . '/wp-includes/class-wp-query.php';

$wp_web_app_manifest = new WP_Web_App_Manifest();
$wp_web_app_manifest->init();
$wp_https_detection = new WP_HTTPS_Detection();
$wp_https_detection->init();
