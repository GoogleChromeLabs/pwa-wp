/* global ERROR_OFFLINE_URL, ERROR_500_URL, BLACKLIST_PATTERNS */

// @todo Should this use setDefaultHandler?
wp.serviceWorker.routing.registerRoute( new wp.serviceWorker.routing.NavigationRoute(
	( {event} ) => {
		return fetch( event.request )
			.then( ( response ) => {
				// @todo Send response.status and response.statusText to client for display.
				return response.ok ? response : caches.match( ERROR_500_URL );
			} )
			.catch( ( error ) => {
				// @todo Send error.message to the client for display.
				return caches.match( ERROR_OFFLINE_URL ) || error;
			} );
	},
	{
		blacklist: BLACKLIST_PATTERNS.map( ( pattern ) => new RegExp( pattern ) )
	}
) );
