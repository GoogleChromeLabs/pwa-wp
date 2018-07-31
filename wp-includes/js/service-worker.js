/* global workbox */

/**
 * Handle registering caching strategies.
 */

// @todo Perhaps some other variable makes more sense?
var wp = {};

wp.serviceWorker = {

	/**
	 * Add caching strategy. Missing processing conflicting routes.
	 *
	 * @param {string}      route Route.
	 * @param {string}      strategy Strategy.
	 * @param {string|null} cacheName Cache name.
	 * @param {int}         maxAge Max Age.
	 * @param {int}         maxEntries Max entries.
	 */
	addCachingStrategy: function ( route, strategy, cacheName, maxAge, maxEntries ) {
		var args = {};

		// @todo Logic for detecting conflicts.
		if ( cacheName ) {
			args.cacheName = cacheName;
		}

		if ( ! maxAge ) {
			maxAge = 0;
		}
		if ( ! maxEntries ) {
			maxEntries = 100;
		}
		args.plugins = [
			new workbox.expiration.Plugin({
				maxAgeSeconds: maxAge,
				maxEntries: maxEntries
			})
		];

		// @todo Missing other strategies.
		switch ( strategy ) {
			case 'staleWhileRevalidate':
				workbox.routing.registerRoute(
					new RegExp( route ),
					workbox.strategies.staleWhileRevalidate( args )
				);
				break;
		}
	}
};
