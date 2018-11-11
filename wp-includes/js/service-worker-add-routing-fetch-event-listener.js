/*
 * Note: This fetch event listener was initially in wp-includes/js/service-worker.js
 * The reason for the move is that the fetch event needs to be added _after_ the call to precaching.addRoute() is made, so
 * that precached assets will be handled before falling back to other caching strategies for non-precached routes.
 * If the caching strategy handler is added first, then the precache route would never handle it.
 */

// @todo There is another 'fetch' handler being for DefaultRouter added in the workbox-routing module which will be unused since.
self.addEventListener( 'fetch', event => {
	const request = event.request;
	const responsePromise = wp.serviceWorker.routing.handleRequest( { request, event } );
	if ( responsePromise ) {
		event.respondWith( responsePromise );
	}
} );
