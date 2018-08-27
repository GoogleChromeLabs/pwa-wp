/* global PRECACHE_ENTRIES */

{
	const allEntries = PRECACHE_ENTRIES;
	const precacheEntries = [];
	const otherPrecacheEntries = {};

	for ( const entry of allEntries ) {
		if ( ! entry.cache || 'precache' === entry.cache || wp.serviceWorker.core.cacheNames.precache === entry.cache ) {
			precacheEntries.push( {
				url: entry.url,
				revision: entry.revision || null
			} );
		} else {
			if ( ! otherPrecacheEntries[ entry.cache ] ) {
				otherPrecacheEntries[ entry.cache ] = new Set();
			}
			otherPrecacheEntries[ entry.cache ].add( entry.url );
		}
	}

	if ( precacheEntries.length > 0 ) {
		wp.serviceWorker.precaching.precache( precacheEntries );
	}

	// @todo Should not these parameters be specific to each entry as opposed to all entries?
	// @todo Should not the strategy be tied to each entry as well?
	// @todo Use networkFirst instead of cacheFirst when WP_DEBUG.
	wp.serviceWorker.precaching.addRoute( {
		ignoreUrlParametersMatching: [
			/^utm_/,
			/^ver$/
		]
	} );

	for ( let [ cacheName, urls ] of Object.entries( otherPrecacheEntries ) ) {
		if ( 'runtime' === cacheName ) {
			cacheName = wp.serviceWorker.core.cacheNames.runtime;
		}
		self.addEventListener( 'install', event => {
			event.waitUntil(
				caches.open( cacheName ).then(
					cache => cache.addAll( urls.values() )
				)
			);
		});
	}
}
