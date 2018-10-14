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
	const processedHeadNodeData = new WeakSet();
	const processedHeadElements = new WeakSet();

	// @todo Rename to getElementMatch.
	// Mark all identical nodes as having already been processed.
	const isElementMatchingData = ( element, newElementData ) => {
		if ( element.nodeName.toLowerCase() !== newElementData[0] ) {
			return false;
		}

		const elementAttributes = Array.from( element.attributes )
			/*.filter( ( attribute ) => ! variableAttributes.has( attribute.nodeName ) )*/
			.map( ( attribute ) => {
				return attribute.nodeName + '=' + attribute.nodeValue;
			} )
			.sort().join( ';' );

		const dataAttributes = Object.entries( newElementData[ 1 ] || {} )
			/*.filter( ( [ name ] ) => ! variableAttributes.has( name ) )*/
			.map( ( [ name, value ] ) => {
				return name + '=' + value;
			} )
			.sort().join( ';' );

		return elementAttributes === dataAttributes;
	};

	// Remove links and meta which are probably all stale in the header.
	const preservedLinkRels = new Set( [
		'EditURI',
		'apple-touch-icon-precomposed',
		'dns-prefetch',
		'https://api.w.org/',
		'icon',
		'pingback',
		'preconnect',
		'preload',
		'profile',
		'stylesheet',
		'wlwmanifest'
	] );
	Array.from( document.head.querySelectorAll( 'link[rel]' ) ).forEach( ( link ) => {
		if ( ! preservedLinkRels.has( link.rel ) ) {
			link.remove();
		}
	} );
	const preservedMeta = new Set( [
		'viewport',
		'generator',
		'msapplication-TileImage',
	] );
	Array.from( document.head.querySelectorAll( 'meta[name],meta[property]' ) ).forEach( ( meta ) => {
		if ( ! preservedMeta.has( meta.getAttribute( 'name' ) || meta.getAttribute( 'property' ) ) ) {
			meta.remove();
		}
	} );

	data.head_nodes
		.filter( ( headNodeData ) => '#comment' !== headNodeData[ 0 ] )
		.forEach( ( headNodeData ) => {
			const headChildElement = Array.from( document.head.getElementsByTagName( headNodeData[ 0 ] ) ).find( ( element ) => {
				return ! processedHeadElements.has( element ) && isElementMatchingData( element, headNodeData )
			} );
			if ( ! headChildElement ) {
				return;
			}

			// Update node text if different.
			if ( 'undefined' !== typeof headNodeData[ 2 ] && headChildElement.firstChild && headChildElement.firstChild.nodeType === 3 && headNodeData[ 2 ] !== headChildElement.firstChild.nodeValue ) {
				headChildElement.firstChild.nodeValue = headNodeData[ 2 ];
			}

			processedHeadElements.add( headChildElement );
			processedHeadNodeData.add( headNodeData );
		} );

	// Now create elements for each of the remaining.
	data.head_nodes
		.filter( ( headNodeData ) => '#comment' !== headNodeData[ 0 ] && ! processedHeadNodeData.has( headNodeData ) )
		.forEach( ( headNodeData ) => {
			const element = document.createElement( headNodeData[ 0 ] );
			for ( const [ name, value ] of Object.entries( headNodeData[ 1 ] || {} ) ) {
				element.setAttribute( name, value );
			}
			document.head.appendChild( element );
		} );

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
