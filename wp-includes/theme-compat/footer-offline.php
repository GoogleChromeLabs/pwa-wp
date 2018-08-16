<?php
/**
 * Contains the offline (and 500) footer template
 *
 * When the site cannot be reached because it is offline or the user is offline, this file is used to
 * create the header output if the active theme does not include a footer-offline.php template.
 *
 * @package WordPress
 * @subpackage Theme_Compat
 * @since 4.5.0
 */

/**
 * Prints scripts or data before the closing body tag in the offline template.
 *
 * @since 0.2.0
 */
do_action( 'offline_footer' );
?>
</body>
</html>
