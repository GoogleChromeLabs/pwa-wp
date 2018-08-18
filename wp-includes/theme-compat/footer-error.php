<?php
/**
 * Contains the error (offline and 500) footer template
 *
 * When the site cannot be reached because it is offline or the user is offline, this file is used to
 * create the header output if the active theme does not include a footer-offline.php template.
 *
 * @package PWA
 * @since 0.2
 */

/**
 * Prints scripts or data before the closing body tag in the error template.
 *
 * @since 0.2
 */
do_action( 'error_footer' );
?>
</body>
</html>
