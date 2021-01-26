#!/usr/bin/env php
<?php
/**
 * Rewrite README.md into WordPress's readme.txt
 *
 * @codeCoverageIgnore
 * @package PWA
 */

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fwrite
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents

if ( 'cli' !== php_sapi_name() ) {
	fwrite( STDERR, "Must run from CLI.\n" );
	exit( __LINE__ );
}

$readme_md = file_get_contents( __DIR__ . '/../README.md' );

$readme_txt = $readme_md;

// Transform the sections above the description.
$readme_txt = preg_replace_callback(
	'/^.+?(?=## Description)/s',
	static function ( $matches ) {
		// Delete lines with images.
		$input = trim( preg_replace( '/\[?!\[.+/', '', $matches[0] ) );

		$parts = preg_split( '/\n\n+/', $input );

		if ( 3 !== count( $parts ) ) {
			fwrite( STDERR, "Too many sections in header found.\n" );
			exit( __LINE__ );
		}

		$header = $parts[0];

		$description = $parts[1];
		if ( strlen( $description ) > 150 ) {
			fwrite( STDERR, "The short description is too long: $description\n" );
			exit( __LINE__ );
		}

		$metadata = array();
		foreach ( explode( "\n", $parts[2] ) as $meta ) {
			$meta = trim( $meta );
			if ( ! preg_match( '/^\*\*(?P<key>.+?):\*\* (?P<value>.+)/', $meta, $matches ) ) {
				fwrite( STDERR, "Parse error for meta line: $meta.\n" );
				exit( __LINE__ );
			}

			$unlinked_value = preg_replace( '/\[(.+?)]\(.+?\)/', '$1', $matches['value'] );

			$metadata[ $matches['key'] ] = $unlinked_value;

			// Extract License URI from link.
			if ( 'License' === $matches['key'] ) {
				$license_uri = preg_replace( '/\[.+?]\((.+?)\)/', '$1', $matches['value'] );

				if ( 0 !== strpos( $license_uri, 'http' ) ) {
					fwrite( STDERR, "Unable to extract License URI from: $meta.\n" );
					exit( __LINE__ );
				}

				$metadata['License URI'] = $license_uri;
			}
		}

		$expected_metadata = array(
			'Contributors',
			'Tags',
			'Requires at least',
			'Tested up to',
			'Stable tag',
			'License',
			'License URI',
			'Requires PHP',
		);
		foreach ( $expected_metadata as $key ) {
			if ( empty( $metadata[ $key ] ) ) {
				fwrite( STDERR, "Failed to parse metadata. Missing: $key\n" );
				exit( __LINE__ );
			}
		}

		$replaced = "$header\n";
		foreach ( $metadata as $key => $value ) {
			$replaced .= "$key: $value\n";
		}
		$replaced .= "\n$description\n\n";

		return $replaced;
	},
	$readme_txt
);

// Convert markdown headings into WP readme headings for good measure.
$readme_txt = preg_replace_callback(
	'/^(#+)\s(.+)/m',
	static function ( $matches ) {
		$md_heading_level = strlen( $matches[1] );
		$heading_text     = $matches[2];

		// #: ===
		// ##: ==
		// ###: =
		$txt_heading_level = 4 - $md_heading_level;
		if ( $txt_heading_level <= 0 ) {
			fwrite( STDERR, "Heading too small to transform: {$matches[0]}.\n" );
			exit( __LINE__ );
		}

		return sprintf(
			'%1$s %2$s %1$s',
			str_repeat( '=', $txt_heading_level ),
			$heading_text
		);
	},
	$readme_txt,
	-1,
	$replace_count
);
if ( 0 === $replace_count ) {
	fwrite( STDERR, "Unable to transform headings.\n" );
	exit( __LINE__ );
}

if ( ! file_put_contents( __DIR__ . '/../readme.txt', $readme_txt ) ) {
	fwrite( STDERR, "Failed to write readme.txt.\n" );
	exit( __LINE__ );
}

fwrite( STDOUT, "Validated README.md and generated readme.txt\n" );
