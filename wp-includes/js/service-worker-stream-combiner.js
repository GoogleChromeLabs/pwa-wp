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
	const processedHeadNodeData = new WeakSet();
	const processedHeadElements = new WeakSet();

	const variableLinkRels = new Set( [ 'canonical', 'shortlink' ] );

	const getElementVariableAttributes = ( element ) => {
		const nodeName = element.nodeName.toLowerCase();
		if ( 'meta' === nodeName ) {
			return new Set( [ 'content' ] );
		}
		if ( 'link' === nodeName ) {
			return new Set( [ 'href', 'title' ] ); // @todo There are multiple rel=preconnects.
		}
		return new Set();
	};

	// @todo Rename to getElementMatch.
	// Mark all identical nodes as having already been processed.
	const isElementMatchingData = ( element, newElementData ) => {
		if ( element.nodeName.toLowerCase() !== newElementData[0] ) {
			return false;
		}

		const variableAttributes = getElementVariableAttributes( element );

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
		// if ( elementAttributes !== dataAttributes ) {
		// 	return false;
		// }
		//
		// if ( 'undefined' === typeof newElementData[2] ) {
		// 	return ! element.firstChild;
		// } else {
		// 	return element.firstChild === newElementData[2];
		// }
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
			const variableAttributes = getElementVariableAttributes( headChildElement );

			// Update variable attributes.
			variableAttributes.forEach( ( variableAttributeName ) => {
				if ( headNodeData[ 1 ] && headChildElement.getAttribute( variableAttributeName ) !== headNodeData[ 1 ][ variableAttributeName ] ) {
					headChildElement.setAttribute( variableAttributeName, headNodeData[ 1 ][ variableAttributeName ] );
				}
			} );

			// Set node content if different.
			if ( 'undefined' !== typeof headNodeData[ 2 ] && headChildElement.firstChild && headChildElement.firstChild.nodeType === 3 && headNodeData[ 2 ] !== headChildElement.firstChild.nodeValue ) {
				headChildElement.firstChild.nodeValue = headNodeData[ 2 ];
				console.info( 'update', headChildElement, headNodeData );
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
			console.info( 'create', element );
			document.head.appendChild( element );
		} );

	return;

	data.head_nodes.filter( ( headNodeData ) => '#comment' !== headNodeData[ 0 ] ).forEach( ( headNodeData ) => {
		for ( const headChildElement of document.head.getElementsByTagName( headNodeData[ 0 ] ) ) {
			if ( isElementMatchingData( headChildElement, headNodeData ) ) {
				processedHeadNodeData.add( headNodeData );
				break;
			}
		}
	} );

	// Update title.
	nodeData = data.head_nodes.find( ( thisNodeData ) => {
		return 'title' === thisNodeData[ 0 ];
	} );
	if ( nodeData ) {
		processedHeadNodeData.add( nodeData );
		document.title = nodeData[ 2 ];
	}

	// Update style[amp-custom].
	const ampCustom = document.head.querySelector( 'style[amp-custom]' );
	nodeData = data.head_nodes.find( ( thisNodeData ) => {
		return 'style' === thisNodeData[ 0 ] && ( thisNodeData[ 1 ] && 'amp-custom' in thisNodeData[ 1 ] );
	} );
	if ( ampCustom && nodeData ) {
		processedHeadNodeData.add( nodeData );
		ampCustom.firstChild.nodeValue = nodeData[ 2 ];
	}

	// Update rel=canonical link.
	const relCanonical = document.head.querySelector( 'link[rel=canonical]' );
	nodeData = data.head_nodes.find( ( thisNodeData ) => {
		return 'link' === thisNodeData[ 0 ] && ( thisNodeData[ 1 ] && 'canonical' === thisNodeData[ 1 ]['rel'] );
	} );
	if ( relCanonical && nodeData ) {
		processedHeadNodeData.add( nodeData );
		relCanonical.setAttribute( 'href', nodeData[ 1 ][ 'href' ] )
	}

	// Update Schema.org data.
	const schemaScript = document.head.querySelector( 'script[type="application/ld+json"]' );
	nodeData = data.head_nodes.find( ( thisNodeData ) => {
		return 'script' === thisNodeData[ 0 ] && ( thisNodeData[ 1 ] && 'application/ld+json' === thisNodeData[ 1 ]['type'] );
	} );
	if ( schemaScript && nodeData ) {
		processedHeadNodeData.add( nodeData );
		schemaScript.firstChild.nodeValue = nodeData[ 2 ];
	}

	// Update meta tags.
	const metaHeadNodeDataLookup = new Map();
	data.head_nodes.forEach( ( nodeData ) => {
		if ( 'meta' !== nodeData[ 0 ] || ! nodeData[ 1 ] || 'undefined' === typeof nodeData[ 1 ][ 'content' ] ) {
			return;
		}
		if ( 'property' in nodeData[ 1 ] ) {
			metaHeadNodeDataLookup.set( 'property:' + nodeData[ 1 ][ 'property' ], nodeData );
		}
		if ( 'name' in nodeData[ 1 ] ) {
			metaHeadNodeDataLookup.set( 'name:' + nodeData[ 1 ][ 'name' ], nodeData );
		}
	} );
	document.head.querySelectorAll( 'meta[name][content]' ).forEach( ( meta ) => {
		const nodeData = metaHeadNodeDataLookup.get( 'name:' + meta.getAttribute( 'name' ) );
		if ( nodeData ) {
			meta.setAttribute( 'content', nodeData[ 1 ][ 'content' ] );
			processedHeadNodeData.add( nodeData );
		}
	} );
	document.head.querySelectorAll( 'meta[property][content]' ).forEach( ( meta ) => {
		const nodeData = metaHeadNodeDataLookup.get( 'property:' + meta.getAttribute( 'name' ) );
		if ( nodeData ) {
			meta.setAttribute( 'content', nodeData[ 1 ][ 'content' ] );
			processedHeadNodeData.add( nodeData );
		}
	} );

	// Add remaining elements
	data.head_nodes.forEach( ( headNodeData ) => {
		if ( processedHeadNodeData.has( headNodeData ) ) {
			return;
		}

		// @todo More to be done.
		if ( 'script' === headNodeData[ 0 ] && headNodeData[ 1 ] && 'src' in headNodeData[ 1 ] ) {
			const script = document.createElement( 'script' );
			for ( const [ name, value ] of Object.entries( headNodeData[ 1 ] ) ) {
				script.setAttribute( name, value );
			}
			document.head.appendChild( script );
		}
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
