#!/usr/bin/env php
<?php
/**
 * Verify versions referenced in plugin match.
 *
 * @codeCoverageIgnore
 * @package PWA
 */

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fwrite
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode

if ( 'cli' !== php_sapi_name() ) {
	fwrite( STDERR, "Must run from CLI.\n" );
	exit( 1 );
}

$versions = array();

$readme_txt = file_get_contents( dirname( __FILE__ ) . '/../readme.txt' );
if ( ! preg_match( '/Stable tag:\s+(?P<version>\S+)/i', $readme_txt, $matches ) ) {
	echo "Could not find stable tag in readme\n";
	exit( 1 );
}
$versions['readme.txt#stable-tag'] = $matches['version'];

$plugin_file = file_get_contents( dirname( __FILE__ ) . '/../pwa.php' );
if ( ! preg_match( '/\*\s*Version:\s*(?P<version>\d+\.\d+(?:.\d+)?(-\w+)?)/', $plugin_file, $matches ) ) {
	echo "Could not find version in readme metadata\n";
	exit( 1 );
}
$versions['pwa.php#metadata'] = $matches['version'];

if ( ! preg_match( '/define\( \'PWA_VERSION\', \'(?P<version>[^\\\']+)\'/', $plugin_file, $matches ) ) {
	echo "Could not find version in PWA_VERSION constant\n";
	exit( 1 );
}
$versions['PWA_VERSION'] = $matches['version'];

fwrite( STDERR, "Version references:\n" );

echo json_encode( $versions, JSON_PRETTY_PRINT ) . "\n";

if ( 1 !== count( array_unique( $versions ) ) ) {
	fwrite( STDERR, "Error: Not all version references have been updated.\n" );
	exit( 1 );
}

if ( false === strpos( $versions['pwa.php#metadata'], '-' ) && ! preg_match( '/^\d+\.\d+\.\d+$/', $versions['pwa.php#metadata'] ) ) {
	fwrite( STDERR, sprintf( "Error: Release version (%s) lacks patch number. For new point releases, supply patch number of 0, such as 0.9.0 instead of 0.9.\n", $versions['pwa.php#metadata'] ) );
	exit( 1 );
}
