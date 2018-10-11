/* global console, ERROR_OFFLINE_URL, ERROR_500_URL, THEME_SUPPORTS_STREAMING, STREAM_HEADER_FRAGMENT_URL, BLACKLIST_PATTERNS */

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

			wp.serviceWorker.streams.strategy([
				() => precacheStrategy.makeRequest({ request: streamHeaderFragmentURL }), // @todo This should be able to vary based on the request.url. No: just don't allow in paired mode.
				fetch( event.request ), // @todo Need to amend event.request.url with wp_service_worker_stream_fragment=body?
				// async ({event, url}) => {
				// 	event.request.url.searchParams.set( 'wp_service_worker_stream_fragment', 'body' );
				// 	const tag = url.searchParams.get('tag') || DEFAULT_TAG;
				// 	const listResponse = await apiStrategy.makeRequest(...);
				// 	const data = await listResponse.json();
				// 	return templates.index(tag, data.items);
				// },
			]);

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
