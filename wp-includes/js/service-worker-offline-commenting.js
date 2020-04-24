/* global ERROR_OFFLINE_URL, ERROR_MESSAGES, ERROR_500_URL */

// IIFE is used for lexical scoping instead of just a braces block due to bug with const in Safari.
(() => {
	const queue = new wp.serviceWorker.backgroundSync.Queue(
		'wpPendingComments'
	);
	const errorMessages = ERROR_MESSAGES;

	const commentHandler = ({ event }) => {
		const clone = event.request.clone();
		return fetch(event.request)
			.then((response) => {
				if (response.status < 500) {
					return response;
				}

				// @todo This is duplicated with code in service-worker-navigation-routing.js.
				return response.text().then(function (errorText) {
					return caches
						.match(
							wp.serviceWorker.precaching.getCacheKeyForURL(
								ERROR_500_URL
							)
						)
						.then(function (errorResponse) {
							if (!errorResponse) {
								return response;
							}

							return errorResponse.text().then(function (text) {
								const init = {
									status: errorResponse.status,
									statusText: errorResponse.statusText,
									headers: errorResponse.headers,
								};

								let body = text.replace(
									/[<]!--WP_SERVICE_WORKER_ERROR_MESSAGE-->/,
									errorMessages.error
								);
								body = body.replace(
									/([<]!--WP_SERVICE_WORKER_ERROR_TEMPLATE_BEGIN-->)((?:.|\n)+?)([<]!--WP_SERVICE_WORKER_ERROR_TEMPLATE_END-->)/,
									(details) => {
										if (!errorText) {
											return ''; // Remove the details from the document entirely.
										}
										const src =
											'data:text/html;base64,' +
											btoa(errorText); // The errorText encoded as a text/html data URL.
										const srcdoc = errorText
											.replace(/&/g, '&amp;')
											.replace(/'/g, '&#39;')
											.replace(/"/g, '&quot;')
											.replace(/</g, '&lt;')
											.replace(/>/g, '&gt;');
										const iframe = `<iframe style="width:100%" src="${src}"  srcdoc="${srcdoc}"></iframe>`;
										details = details.replace(
											'{{{error_details_iframe}}}',
											iframe
										);
										// The following are in case the user wants to include the <iframe> in the template.
										details = details.replace(
											'{{{iframe_src}}}',
											src
										);
										details = details.replace(
											'{{{iframe_srcdoc}}}',
											srcdoc
										);

										// Replace the comments.
										details = details.replace(
											'<' +
												'!--WP_SERVICE_WORKER_ERROR_TEMPLATE_BEGIN-->',
											''
										);
										details = details.replace(
											'<' +
												'!--WP_SERVICE_WORKER_ERROR_TEMPLATE_END-->',
											''
										);
										return details;
									}
								);

								return new Response(body, init);
							});
						});
				});
			})
			.catch(() => {
				const bodyPromise = clone.blob();
				bodyPromise.then(function (body) {
					const request = event.request;
					const req = new Request(request.url, {
						method: request.method,
						headers: request.headers,
						mode: 'same-origin',
						credentials: request.credentials,
						referrer: request.referrer,
						redirect: 'manual',
						body,
					});

					// Add request to queue.
					queue.pushRequest({
						request: req,
					});
				});

				// @todo This is duplicated with code in service-worker-navigation-routing.js.
				return caches
					.match(
						wp.serviceWorker.precaching.getCacheKeyForURL(
							ERROR_OFFLINE_URL
						)
					)
					.then(function (response) {
						return response.text().then(function (text) {
							const init = {
								status: response.status,
								statusText: response.statusText,
								headers: response.headers,
							};

							const body = text.replace(
								/[<]!--WP_SERVICE_WORKER_ERROR_MESSAGE-->/,
								errorMessages.comment
							);

							return new Response(body, init);
						});
					});
			});
	};

	wp.serviceWorker.routing.registerRoute(
		/\/wp-comments-post\.php$/,
		commentHandler,
		'POST'
	);
})();
