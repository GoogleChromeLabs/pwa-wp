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
				'type'     => 'checkbox',
				'section'  => 'title_tagline',
				'label'    => __( 'Maskable icon', 'pwa' ),
				'priority' => $site_icon_control->priority + 1,
			)
		);
	}
}

add_action( 'customize_register', 'pwa_customize_register_site_icon_maskable', 1000 );

/**
 * Enqueue script for site_icon_maskable control.
 *
 * This may end up making sense being enqueued as part of WP_Customize_Site_Icon_Control or just added to logic in
 * <src/js/_enqueues/wp/customize/controls.js>.
 *
 * @see WP_Customize_Site_Icon_Control::enqueue()
 */
function pwa_customize_controls_enqueue_site_icon_maskable_script() {
	wp_enqueue_script(
		'customize-controls-site-icon-maskable',
		plugins_url( 'wp-admin/js/customize-controls-site-icon-maskable.js', PWA_PLUGIN_FILE ),
		array( 'customize-controls' ),
		PWA_VERSION,
		true
	);
}

add_action( 'customize_controls_enqueue_scripts', 'pwa_customize_controls_enqueue_site_icon_maskable_script' );
