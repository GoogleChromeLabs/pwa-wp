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
	var node, nextNode, nodeData, refNode, elements;

	// Keep track of the elements we matched already so we don't keep updating the same element.
	const alreadyMatchedElements = new WeakSet();

	const isElementMatchingData = ( element, newElementData ) => {
		if ( element.nodeName.toLowerCase() !== newElementData[ 0 ] ) {
			return false;
		}
		const elementAttributes = Array.prototype.map.call( element.attributes, ( attribute ) => {
			return attribute.nodeName + '=' + attribute.nodeValue;
		} ).sort().join( ';' );

		const dataAttributes = ! newElementData[ 1 ] ? '' : Object.entries( newElementData[ 1 ] ).map( ( [ key, value ] ) => {
			return key + '=' + value;
		} ).sort().join( ';' );

		if ( elementAttributes !== dataAttributes ) {
			return false;
		}

		if ( 'undefined' === typeof newElementData[ 2 ] ) {
			return ! element.firstChild;
		} else {
			return element.firstChild === newElementData[ 2 ];
		}
	};

	const missingNodeData = [];
	headNodeLoop:
	for ( const nodeData of data.head_nodes ) {
		for ( const headChild of document.head.children ) {
			if ( isElementMatchingData( headChild, nodeData ) ) {
				alreadyMatchedElements.add( headChild );
				continue headNodeLoop;
			}
		}
		missingNodeData.push( nodeData );
	}

	// @todo Identify the head children
	// Now delete all nodes that
	const unmatchedElements = [];
	for ( const headChild of document.head.children ) {
		if ( ! alreadyMatchedElements.has( headChild ) ) {
			unmatchedElements.push( headChild );
		}
	}

	// @todo Update style elements.
	// @todo Update title element.
	// @todo Update JSON+LD element.
	// @todo Update rel=preconnect?

	console.info( 'missingNodeData', missingNodeData );
	console.info( 'unmatchingElements', unmatchedElements );

	return;

	//

	/* First, delete all nodes that are not elements since they are irrelevant. */
	node = document.head.firstChild;
	while ( nextNode ) {
		node = nextNode;
		nextNode = nextNode.nextSibling;
		if ( node.nodeType === 1 ) {
			document.head.removeChild( node );
		} else {
			document.head.removeChild( node );
		}
	}

	refNode = document.head.firstChild;
	while ( data.head_nodes.length ) {
		nodeData = data.head_nodes.shift();
		if ( '#comment' === nodeData[ 0 ] ) {
			const comment = document.createElement( nodeData[ 1 ] );
			document.head.insertBefore( comment, refNode );
			refNode = comment;
		} else {
			elements = document.head.getElementsByTagName( nodeData[ 0 ] );
			for ( let i = 0; i < elements.length; i++ ) {
				// if (  ) {
				//
				// }
			}
		}
	}


	// If it is the title, then it matches.
	// If it is meta[charset] then it matches.
	// If it is style[amp-custom] then it matches
	// If it is style[amp-boilerplate] then it matches.
	// No need to delete nodes; just replace/add.

	var createNode = function( nodeData ) {

	};
	var applyNodeChanges = function ( node, nodeData ) {

	};

	// // Replace all head nodes. @todo This should be smarter about only modifying elements when they differ.
	// for ( i = 0; i < document.head.childNodes.length; i++ ) {
	// 	if ( document.head.childNodes[ i ].nodeType !== 1 || ! document.head.childNodes[ i ].hasAttribute( 'amp-custom' ) ) {
	// 		continue;
	// 	}
	// 	const style = document.head.childNodes[ i ];
	// 	for ( j = 0; j < data.head_nodes.length; j++ ) {
	// 		if ( 'style' === data.head_nodes[ j ][ 0 ] && 'amp-custom' in data.head_nodes[ j ][ 1 ] ) {
	// 			style.firstChild.nodeValue = data.head_nodes[ j ][ 2 ];
	// 			break;
	// 		}
	// 	}
	// 	break;
	// }

	node = document.head.firstChild;
	while ( node ) {
		node = node.nextSibling;
		document.head.removeChild( document.head.firstChild );
	}
	for ( i = 0; i < data.head_nodes.length; i++ ) {
		if ( '#comment' === data.head_nodes[ i ][ 0 ] ) {
			node = document.createComment( data.head_nodes[ i ][ 1 ] );
		} else {
			node = document.createElement( data.head_nodes[ i ][ 0 ] );
			for ( const key in data.head_nodes[ i ][ 1 ] ) {
				node.setAttribute( key, data.head_nodes[ i ][ 1 ][ key ] );
			}
			// console.info(node.nodeName)
			// if ( 'style' === node.nodeName.toLowerCase() ) {
			// 	node.textContent = data.head_nodes[ i ][ 2 ];
			// }
		}
		document.head.appendChild( node );
		if ( 'string' === typeof data.head_nodes[ i ][ 2 ] ) {
			// node.appendChild( data.head_nodes[ i ][ 2 ] );
			// node.appendChild( document.createTextNode( '' ) );
			// node.firstChild.nodeValue = data.head_nodes[ i ][ 2 ];
		}
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
	for ( i = 0; i < removedElements.length; i++ ) {
		const element = document.getElementById( removedElements[ i ] );
		if ( element ) {
			element.parentNode.removeChild( element );
		}
	}
}
