/* global PRECACHE_ENTRIES */

{
	wp.serviceWorker.precaching.precache( PRECACHE_ENTRIES );

	// @todo Use networkFirst instead of cacheFirst when WP_DEBUG.
	wp.serviceWorker.precaching.addRoute( {
		ignoreUrlParametersMatching: [
			/^utm_/,
			/^ver$/
		]
	} );
}
