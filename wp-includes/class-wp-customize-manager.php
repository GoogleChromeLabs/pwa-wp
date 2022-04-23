<?php
/**
 * Add hooks to amend behavior of the WP_Customize_Manager class.
 *
 * @package PWA
 * @subpackage Customize
 * @since 0.7
 */

/**
 * Register site_icon_maskable setting and control.
 *
 * Core merge note: This will go into the `WP_Customize_Manager::register_controls()` method or else the control logic
 * itself may be made part of WP_Customize_Site_Icon_Control.
 *
 * @see WP_Customize_Manager::register_controls()
 * @see WP_Customize_Site_Icon_Control
 * @since 0.7
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager object.
 */
function pwa_customize_register_site_icon_maskable( WP_Customize_Manager $wp_customize ) {
	$wp_customize->add_setting(
		'site_icon_maskable',
		array(
			'capability' => 'manage_options',
			'type'       => 'option',
			'default'    => false,
			'transport'  => 'postMessage',
		)
	);

	$site_icon_control = $wp_customize->get_control( 'site_icon' );
	if ( $site_icon_control ) {
		$wp_customize->add_control(
			'site_icon_maskable',
			array(
				'type'            => 'checkbox',
				'section'         => 'title_tagline',
				'label'           => __( 'Maskable icon', 'pwa' ),
				'priority'        => $site_icon_control->priority + 1,
				'active_callback' => function() use ( $wp_customize ) {
					return (bool) $wp_customize->get_setting( 'site_icon' )->value();
				},
			)
		);
	}
}

add_action( 'customize_register', 'pwa_customize_register_site_icon_maskable', 1000 );

/**
 * Enqueue script for Site Icon control.
 *
 * This may end up making sense being enqueued as part of WP_Customize_Site_Icon_Control or just added to logic in
 * <src/js/_enqueues/wp/customize/controls.js>.
 *
 * @see WP_Customize_Site_Icon_Control::enqueue()
 */
function pwa_customize_controls_enqueue_site_icon_script() {
	wp_enqueue_script(
		'customize-controls-site-icon-pwa',
		plugins_url( 'wp-admin/js/customize-controls-site-icon-pwa.js', PWA_PLUGIN_FILE ),
		array( 'customize-controls' ),
		PWA_VERSION,
		true
	);

	$icon_validation_messages = array(
		'pwa_icon_not_set'    => __( 'Site icon should be selected.', 'pwa' ),
		'pwa_icon_too_small'  => __( 'Site icon should be at least 512 x 512 pixels.', 'pwa' ),
		'pwa_icon_not_png'    => __( 'Site icon should be in PNG format.', 'pwa' ),
		'pwa_icon_not_square' => __( 'Site icon must be square.', 'pwa' ),
	);

	wp_add_inline_script(
		'customize-controls-site-icon-pwa',
		sprintf( 'Object.assign( window._wpCustomizeControlsL10n, %s );', wp_json_encode( $icon_validation_messages ) ),
		'after'
	);
}
add_action( 'customize_controls_enqueue_scripts', 'pwa_customize_controls_enqueue_site_icon_script' );
