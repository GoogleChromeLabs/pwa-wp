"use strict";
/* This JS file will be added as an inline script in a stream header fragment response. */
/* This file currently uses JS features which are compatible with Chrome 40 (Googlebot). */

/**
 * Apply the stream body data to the stream header.
 *
 * @param {Array}  data Data.
 * @param {Array}  data.head_nodes      - Nodes in HEAD.
 * @param {Object} data.body_attributes - Attributes on body.
 */
function wpStreamCombine( data ) { /* eslint-disable-line no-unused-vars */
	let nodeData;

	// Update title.
	nodeData = data.head_nodes.find( ( thisNodeData ) => {
		return 'title' === thisNodeData[ 0 ];
	} );
	if ( nodeData ) {
		document.title = nodeData[ 2 ];
	}

	// Update style[amp-custom].
	const ampCustom = document.head.querySelector( 'style[amp-custom]' );
	nodeData = data.head_nodes.find( ( thisNodeData ) => {
		return 'style' === thisNodeData[ 0 ] && ( thisNodeData[ 1 ] && 'amp-custom' in thisNodeData[ 1 ] );
	} );
	if ( ampCustom && nodeData ) {
		ampCustom.firstChild.nodeValue = nodeData[ 2 ];
	}

	// Update rel=canonical link.
	const relCanonical = document.head.querySelector( 'link[rel=canonical]' );
	nodeData = data.head_nodes.find( ( thisNodeData ) => {
		return 'link' === thisNodeData[ 0 ] && ( thisNodeData[ 1 ] && 'canonical' === thisNodeData[ 1 ]['rel'] );
	} );
	if ( relCanonical && nodeData ) {
		relCanonical.setAttribute( 'href', nodeData[ 1 ][ 'href' ] )
	}

	// Update Schema.org data.
	const schemaScript = document.head.querySelector( 'script[type="application/ld+json"]' );
	nodeData = data.head_nodes.find( ( thisNodeData ) => {
		return 'script' === thisNodeData[ 0 ] && ( thisNodeData[ 1 ] && 'application/ld+json' === thisNodeData[ 1 ]['type'] );
	} );
	if ( schemaScript && nodeData ) {
		schemaScript.firstChild.nodeValue = nodeData[ 2 ];
	}

	// Populate body attributes.
	for ( const key in data.body_attributes ) {
		document.body.setAttribute( key, data.body_attributes[ key ] );
	}

	/* Purge all traces of the stream combination logic to ensure the AMP validator doesn't complain at runtime. */
	const removedElements = [
		'wp-stream-fragment-boundary',
		'wp-stream-combine-function',
		'wp-stream-combine-call'
	];
	for ( const elementId of removedElements ) {
		const element = document.getElementById( elementId );
		if ( element ) {
			element.parentNode.removeChild( element );
		}
	}
}
