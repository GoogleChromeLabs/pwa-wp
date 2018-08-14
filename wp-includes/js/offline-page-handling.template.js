/* global OFFLINE_PAGE_URL */
{

	// Add custom offline / error response serving.
	const networkFirstHandler = wp.serviceWorker.strategies.networkFirst( {
		plugins: [
			new wp.serviceWorker.cacheableResponse.Plugin( {
				statuses: [200]
			} ),
			{
				// Prevent storing navigated pages in the cache; the offline page will be pre-cached.
				cacheWillUpdate: () => { return null; }
			}
		],
	} );

	const matcher = ( {event} ) => event.request.mode === 'navigate';

	const handler = (args) => networkFirstHandler.handle( args ).then( ( response ) => {
		// In case of error. @todo Separate handling of error case to add more information about the error?
		if ( response && ! response.ok ) {
			return caches.match( OFFLINE_PAGE_URL );
		} else {
			// If no response, return offline page.
			return ( ! response ) ? caches.match( OFFLINE_PAGE_URL ) : response;
		}
	} );
	wp.serviceWorker.WPRouter.registerRoute( matcher, handler );

}
