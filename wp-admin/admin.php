<?php
/**
 * Hooks to integrate with admin.
 *
 * @package PWA
 */

/**
 * Serve the error.php admin page template when requested.
 *
 * @since 0.2
 */
function pwa_serve_admin_error_template() {
	add_filter( 'wp_doing_ajax', '__return_false' );
	require dirname( __FILE__ ) . '/error.php';
	exit;
}
add_action( 'wp_ajax_wp_error_template', 'pwa_serve_admin_error_template' );
add_action( 'wp_ajax_nopriv_wp_error_template', 'pwa_serve_admin_error_template' );

/**
 * Add customizer settings for maskable icon checkbox.
 *
 * @since 0.7
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager object.
 *
 * @return void
 */
function register_maskable_icon_setting( WP_Customize_Manager $wp_customize ) {
	// add setting in the site identity section.
	$wp_customize->add_setting(
		'pwa_maskable_icon',
		array(
			'capability' => 'manage_options',
			'type'       => 'option',
			'default'    => false,
			'transport'  => 'postMessage',
		)
	);

	// add control for the setting.
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
add_action( 'customize_register', 'register_maskable_icon_setting' );

/**
 * Enqueue scripts for maskable icon customizer setting.
 *
 * @return void
 */
function maskable_icon_scripts() {
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
			'maskable_icon' => get_option( 'pwa_maskable_icon', false ),
		)
	);

	wp_enqueue_script( 'pwa_customizer_script' );
}
add_action( 'customize_controls_enqueue_scripts', 'maskable_icon_scripts' );
