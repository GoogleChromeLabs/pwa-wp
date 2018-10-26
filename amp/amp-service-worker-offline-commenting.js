/* global ERROR_MESSAGES, SITE_URL */
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
			.catch( () => clone.blob() )
			.then( ( body ) => {
				const queuedRequest = new Request( event.request.url, {
					method: event.request.method,
					headers: event.request.headers,
					mode: 'same-origin',
					credentials: event.request.credentials,
					referrer: event.request.referrer,
					redirect: 'manual',
					body: body
				} );

				// Add request to queue. @todo Replace when upgrading to Workbox v4!
				queue.addRequest( queuedRequest );

				const jsonBody = JSON.stringify( { 'error': errorMessages.comment } );
				return new Response( jsonBody, {
					headers: {
						'Access-Control-Allow-Origin': SITE_URL,
						'Access-Control-Allow-Credentials': 'true',
						'Content-Type': 'application/json; charset=UTF-8',
						'Access-Control-Expose-Headers': 'AMP-Access-Control-Allow-Source-Origin',
						'AMP-Access-Control-Allow-Source-Origin': SITE_URL,
						'Cache-Control': 'no-cache, must-revalidate, max-age=0'
					}
				} );
			} );
	};

	wp.serviceWorker.routing.registerRoute(
		/\/wp-comments-post\.php\?.*_wp_amp_action_xhr_converted.*/,
		commentHandler,
		'POST'
	);
}
