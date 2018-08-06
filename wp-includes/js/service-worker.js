/* global workbox */

/**
 * Handle registering caching strategies.
 */

if ( ! self.wp ) {
	self.wp = {};
}

wp.serviceWorker = workbox;

/**
 * Custom router for handling conflicts between registered routes.
 */
class WPRouter extends wp.serviceWorker.routing.Router {

	_findHandlerAndParams( event, url ) {
		const routes = this._routes.get( event.request.method ) || [];
		let matches = 0,
			matchResult,
			matchedRoute = null;
		for ( const route of routes ) {
			matchResult = route.match( { url, event } );
			if ( matchResult ) {
				matches++;
				matchedRoute = route;
			}
		}

		// If we didn't have a match, then return undefined values.
		if ( 0 === matches ) {
			return { handler: undefined, params: undefined };
		} else if ( 1 === matches ) {
			// If there was exactly one match.
			if ( Array.isArray( matchResult ) && 0 === matchResult.length ) {
				// Instead of passing an empty array in as params, use undefined.
				matchResult = undefined;
			} else if ( matchResult.constructor === Object && Object.keys( matchResult ).length === 0 || matchResult === true ) {
				// Instead of passing an empty object in as params, use undefined.
				matchResult = undefined;
			}

			return {
				matchedRoute,
				params: matchResult,
				handler: matchedRoute.handler
			};

			// @todo Confirm approach for conflicting routes: for example perhaps we shouldn't consider multiple matches but same strategy a conflict. Maybe we should still use the first matching route and warn about others.
			// If more than 1 match was found, log the conflicts.
		} else {
			wp.serviceWorker.core._private.logger.warn( `Multiple matches found for ${url.href}. Skipping route.` );
			return { handler: undefined, params: undefined };
		}
	}

	/**
	 * This method is mostly copied from DefaultRouter.
	 *
	 * Easily register a RegExp, string, or function with a caching
	 * strategy to the Router.
	 *
	 * This method will generate a Route for you if needed and
	 * call [Router.registerRoute()]{@link
		* workbox.routing.Router#registerRoute}.
	 *
	 * @param {
     * RegExp|
     * string|
     * workbox.routing.Route~matchCallback|
     * workbox.routing.Route
     * } capture
	 * If the capture param is a `Route`, all other arguments will be ignored.
	 * @param {workbox.routing.Route~handlerCallback} handler A callback
	 * function that returns a Promise resulting in a Response.
	 * @param {string} [method='GET'] The HTTP method to match the Route
	 * against.
	 * @return {workbox.routing.Route} The generated `Route`(Useful for
	 * unregistering).
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
}

// Init custom router.
wp.serviceWorker.WPRouter = new WPRouter();
self.addEventListener( 'fetch', event => {
	const responsePromise = wp.serviceWorker.WPRouter.handleRequest( event );
	if ( responsePromise ) {
		event.respondWith( responsePromise );
	}
} );

// Add custom offline / error response serving.
let networkFirstHandler = workbox.strategies.networkFirst({
	cacheName: 'default',
	plugins: [
		new wp.serviceWorker.cacheableResponse.Plugin({
			statuses: [200]
		} )
	],
} );

const matcher = ( {event} ) => event.request.mode === 'navigate';
const handler = (args) => networkFirstHandler.handle(args).then( ( response ) => {

	// In case of error. @todo Separate handling of error case to add more information about the error?
	if ( response && ! response.ok ) {
		return caches.match( '/offline.html' );
	} else {

		// If no response, return offline page.
		return ( ! response ) ? caches.match( '/offline.html' ) : response;
	}
} );

wp.serviceWorker.WPRouter.registerRoute( matcher, handler );
