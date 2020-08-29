/* global NAVIGATION_PRELOAD, CACHING_STRATEGY, CACHING_STRATEGY_ARGS, NAVIGATION_ROUTE_ENTRY,
ERROR_OFFLINE_URL, ERROR_500_URL, NAVIGATION_DENYLIST_PATTERNS, ERROR_MESSAGES */

// IIFE is used for lexical scoping instead of just a braces block due to bug with const in Safari.
(() => {
	const navigationPreload = NAVIGATION_PRELOAD;
	const errorMessages = ERROR_MESSAGES;
	const navigationRouteEntry = NAVIGATION_ROUTE_ENTRY;

	// Configure navigation preload.
	if (false !== navigationPreload) {
		if (typeof navigationPreload === 'string') {
			wp.serviceWorker.navigationPreload.enable(navigationPreload);
		} else {
			wp.serviceWorker.navigationPreload.enable();
		}
	} else {
		wp.serviceWorker.navigationPreload.disable();
	}

	/*
	 * Define strategy up front so that Workbox modules will import at install time.
	 * If this is not done, then an error will happen like:
	 * > Unable to import module 'workbox-expiration'
	 * Along with an exception:
	 * > workbox-sw.js:1 Uncaught (in promise) DOMException: Failed to execute 'importScripts' on 'WorkerGlobalScope'
	 */
	const navigationCacheStrategy = new wp.serviceWorker.strategies[
		CACHING_STRATEGY
	](CACHING_STRATEGY_ARGS);

	/**
	 * Handle navigation request.
	 *
	 * @param {Object} args Args.
	 * @param {FetchEvent} args.event Event.
	 * @return {Promise<Response>} Response.
	 */
	async function handleNavigationRequest({ event }) {
		const handleResponse = (response) => {
			if (response.status < 500) {
				return response;
			}

			const originalResponse = response.clone();
			return response.text().then(function (responseBody) {
				// Prevent serving custom error template if WordPress is already responding with a valid error page (e.g. via wp_die()).
				if (-1 !== responseBody.indexOf('</html>')) {
					return originalResponse;
				}

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
									if (!responseBody) {
										return ''; // Remove the details from the document entirely.
									}
									const src =
										'data:text/html;base64,' +
										btoa(responseBody); // The errorText encoded as a text/html data URL.
									const srcdoc = responseBody
										.replace(/&/g, '&amp;')
										.replace(/'/g, '&#39;')
										.replace(/"/g, '&quot;')
										.replace(/</g, '&lt;')
										.replace(/>/g, '&gt;');
									const iframe = `<iframe style="width:100%" src="${src}" data-srcdoc="${srcdoc}"></iframe>`;
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
		};

		const sendOfflineResponse = () => {
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
							navigator.onLine
								? errorMessages.serverOffline
								: errorMessages.clientOffline
						);

						return new Response(body, init);
					});
				});
		};

		return navigationCacheStrategy
			.handle({ event, request: event.request })
			.then(handleResponse)
			.catch(sendOfflineResponse);
	}

	const denylist = NAVIGATION_DENYLIST_PATTERNS.map(
		(pattern) => new RegExp(pattern)
	);
	if (navigationRouteEntry && navigationRouteEntry.url) {
		wp.serviceWorker.routing.registerRoute(
			new wp.serviceWorker.routing.NavigationRoute(
				wp.serviceWorker.precaching.createHandlerBoundToURL(
					navigationRouteEntry.url
				),
				{
					denylist,
				}
			)
		);

		class FetchNavigationRoute extends wp.serviceWorker.routing.Route {
			/**
			 * If both `denylist` and `allowlist` are provided, the `denylist` will
			 * take precedence and the request will not match this route.
			 *
			 * @inheritdoc
			 */
			constructor(
				handler,
				{ allowlist: _allowlist = [/./], denylist: _denylist = [] } = {}
			) {
				super((options) => this._match(options), handler);
				this._allowlist = _allowlist;
				this._denylist = _denylist;
			}

			/**
			 * Routes match handler.
			 *
			 * @param {Object} options
			 * @param {URL} options.url
			 * @param {Request} options.request
			 * @return {boolean} Whether there is a match or not.
			 *
			 * @private
			 */
			_match({ url, request }) {
				// This replaces checking for navigate in NavigationRoute, which looks for 'navigate' instead.
				if (request.mode !== 'same-origin') {
					return false;
				}

				const pathnameAndSearch = url.pathname + url.search;
				// eslint-disable-next-line no-unused-vars
				for (const regExp of this._denylist) {
					if (regExp.test(pathnameAndSearch)) {
						return false;
					}
				}

				return this._allowlist.some((regExp) =>
					regExp.test(pathnameAndSearch)
				);
			}
		}

		wp.serviceWorker.routing.registerRoute(
			new FetchNavigationRoute(handleNavigationRequest, { denylist })
		);
	} else {
		wp.serviceWorker.routing.registerRoute(
			new wp.serviceWorker.routing.NavigationRoute(
				handleNavigationRequest,
				{
					denylist,
				}
			)
		);
	}
})();

// Add fallback network-only navigation route to ensure preloadResponse is used if available.
wp.serviceWorker.routing.registerRoute(
	new wp.serviceWorker.routing.NavigationRoute(
		new wp.serviceWorker.strategies.NetworkOnly(),
		{
			allowlist: NAVIGATION_DENYLIST_PATTERNS.map(
				(pattern) => new RegExp(pattern)
			),
		}
	)
);
