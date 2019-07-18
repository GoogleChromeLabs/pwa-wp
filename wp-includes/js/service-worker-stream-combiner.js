/*global STREAM_COMBINE_INVOKE_SCRIPT_ID, STREAM_COMBINE_DEFINE_SCRIPT_ID, STREAM_FRAGMENT_BOUNDARY_ELEMENT_ID */

'use strict';
/* This JS file will be added as an inline script in a stream header fragment response. */
/* This file currently uses JS features which are compatible with Chrome 40 (Googlebot). */

/**
 * Apply the stream body data to the stream header.
 *
 * @param {Array}  data                 - Data collected from the DOMDocument prior to the stream boundary.
 * @param {Array}  data.head_nodes      - Nodes in HEAD.
 * @param {Object} data.body_attributes - Attributes on body.
 */
function wpStreamCombine( data ) { /* eslint-disable-line no-unused-vars */
	const processedHeadNodeData = new WeakSet();
	const processedHeadElements = new WeakSet();

	/**
	 * Determine if a given element matches the nodeData coming from the body fragment.
	 *
	 * Returns true if the element name is the same and its attributes are equal. Node textContent is not matched.
	 *
	 * @param {Element} element        - Element in head to compare.
	 * @param {Object}  newElementData - Head node data to compare with.
	 * @return {boolean} Matching.
	 */
	const isElementMatchingData = ( element, newElementData ) => {
		if ( element.nodeName.toLowerCase() !== newElementData[ 0 ] ) {
			return false;
		}

		const elementAttributes = Array.from( element.attributes )
			.map( ( attribute ) => {
				return attribute.nodeName + '=' + attribute.nodeValue;
			} )
			.sort().join( ';' );

		const dataAttributes = Object.entries( newElementData[ 1 ] || {} )
			.map( ( [ name, value ] ) => {
				return name + '=' + value;
			} )
			.sort().join( ';' );

		return elementAttributes === dataAttributes;
	};

	// Find identical nodes and update text content of any elements with matching attributes.
	data.head_nodes
		.filter( ( headNodeData ) => '#comment' !== headNodeData[ 0 ] )
		.forEach( ( headNodeData ) => {
			const headChildElement = Array.from( document.head.getElementsByTagName( headNodeData[ 0 ] ) ).find( ( element ) => {
				return ! processedHeadElements.has( element ) && isElementMatchingData( element, headNodeData );
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
			processedHeadElements.add( element );
		} );

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
		'wlwmanifest',
	] );
	Array.from( document.head.querySelectorAll( 'link[rel]' ) ).forEach( ( link ) => {
		if ( ! processedHeadElements.has( link ) && ! preservedLinkRels.has( link.rel ) ) {
			link.remove();
		}
	} );
	const preservedMeta = new Set( [
		'viewport',
		'generator',
		'msapplication-TileImage',
	] );
	Array.from( document.head.querySelectorAll( 'meta[name],meta[property]' ) ).forEach( ( meta ) => {
		if ( ! processedHeadElements.has( meta ) && ! preservedMeta.has( meta.getAttribute( 'name' ) || meta.getAttribute( 'property' ) ) ) {
			meta.remove();
		}
	} );

	// Replace comments.
	const pendingCommentNodes = data.head_nodes.filter( ( headNodeData ) => '#comment' === headNodeData[ 0 ] );
	for ( const node of document.head.childNodes ) {
		if ( 0 === pendingCommentNodes.length ) {
			break;
		}
		if ( 8 === node.nodeType ) {
			node.nodeValue = pendingCommentNodes.shift()[ 1 ];
		}
	}
	// Add remaining comments that didn't match up.
	pendingCommentNodes.forEach( ( headNodeData ) => {
		document.head.appendChild( document.createTextNode( headNodeData[ 1 ] ) );
	} );

	// Populate body attributes.
	for ( const key in data.body_attributes ) {
		document.body.setAttribute( key, data.body_attributes[ key ] );
	}

	/* Purge all traces of the stream combination logic since it isn't needed anymore. */
	const removedElements = [
		STREAM_COMBINE_INVOKE_SCRIPT_ID,
		STREAM_COMBINE_DEFINE_SCRIPT_ID,
		STREAM_FRAGMENT_BOUNDARY_ELEMENT_ID,
	];
	for ( const elementId of removedElements ) {
		const element = document.getElementById( elementId );
		if ( element ) {
			element.parentNode.removeChild( element );
		}
	}
}
