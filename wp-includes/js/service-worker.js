/* global workbox */

/**
 * Handle registering caching strategies.
 *
 * @todo Handle conflicts between routes.
 */

if ( ! self.wp ) {
	self.wp = {};
}

wp.serviceWorker = workbox;
