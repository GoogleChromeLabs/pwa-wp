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
 * Register maskable icon setting.
 *
 * @since 0.7
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager object.
 *
 * @return void
 */
function pwa_customize_register_maskable_icon_setting( WP_Customize_Manager $wp_customize ) {
	$wp_customize->add_setting(
		'pwa_maskable_icon',
		array(
			'capability' => 'manage_options',
			'type'       => 'option',
			'default'    => false,
			'transport'  => 'postMessage',
		)
	);

	$wp_customize->add_control(
		'pwa_maskable_icon',
		array(
			'type'     => 'checkbox',
			'section'  => 'title_tagline',
			'label'    => __( 'Maskable icon', 'pwa' ),
			'priority' => 100,
		)
	);
}
add_action( 'customize_register', 'pwa_customize_register_maskable_icon_setting' );

/**
 * Enqueue scripts for maskable icon customizer setting.
 *
 * @return void
 */
function pwa_maskable_icon_scripts() {
	wp_register_script(
		'pwa_customizer_script',
		plugins_url( 'wp-includes/js/customizer.js', dirname( __FILE__ ) ),
		array(),
		PWA_VERSION,
		true
	);

	wp_localize_script(
		'pwa_customizer_script',
		'PWA_Customizer_Data',
		array(
			'siteIcon' => get_option( 'site_icon', 0 ),
		)
	);

	wp_enqueue_script( 'pwa_customizer_script' );
}

add_action( 'customize_controls_enqueue_scripts', 'pwa_maskable_icon_scripts' );
