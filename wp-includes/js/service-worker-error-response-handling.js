/* global console, ERROR_OFFLINE_URL, ERROR_500_URL, BLACKLIST_PATTERNS */

wp.serviceWorker.routing.registerRoute( new wp.serviceWorker.routing.NavigationRoute(
	async function ( { event } ) {
		const { url } = event.request;

		const handleResponse = ( response ) => {
			if ( response.status < 500 ) {
				return response;
			}
			const channel = new BroadcastChannel( 'wordpress-server-errors' );

			// Wait for client to request the error message.
			channel.onmessage = ( event ) => {
				if ( event.data && event.data.clientUrl && url === event.data.clientUrl ) {
					response.text().then( ( text ) => {
						channel.postMessage({
							requestUrl: url,
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
		};

		const sendOfflineResponse = () => {
			return caches.match( ERROR_OFFLINE_URL );
		};

		/*
		 * If navigation preload is enabled, use the preload request instead of doing another fetch.
		 * This prevents requests from being duplicated. See <https://github.com/xwp/pwa-wp/issues/67>.
		 */
		if ( event.preloadResponse ) {
			try {
				const response = await event.preloadResponse;
				if ( response ) {
					return handleResponse( response );
				}
			} catch ( error ) {
				return sendOfflineResponse();
			}
		}

		return fetch( event.request )
			.then( handleResponse )
			.catch( sendOfflineResponse );
	},
	{
		blacklist: BLACKLIST_PATTERNS.map( ( pattern ) => new RegExp( pattern ) )
	}
) );
