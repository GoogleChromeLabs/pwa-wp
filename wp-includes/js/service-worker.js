/* global workbox */

/**
 * Handle registering caching strategies.
 *
 * @todo This can probably be moved away from separate JS file to PHP if there's no additional abstraction layer needed.
 */

if ( ! self.wp ) {
	self.wp = {};
}

wp.serviceWorker = workbox;
