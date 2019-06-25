/* global NAVIGATION_PRELOAD, CACHING_STRATEGY, CACHING_STRATEGY_ARGS, NAVIGATION_ROUTE_ENTRY,
ERROR_OFFLINE_URL, ERROR_500_URL, SHOULD_STREAM_RESPONSE, STREAM_HEADER_FRAGMENT_URL, ERROR_500_BODY_FRAGMENT_URL,
ERROR_OFFLINE_BODY_FRAGMENT_URL, STREAM_HEADER_FRAGMENT_QUERY_VAR, NAVIGATION_BLACKLIST_PATTERNS, ERROR_MESSAGES */

// IIFE is used for lexical scoping instead of just a braces block due to bug with const in Safari.
( () => {
	const navigationPreload = NAVIGATION_PRELOAD;
	const isStreamingResponses = SHOULD_STREAM_RESPONSE && wp.serviceWorker.streams.isSupported();
	const errorMessages = ERROR_MESSAGES;
	const navigationRouteEntry = NAVIGATION_ROUTE_ENTRY;

	// Configure navigation preload.
	if ( false !== navigationPreload ) {
		if ( typeof navigationPreload === 'string' ) {
			wp.serviceWorker.navigationPreload.enable( navigationPreload );
		} else {
			wp.serviceWorker.navigationPreload.enable();
		}
	} else {
		wp.serviceWorker.navigationPreload.disable();
	}

	/**
	 * Handle navigation request.
	 *
	 * @param event Event.
	 * @return {Promise<Response>} Response.
	 */
	async function handleNavigationRequest( { event } ) {
		let responsePreloaded = false;

		const canStreamResponse = () => {
			return isStreamingResponses && ! responsePreloaded;
		};

		const handleResponse = ( response ) => {
			if ( response.status < 500 ) {
				if ( response.redirected && canStreamResponse() ) {
					const redirectedUrl = new URL( response.url );
					redirectedUrl.searchParams.delete( STREAM_HEADER_FRAGMENT_QUERY_VAR );
					const script = `
						<script id="wp-stream-fragment-replace-state">
						history.replaceState( {}, '', ${ JSON.stringify( redirectedUrl.toString() ) } );
						document.getElementById( 'wp-stream-fragment-replace-state' ).remove();
						</script>
					`;
					return response.text().then( ( body ) => {
						return new Response( script + body );
					} );
				} else {
					return response;
				}
			}

			if ( canStreamResponse() ) {
				return caches.match( wp.serviceWorker.precaching.getCacheKeyForURL( ERROR_500_BODY_FRAGMENT_URL ) );
			}

			const originalResponse = response.clone();
			return response.text().then( function( responseBody ) {

				// Prevent serving custom error template if WordPress is already responding with a valid error page (e.g. via wp_die()).
				if ( -1 !== responseBody.indexOf( '</html>' ) ) {
					return originalResponse;
				}

				return caches.match( wp.serviceWorker.precaching.getCacheKeyForURL( ERROR_500_URL ) ).then( function( errorResponse ) {

					if ( ! errorResponse ) {
						return response;
					}

					return errorResponse.text().then( function( text ) {
						let init = {
							status: errorResponse.status,
							statusText: errorResponse.statusText,
							headers: errorResponse.headers
						};

						let body = text.replace( /[<]!--WP_SERVICE_WORKER_ERROR_MESSAGE-->/, errorMessages.error );
						body = body.replace(
							/([<]!--WP_SERVICE_WORKER_ERROR_TEMPLATE_BEGIN-->)((?:.|\n)+?)([<]!--WP_SERVICE_WORKER_ERROR_TEMPLATE_END-->)/,
							( details ) => {
								if ( ! responseBody ) {
									return ''; // Remove the details from the document entirely.
								}
								const src = 'data:text/html;base64,' + btoa( responseBody ); // The errorText encoded as a text/html data URL.
								const srcdoc = responseBody
									.replace( /&/g, '&amp;' )
									.replace( /'/g, '&#39;' )
									.replace( /"/g, '&quot;' )
									.replace( /</g, '&lt;' )
									.replace( />/g, '&gt;' );
								const iframe = `<iframe style="width:100%" src="${src}" data-srcdoc="${srcdoc}"></iframe>`;
								details = details.replace( '{{{error_details_iframe}}}', iframe );
								// The following are in case the user wants to include the <iframe> in the template.
								details = details.replace( '{{{iframe_src}}}', src );
								details = details.replace( '{{{iframe_srcdoc}}}', srcdoc );

								// Replace the comments.
								details = details.replace( '<' + '!--WP_SERVICE_WORKER_ERROR_TEMPLATE_BEGIN-->', '' );
								details = details.replace( '<' + '!--WP_SERVICE_WORKER_ERROR_TEMPLATE_END-->', '' );
								return details;
							}
						);

						return new Response( body, init );
					} );
				} );
			} );
		};

		const sendOfflineResponse = () => {
			if ( canStreamResponse() ) {
				return caches.match( wp.serviceWorker.precaching.getCacheKeyForURL( ERROR_OFFLINE_BODY_FRAGMENT_URL ) );
			}

			return caches.match( wp.serviceWorker.precaching.getCacheKeyForURL( ERROR_OFFLINE_URL ) ).then( function( response ) {
				return response.text().then( function( text ) {
					let init = {
						status: response.status,
						statusText: response.statusText,
						headers: response.headers
					};

					const body = text.replace( /[<]!--WP_SERVICE_WORKER_ERROR_MESSAGE-->/, errorMessages.default );

					return new Response( body, init );
				} );
			} );
		};

		const navigationCacheStrategy = new wp.serviceWorker.strategies[ CACHING_STRATEGY ]( CACHING_STRATEGY_ARGS );

		if ( canStreamResponse() ) {
			const streamHeaderFragmentURL = STREAM_HEADER_FRAGMENT_URL;
			const precacheStrategy = new wp.serviceWorker.strategies.cacheFirst({
				cacheName: wp.serviceWorker.core.cacheNames.precache,
			});

			const url = new URL( event.request.url );
			url.searchParams.append( STREAM_HEADER_FRAGMENT_QUERY_VAR, 'body' );
			const init = {
				mode: 'same-origin'
			};
			const copiedProps = [
				'method',
				'headers',
				'credentials',
				'cache',
				'redirect',
				'referrer',
				'integrity',
			];
			for ( const initProp of copiedProps ) {
				init[ initProp ] = event.request[ initProp ];
			}
			const request = new Request( url.toString(), init );
			const stream = wp.serviceWorker.streams.concatenateToResponse([
				precacheStrategy.makeRequest({ request: streamHeaderFragmentURL }),
				navigationCacheStrategy.makeRequest( { request } )
					.then( handleResponse )
					.catch( sendOfflineResponse ),
			]);

			return stream.response;
		} else {
			return navigationCacheStrategy.handle( { event, request: event.request } )
				.then( handleResponse )
				.catch( sendOfflineResponse );
		}
	}

	const blacklist = NAVIGATION_BLACKLIST_PATTERNS.map( ( pattern ) => new RegExp( pattern ) );
	if ( navigationRouteEntry && navigationRouteEntry.url ) {
		wp.serviceWorker.routing.registerNavigationRoute(
			navigationRouteEntry.url,
			{ blacklist }
		);

		class FetchNavigationRoute extends wp.serviceWorker.routing.Route {
			/**
			 * If both `blacklist` and `whitelist` are provided, the `blacklist` will
			 * take precedence and the request will not match this route.
			 *
			 * @inheritDoc
			 */
			constructor(handler, {
				whitelist = [/./],
				blacklist = []
			} = {}) {
				super(options => this._match(options), handler);
				this._whitelist = whitelist;
				this._blacklist = blacklist;
			}

			/**
			 * Routes match handler.
			 *
			 * @param {Object} options
			 * @param {URL} options.url
			 * @param {Request} options.request
			 * @return {boolean}
			 *
			 * @private
			 */
			_match({ url, request }) {
				// This replaces checking for navigate in NavigationRoute, which looks for 'navigate' instead.
				if ( request.mode !== 'same-origin' ) {
					return false;
				}

				const pathnameAndSearch = url.pathname + url.search;
				for (const regExp of this._blacklist) {
					if (regExp.test(pathnameAndSearch)) {
						return false;
					}
				}

				return this._whitelist.some(regExp => regExp.test(pathnameAndSearch));
			}
		}

		wp.serviceWorker.routing.registerRoute(
			new FetchNavigationRoute(
				handleNavigationRequest,
				{ blacklist }
			)
		);
	} else {
		wp.serviceWorker.routing.registerRoute( new wp.serviceWorker.routing.NavigationRoute(
			handleNavigationRequest,
			{ blacklist }
		) );
	}
} )();

// Add fallback network-only navigation route to ensure preloadResponse is used if available.
wp.serviceWorker.routing.registerRoute( new wp.serviceWorker.routing.NavigationRoute(
	new wp.serviceWorker.strategies.NetworkOnly(),
	{
		whitelist: NAVIGATION_BLACKLIST_PATTERNS.map( ( pattern ) => new RegExp( pattern ) )
	}
) );
