/* global console, ERROR_OFFLINE_URL, ERROR_500_URL, SHOULD_STREAM_RESPONSE, STREAM_HEADER_FRAGMENT_URL, ERROR_500_BODY_FRAGMENT_URL, ERROR_OFFLINE_BODY_FRAGMENT_URL, STREAM_HEADER_FRAGMENT_QUERY_VAR, BLACKLIST_PATTERNS */

const isStreamingResponses = SHOULD_STREAM_RESPONSE && wp.serviceWorker.streams.isSupported();

wp.serviceWorker.routing.registerRoute( new wp.serviceWorker.routing.NavigationRoute(
	async function ( { event } ) {
		const { url } = event.request;

		let responsePreloaded = false;

		const canStreamResponse = () => {
			return isStreamingResponses && ! responsePreloaded;
		};

		const handleResponse = ( response ) => {
			if ( response.status < 500 ) {
				if ( response.redirected ) {
					const redirectedUrl = new URL( response.url );
					redirectedUrl.searchParams.delete( STREAM_HEADER_FRAGMENT_QUERY_VAR );
					const script = `
						<script id="wp-stream-fragment-replace-state">
						history.replaceState( {}, '', ${ JSON.stringify( redirectedUrl.toString() ) } );
						document.getElementById( 'wp-stream-fragment-replace-state' ).remove();
						</script>
					`;
					return response.text().then( ( body ) => {
						return new Response( script + body );
					} );
				} else {
					return response;
				}
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

			return caches.match( canStreamResponse() ? ERROR_500_BODY_FRAGMENT_URL : ERROR_500_URL );
		};

		const sendOfflineResponse = () => {
			return caches.match( canStreamResponse() ? ERROR_OFFLINE_BODY_FRAGMENT_URL : ERROR_OFFLINE_URL );
		};

		/*
		 * If navigation preload is enabled, use the preload request instead of doing another fetch.
		 * This prevents requests from being duplicated. See <https://github.com/xwp/pwa-wp/issues/67>.
		 */
		if ( event.preloadResponse ) {
			try {
				const response = await event.preloadResponse;
				if ( response ) {
					responsePreloaded = true;
					return handleResponse( response );
				}
			} catch ( error ) {
				responsePreloaded = true;
				return sendOfflineResponse();
			}
		}

		if ( canStreamResponse() ) {
			const streamHeaderFragmentURL = STREAM_HEADER_FRAGMENT_URL;
			const precacheStrategy = wp.serviceWorker.strategies.cacheFirst({
				cacheName: wp.serviceWorker.core.cacheNames.precache,
			});

			const url = new URL( event.request.url );
			url.searchParams.append( STREAM_HEADER_FRAGMENT_QUERY_VAR, 'body' );
			const request = new Request( url.toString(), {...event.request} );

			const stream = wp.serviceWorker.streams.concatenateToResponse([
				precacheStrategy.makeRequest({ request: streamHeaderFragmentURL }), // @todo This should be able to vary based on the request.url. No: just don't allow in paired mode.
				fetch( request )
					.then( handleResponse )
					.catch( sendOfflineResponse ),
			]);

			return stream.response;
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
