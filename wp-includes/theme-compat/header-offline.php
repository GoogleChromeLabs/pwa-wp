<?php
/**
 * Contains the offline (and 500) header template
 *
 * When the site cannot be reached because it is offline or the user is offline, this file is used to
 * create the header output if the active theme does not include a header-offline.php template.
 *
 * @package PWA
 * @since 0.2.0
 */

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<title><?php echo esc_html( wp_get_document_title() ); ?></title>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">

	<?php
	/**
	 * Prints scripts or data in the offline template <head> tag.
	 *
	 * @since 0.2
	 */
	do_action( 'offline_head' );
	?>
</head>
<body <?php body_class(); ?>>
