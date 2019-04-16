<?php
/**
 * Service Worker functions related to integrations.
 *
 * These are experimental and therefore kept separate from the service worker core code.
 *
 * @since 0.2
 *
 * @package PWA
 */

/**
 * Registers service worker integrations.
 *
 * These integrations are separate from the service worker core and need to be opted in via theme support.
 * To enable all integrations, use the following:
 *
 *     add_theme_support( 'service_worker', true );
 *
 * Alternatively, you can also pass an array of integration slugs as keys and a boolean indicating that
 * integration's status as values, for example:
 *
 *     add_theme_support(
 *         'service_worker',
 *         array(
 *             'wp-custom-logo'   => true,
 *             'wp-custom-header' => true,
 *         )
 *     );
 *
 * @since 0.2
 *
 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
 */
function pwa_register_service_worker_integrations( WP_Service_Worker_Scripts $scripts ) {
	// Bail if not supported by theme.
	$theme_support = get_theme_support( 'service_worker' );
	if ( ! $theme_support ) {
		return;
	}

	// Set script default parameters.
	$scripts->base_url        = site_url();
	$scripts->content_url     = defined( 'WP_CONTENT_URL' ) ? WP_CONTENT_URL : '';
	$scripts->default_version = get_bloginfo( 'version' );

	// Load integration base.
	require_once PWA_PLUGIN_DIR . '/integrations/interface-wp-service-worker-integration.php';
	require_once PWA_PLUGIN_DIR . '/integrations/class-wp-service-worker-base-integration.php';

	$integrations = array(
		'wp-site-icon'         => 'WP_Service_Worker_Site_Icon_Integration',
		'wp-custom-logo'       => 'WP_Service_Worker_Custom_Logo_Integration',
		'wp-custom-header'     => 'WP_Service_Worker_Custom_Header_Integration',
		'wp-custom-background' => 'WP_Service_Worker_Custom_Background_Integration',
		'wp-scripts'           => 'WP_Service_Worker_Scripts_Integration',
		'wp-styles'            => 'WP_Service_Worker_Styles_Integration',
		'wp-fonts'             => 'WP_Service_Worker_Fonts_Integration',
	);

	if ( ! SCRIPT_DEBUG ) {
		$integrations['wp-admin-assets'] = 'WP_Service_Worker_Admin_Assets_Integration';
	}

	// Filter active integrations if granular theme support array is provided.
	if ( is_array( $theme_support ) && isset( $theme_support[0] ) && is_array( $theme_support[0] ) ) {
		$integrations = array_intersect_key(
			$integrations,
			array_filter( $theme_support[0] )
		);
	}

	// Load, instantiate and register each integration supported by the theme.
	foreach ( $integrations as $slug => $integration_class ) {
		require_once PWA_PLUGIN_DIR . '/integrations/class-' . str_replace( '_', '-', strtolower( $integration_class ) ) . '.php';

		$integration = new $integration_class();
		if ( ! $integration instanceof WP_Service_Worker_Integration ) {
			_doing_it_wrong(
				__FUNCTION__,
				sprintf(
					/* translators: 1: integration slug, 2: interface name */
					esc_html__( 'The integration with slug %1$s does not implement the %2$s interface.', 'pwa' ),
					esc_html( $slug ),
					'WP_Service_Worker_Integration'
				),
				'0.2'
			);
			continue;
		}

		$scope    = $integration->get_scope();
		$priority = $integration->get_priority();
		switch ( $scope ) {
			case WP_Service_Workers::SCOPE_FRONT:
				add_action( 'wp_front_service_worker', array( $integration, 'register' ), $priority, 1 );
				break;
			case WP_Service_Workers::SCOPE_ADMIN:
				add_action( 'wp_admin_service_worker', array( $integration, 'register' ), $priority, 1 );
				break;
			case WP_Service_Workers::SCOPE_ALL:
				add_action( 'wp_front_service_worker', array( $integration, 'register' ), $priority, 1 );
				add_action( 'wp_admin_service_worker', array( $integration, 'register' ), $priority, 1 );
				break;
			default:
				$valid_scopes = array( WP_Service_Workers::SCOPE_FRONT, WP_Service_Workers::SCOPE_ADMIN, WP_Service_Workers::SCOPE_ALL );
				_doing_it_wrong(
					__FUNCTION__,
					sprintf(
						/* translators: 1: integration slug, 2: a comma-separated list of valid scopes */
						esc_html__( 'Scope for integration %1$s must be one out of %2$s.', 'pwa' ),
						esc_html( $slug ),
						esc_html( implode( ', ', $valid_scopes ) )
					),
					'0.1'
				);
		}
	}
}
