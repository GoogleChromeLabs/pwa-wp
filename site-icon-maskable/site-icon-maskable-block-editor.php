<?php
/**
 * Handles the 'site_icon_maskable' setting within a full-site-editing context
 *
 * @package PWA
 */

namespace PWA_WP;

use PWA_PLUGIN_DIR;

use Error;

use function add_action;
use function plugins_url;
use function register_setting;
use function wp_enqueue_script;
use function wp_set_script_translations;

/**
 * [pwa__enqueue_block_editor_assets description]
 *
 * @package PWA
 * @since   0.8.0-alpha
 * @throws Error Fatals out when the block-filter files weren't built.
 *
 * @see  https://developer.wordpress.org/reference/hooks/enqueue_block_editor_assets/
 */
function enqueue_block_editor_assets__site_icon_maskable() {
	$dir  = '/wp-includes/js/dist';
	$path = PWA_PLUGIN_DIR . $dir;

	$script_asset_path = "$path/site-icon-maskable.asset.php";
	if ( ! file_exists( $script_asset_path ) ) {
		return;
	}
	$index_js     = "$dir/site-icon-maskable.js";
	$script_asset = require $script_asset_path;

	wp_enqueue_script(
		'pwa-site-icon-maskable-block-editor',
		plugins_url( $index_js, __FILE__ ),
		$script_asset['dependencies'],
		$script_asset['version'],
		true
	);
	wp_set_script_translations( 'pwa-site-icon-maskable-block-editor', 'pwa' );

}
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_block_editor_assets__site_icon_maskable' );

