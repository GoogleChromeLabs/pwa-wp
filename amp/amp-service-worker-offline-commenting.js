/* global ERROR_MESSAGES */
{
	const queue = new wp.serviceWorker.backgroundSync.Queue( 'amp-wpPendingComments' );
	const errorMessages = ERROR_MESSAGES;

	const commentHandler = ( { event } ) => {
		const clone = event.request.clone();
		return fetch( event.request )
			.then( ( response ) => {
				// @todo Make sure that 409 etc. error still work as expected.
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

						// Add request to queue. @todo Replace when upgrading to Workbox v4!
						queue.addRequest( req );
					}
				);

				// @todo That's not actually working yet.
				const body = JSON.stringify( { 'error': errorMessages.comment } );
				return new Response( body, {} );
			} );
	};

	wp.serviceWorker.routing.registerRoute(
		new RegExp('/wp-comments-post\.php\?.*_wp_amp_action_xhr_converted.*' ), // eslint-disable-line no-useless-escape
		commentHandler,
		'POST'
	);
}
