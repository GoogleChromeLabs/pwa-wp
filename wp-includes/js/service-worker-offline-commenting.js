/* global ERROR_OFFLINE_URL */
{
	const queue = new wp.serviceWorker.backgroundSync.Queue( 'wpPendingComments' );

	const commentHandler = ( { event } ) => {
		const clone = event.request.clone();
		return fetch( event.request )
			.then( ( response ) => {
				return response;
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

						// Add request to queue.
						queue.addRequest( req );
					}
				);
				return caches.match( ERROR_OFFLINE_URL );
			} );
	};

	wp.serviceWorker.routing.registerRoute(
		'/wp-comments-post.php',
		commentHandler,
		'POST'
	);
}
