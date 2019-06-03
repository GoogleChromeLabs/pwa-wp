<?php
/**
 * Contains the error header template (e.g. offline and 500)
 *
 * When the site cannot be reached because it is offline or the user is offline, this file is used to
 * create the header output if the active theme does not include a header-offline.php template.
 *
 * @todo Consider loading this template in _default_wp_die_handler() if not is_admin() to easily allow themes to override the template by simply defining an error.php template.
 *
 * @package PWA
 * @since 0.2.0
 */

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width">
	<title><?php echo esc_html( wp_get_document_title() ); ?></title>

	<!-- The following style is copied from _default_wp_die_handler(). -->
	<style type="text/css">
	html {
		background: #f1f1f1;
	}
	body {
		background: #fff;
		color: #444;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
		margin: 2em auto;
		padding: 1em 2em;
		max-width: 700px;
		-webkit-box-shadow: 0 1px 3px rgba(0,0,0,0.13);
		box-shadow: 0 1px 3px rgba(0,0,0,0.13);
	}
	h1 {
		border-bottom: 1px solid #dadada;
		clear: both;
		color: #666;
		font-size: 24px;
		margin: 30px 0 0 0;
		padding: 0 0 7px;
	}
	#error-page {
		margin-top: 50px;
	}
	#error-page p {
		font-size: 14px;
		line-height: 1.5;
		margin: 25px 0 20px;
	}
	#error-page code {
		font-family: Consolas, Monaco, monospace;
	}
	ul li {
		margin-bottom: 10px;
		font-size: 14px ;
	}
	a {
		color: #0073aa;
	}
	a:hover,
	a:active {
		color: #00a0d2;
	}
	a:focus {
		color: #124964;
		-webkit-box-shadow: 0 0 0 1px #5b9dd9, 0 0 2px 1px rgba(30, 140, 190, .8);
		box-shadow: 0 0 0 1px #5b9dd9, 0 0 2px 1px rgba(30, 140, 190, .8);
		outline: none;
	}
	.button {
		background: #f7f7f7;
		border: 1px solid #ccc;
		color: #555;
		display: inline-block;
		text-decoration: none;
		font-size: 13px;
		line-height: 26px;
		height: 28px;
		margin: 0;
		padding: 0 10px 1px;
		cursor: pointer;
		-webkit-border-radius: 3px;
		-webkit-appearance: none;
		border-radius: 3px;
		white-space: nowrap;
		-webkit-box-sizing: border-box;
		-moz-box-sizing:    border-box;
		box-sizing:         border-box;

		-webkit-box-shadow: 0 1px 0 #ccc;
		box-shadow: 0 1px 0 #ccc;
		vertical-align: top;
	}

	.button.button-large {
		height: 30px;
		line-height: 28px;
		padding: 0 12px 2px;
	}

	.button:hover,
	.button:focus {
		background: #fafafa;
		border-color: #999;
		color: #23282d;
	}

	.button:focus  {
		border-color: #5b9dd9;
		-webkit-box-shadow: 0 0 3px rgba( 0, 115, 170, .8 );
		box-shadow: 0 0 3px rgba( 0, 115, 170, .8 );
		outline: none;
	}

	.button:active {
		background: #eee;
		border-color: #999;
		-webkit-box-shadow: inset 0 2px 5px -3px rgba( 0, 0, 0, 0.5 );
		box-shadow: inset 0 2px 5px -3px rgba( 0, 0, 0, 0.5 );
		-webkit-transform: translateY(1px);
		-ms-transform: translateY(1px);
		transform: translateY(1px);
	}

	<?php
	if ( is_rtl() ) {
		echo 'body { font-family: Tahoma, Arial; }';
	}
	?>
	</style>
	<?php
	/**
	 * Prints scripts or data in the error template <head> tag.
	 *
	 * @since 0.2
	 */
	do_action( 'error_head' );
	?>
</head>
<body id="error-page" <?php body_class(); ?>>
