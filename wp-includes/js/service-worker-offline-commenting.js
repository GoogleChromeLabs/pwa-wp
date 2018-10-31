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

				return response.text().then( function( errorText ) {
					return caches.match( ERROR_500_URL ).then( function( errorResponse ) {

						if ( ! errorResponse ) {
							return response;
						}

						return errorResponse.text().then( function( text ) {
							let init = {
								status: errorResponse.status,
								statusText: errorResponse.statusText,
								headers: errorResponse.headers
							};

							let body = text.replace( /<!--WP_SERVICE_WORKER_ERROR_MESSAGE-->/, errorMessages.error );
							body = body.replace( /<!--WP_SERVICE_WORKER_ERROR_DETAILS-->/, errorText );

							return new Response( body, init );
						} );
					} );
				} );
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
		/\/wp-comments-post\.php$/,
		commentHandler,
		'POST'
	);
}
