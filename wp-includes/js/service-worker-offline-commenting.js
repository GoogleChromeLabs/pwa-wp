/* global ERROR_OFFLINE_URL, ERROR_MESSAGES, ERROR_500_URL */
{
	const queue = new wp.serviceWorker.backgroundSync.Queue( 'wpPendingComments' );
	const errorMessages = ERROR_MESSAGES;

	const commentHandler = ( { event } ) => {

		const clone = event.request.clone();
		return fetch( event.request )
			.then( ( response ) => {
				if ( response.status < 500 ) {
					return response;
				}

				// @todo Replace depending on BroadcastChannel.
				const channel = new BroadcastChannel( 'wordpress-server-errors' );

				// Wait for client to request the error message.
				channel.onmessage = ( event ) => {
					if ( event.data && event.data.clientUrl && clone.url === event.data.clientUrl ) {
						response.text().then( ( text ) => {
							channel.postMessage({
								requestUrl: clone.url,
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
			.catch( () => {
				const bodyPromise = clone.blob();
				bodyPromise.then(
					function( body ) {
						const request = event.request;
						const req = new Request( request.url, {
							method: request.method,
							headers: request.headers,
							mode: 'same-origin',
							credentials: request.credentials,
							referrer: request.referrer,
							redirect: 'manual',
							body: body
						} );

						// Add request to queue. @todo Replace when upgrading to Workbox v4!
						queue.addRequest( req );
					}
				);

				return caches.match( ERROR_OFFLINE_URL ).then( function( response ) {

					return response.text().then( function( text ) {
						let init = {
							status: response.status,
							statusText: response.statusText,
							headers: response.headers
						};

						const body = text.replace( /<!--WP_SERVICE_WORKER_ERROR_MESSAGE-->/, errorMessages.comment );

						return new Response( body, init );
					} );
				} );
			} );
	};

	wp.serviceWorker.routing.registerRoute(
		new RegExp('/wp-comments-post\.php' ), // eslint-disable-line no-useless-escape
		commentHandler,
		'POST'
	);
}
