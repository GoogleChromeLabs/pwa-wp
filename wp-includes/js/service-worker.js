/* global workbox */

/**
 * Handle registering caching strategies.
 */

if ( ! self.wp ) {
	self.wp = {};
}

wp.serviceWorker = workbox;

// Skip the waiting phase for the Service Worker.
self.addEventListener( 'message', function( event ) {
	if ( 'skipWaiting' === event.data.action ) {
		self.skipWaiting();
	}
} );
