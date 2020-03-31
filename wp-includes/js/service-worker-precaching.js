/* global PRECACHE_ENTRIES */

// IIFE is used for lexical scoping instead of just a braces block due to bug in Safari.
(() => {
	wp.serviceWorker.precaching.precache(PRECACHE_ENTRIES);

	// @todo Should not these parameters be specific to each entry as opposed to all entries?
	// @todo Should not the strategy be tied to each entry as well?
	// @todo Use networkFirst instead of cacheFirst when WP_DEBUG.
	wp.serviceWorker.precaching.addRoute({
		ignoreUrlParametersMatching: [/^utm_/, /^wp-mce-/, /^ver$/],
		// @todo Add urlManipulation which allows for the list of ignoreUrlParametersMatching to be supplied with each entry.
	});
})();
