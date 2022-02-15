<?php
/**
 * Customizer API: WP_Customize_Manager class
 *
 * Add control for maskable icon setting.
 *
 * @package PWA
 * @subpackage Customize
 * @since 0.7
 */

/**
 * Register site_icon_maskable setting and control.
 *
 * Core merge note: This will go into the `WP_Customize_Manager::register_controls()` method
 *
 * @see WP_Customize_Manager::register_controls()
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
				'active_callback' => function() {
					return (bool) get_option( 'site_icon' );
				},
			)
		);
	}
}

add_action( 'customize_register', 'pwa_customize_register_site_icon_maskable', 1000 );

/**
 * Enqueue scripts for maskable icon customizer setting.
 *
 * @return void
 */
function site_icon_maskable_scripts() {
	wp_enqueue_script(
		'pwa_customizer_script',
		plugins_url( 'wp-admin/js/customizer.js', PWA_PLUGIN_FILE ),
		array( 'customize-controls' ),
		PWA_VERSION,
		true
	);
}

add_action( 'customize_controls_enqueue_scripts', 'site_icon_maskable_scripts' );
