/* global console, ERROR_OFFLINE_URL, ERROR_500_URL, THEME_SUPPORTS_STREAMING, STREAM_HEADER_FRAGMENT_URL, STREAM_HEADER_FRAGMENT_QUERY_VAR, BLACKLIST_PATTERNS */

if ( THEME_SUPPORTS_STREAMING ) {
	wp.serviceWorker.streams.isSupported(); // Make sure importScripts happens during SW installation.
}

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

		const themeSupportsStreaming = THEME_SUPPORTS_STREAMING;
		if ( themeSupportsStreaming ) {
			const streamHeaderFragmentURL = STREAM_HEADER_FRAGMENT_URL;
			const precacheStrategy = wp.serviceWorker.strategies.cacheFirst({
				cacheName: wp.serviceWorker.core.cacheNames.precache,
			});

			const url = new URL( event.request.url );
			url.searchParams.append( STREAM_HEADER_FRAGMENT_QUERY_VAR, 'body' );
			const request = new Request( url.toString(), {...event.request} );

			const stream = wp.serviceWorker.streams.concatenateToResponse([
				precacheStrategy.makeRequest({ request: streamHeaderFragmentURL }), // @todo This should be able to vary based on the request.url. No: just don't allow in paired mode.
				fetch( request ),
			]);

			// @todo Handle error case.
			return handleResponse( stream.response );
		} else {
			return fetch( event.request )
				.then( handleResponse )
				.catch( sendOfflineResponse );
		}
	},
	{
		blacklist: BLACKLIST_PATTERNS.map( ( pattern ) => new RegExp( pattern ) )
	}
) );
