/* global workbox */

/**
 * Handle registering caching strategies.
 */

if ( ! self.wp ) {
	self.wp = {};
}

wp.serviceWorker = workbox;

{

	/**
	 * Custom router for handling conflicts between registered routes.
	 */
	class WPRouter extends wp.serviceWorker.routing.Router {

		findMatchingRoute( {
			url,
			request,
			event
		} ) {
			const routes = this.routes.get( request.method ) || [];
			let matches = 0,
				matchResult,
				firstMatch;
			for ( const route of routes ) {
				matchResult = route.match( { url, request, event } );
				if ( matchResult ) {
					matches++;

					// First match.
					if ( 1 === matches ) {
						if ( Array.isArray( matchResult ) && 0 === matchResult.length ) {
							// Instead of passing an empty array in as params, use undefined.
							matchResult = undefined;
						} else if ( matchResult.constructor === Object && Object.keys( matchResult ).length === 0 || matchResult === true ) {
							// Instead of passing an empty object in as params, use undefined.
							matchResult = undefined;
						}

						firstMatch = {
							route,
							params: matchResult
						};
					}
				}
			}

			// If we didn't have a match, then return undefined values.
			if ( 0 === matches ) {
				return { route: undefined, params: undefined };
			} else {
				// Log conflicting routes and return the first.
				if ( 1 < matches ) {
					wp.serviceWorker.core._private.logger.warn( `Multiple matches found for ${url.href}. Routing the first match.` );
				}
				return firstMatch;
			}
		}

		/**
		 * This method is mostly copied from DefaultRouter.
		 *
		 * Easily register a RegExp, string, or function with a caching
		 * strategy to the Router. This method will generate a Route for you if needed and
		 * call [Router.registerRoute()]{@link workbox.routing.Router#registerRoute}.
		 *
		 * @param {RegExp|string|workbox.routing.Route~matchCallback|workbox.routing.Route|Function} capture - If the capture param is a `Route`, all other arguments will be ignored.
		 * @param {workbox.routing.Route~handlerCallback|Function} handler - A callback function that returns a Promise resulting in a Response.
		 * @param {string} [method='GET'] The HTTP method to match the Route against.
		 * @return {workbox.routing.Route} The generated `Route`(Useful for unregistering).
		 *
		 * @alias workbox.routing.registerRoute
		 */
		registerRoute( capture, handler, method = 'GET' ) {
			let route;

			if ( typeof capture === 'string' ) {
				const captureUrl = new URL( capture, location );

				{
					if ( ! ( capture.startsWith( '/' ) || capture.startsWith( 'http' ) ) ) {
						throw new wp.serviceWorker.core._private.WorkboxError('invalid-string', {
							moduleName: 'workbox-routing',
							className: 'WPRouter',
							funcName: 'registerRoute',
							paramName: 'capture'
						});
					}

					// We want to check if Express-style wildcards are in the pathname only.
					// TODO: Remove this log message in v4.
					const valueToCheck = capture.startsWith('http') ? captureUrl.pathname : capture;
					// See https://github.com/pillarjs/path-to-regexp#parameters
					const wildcards = '[*:?+]';
					if (valueToCheck.match(new RegExp(`${wildcards}`))) {
						wp.serviceWorker.core._private.logger.debug(`The '$capture' parameter contains an Express-style wildcard ` + `character (${wildcards}). Strings are now always interpreted as ` + `exact matches; use a RegExp for partial or wildcard matches.`);
					}
				}

				const matchCallback = ({ url }) => {
					{
						if (url.pathname === captureUrl.pathname && url.origin !== captureUrl.origin) {
							wp.serviceWorker.core._private.logger.debug(`${capture} only partially matches the cross-origin URL ` + `${url}. This route will only handle cross-origin requests ` + `if they match the entire URL.`);
						}
					}

					return url.href === captureUrl.href;
				};

				route = new wp.serviceWorker.routing.Route( matchCallback, handler, method );
			} else if ( capture instanceof RegExp ) {
				route = new wp.serviceWorker.routing.RegExpRoute( capture, handler, method );
			} else if ( 'function' === typeof capture ) {
				route = new wp.serviceWorker.routing.Route( capture, handler, method);
			} else if ( capture instanceof wp.serviceWorker.routing.Route ) {
				route = capture;
			} else {
				throw new wp.serviceWorker.core._private.WorkboxError('unsupported-route-type', {
					moduleName: 'workbox-routing',
					className: 'WPRouter',
					funcName: 'registerRoute',
					paramName: 'capture'
				} );
			}

			super.registerRoute( route );
			return route;
		}

		/**
		 * Register a route that will return a precached file for a navigation
		 * request. This is useful for the
		 * [application shell pattern]{@link https://developers.google.com/web/fundamentals/architecture/app-shell}.
		 *
		 * This method will generate a
		 * [NavigationRoute]{@link workbox.routing.NavigationRoute}
		 * and call
		 * [Router.registerRoute()]{@link workbox.routing.Router#registerRoute}
		 * .
		 *
		 * @param {string} cachedAssetUrl
		 * @param {Object} [options]
		 * @param {string} [options.cacheName] Cache name to store and retrieve
		 * requests. Defaults to precache cache name provided by
		 * [workbox-core.cacheNames]{@link workbox.core.cacheNames}.
		 * @param {Array<RegExp>} [options.blacklist=[]] If any of these patterns
		 * match, the route will not handle the request (even if a whitelist entry
		 * matches).
		 * @param {Array<RegExp>} [options.whitelist=[/./]] If any of these patterns
		 * match the URL's pathname and search parameter, the route will handle the
		 * request (assuming the blacklist doesn't match).
		 * @return {workbox.routing.NavigationRoute} Returns the generated
		 * Route.
		 *
		 * @alias workbox.routing.registerNavigationRoute
		 */
		registerNavigationRoute(cachedAssetUrl, options = {}) {
			const cacheName = wp.serviceWorker.core.cacheNames.precache;
			const handler = () => caches.match(cachedAssetUrl, {cacheName})
				.then((response) => {
					if (response) {
						return response;
					}
					// This shouldn't normally happen, but there are edge cases:
					// https://github.com/GoogleChrome/workbox/issues/1441
					throw new Error(`The cache ${cacheName} did not have an entry for ` +
						`${cachedAssetUrl}.`);
				}).catch(() => {
					// If there's either a cache miss, or the caches.match() call threw
					// an exception, then attempt to fulfill the navigation request with
					// a response from the network rather than leaving the user with a
					// failed navigation.

					// This might still fail if the browser is offline...
					return fetch(cachedAssetUrl);
				});

			const route = new wp.serviceWorker.routing.NavigationRoute(handler, {
				whitelist: options.whitelist,
				blacklist: options.blacklist,
			});
			super.registerRoute(route);

			return route;
		}
	}

	wp.serviceWorker.WPRouter = WPRouter;

	const publicAPI = Object.freeze({
		RegExpRoute: wp.serviceWorker.routing.RegExpRoute,
		Route: wp.serviceWorker.routing.Route,
		Router: wp.serviceWorker.routing.Router,
		NavigationRoute: wp.serviceWorker.routing.NavigationRoute
	});

	wp.serviceWorker.routing = Object.assign( new WPRouter(), publicAPI );
}

// @todo There is another 'fetch' handler being for DefaultRouter added in the workbox-routing module which will be unused since.
self.addEventListener( 'fetch', event => {
	const request = event.request;
	const responsePromise = wp.serviceWorker.routing.handleRequest( { request, event } );
	if ( responsePromise ) {
		event.respondWith( responsePromise );
	}
} );

// Skip the waiting phase for the Service Worker.
self.addEventListener( 'message', function ( event ) {
	if ( 'skipWaiting' === event.data.action ) {
		self.skipWaiting();
	}
} );
