/* global OFFLINE_PAGE_URL, ADMIN_URL_PATTERN */

// @todo Should this use setDefaultHandler?
wp.serviceWorker.WPRouter.registerRoute( new wp.serviceWorker.routing.NavigationRoute(
	( {event} ) => {
		return fetch( event.request )
			.then( ( response ) => {
				// @todo Send response.status and response.statusText to client for display.
				return response.ok ? response : caches.match( OFFLINE_PAGE_URL );
			} )
			.catch( ( error ) => {
				// @todo Send error.message to the client for display.
				return caches.match( OFFLINE_PAGE_URL ) || error;
			} );
	},
	{
		blacklist: [
			new RegExp( ADMIN_URL_PATTERN )
		]
	}
) );
