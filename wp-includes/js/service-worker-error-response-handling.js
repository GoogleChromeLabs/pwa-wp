/* global console, ERROR_OFFLINE_URL, ERROR_500_URL, BLACKLIST_PATTERNS */

// @todo Should this use setDefaultHandler?
wp.serviceWorker.routing.registerRoute( new wp.serviceWorker.routing.NavigationRoute(
	( {event} ) => {
		return fetch( event.request )
			.then( ( response ) => {
				if ( response.status < 500 ) {
					return response;
				}
				const { request } = event;
				const channel = new BroadcastChannel( 'wordpress-server-errors' );

				// Wait for client to request the error message.
				channel.onmessage = ( event ) => {
					if ( event.data && event.data.clientUrl && request.url === event.data.clientUrl ) {
						response.text().then( ( text ) => {
							channel.postMessage({
								requestUrl: request.url,
								bodyText: text,
								status: response.status,
								statusText: response.statusText
							});
							channel.close();
						} );
					}
				};

				// Close the channel if client did not request the message within 30 seconds.
				setTimeout( () => {
					channel.close();
				}, 30 * 1000 );

				return caches.match( ERROR_500_URL );
			} )
			.catch( ( error ) => {
				console.error( error ); // eslint-disable-line no-console

				return caches.match( ERROR_OFFLINE_URL ) || error;
			} );
	},
	{
		blacklist: BLACKLIST_PATTERNS.map( ( pattern ) => new RegExp( pattern ) )
	}
) );
