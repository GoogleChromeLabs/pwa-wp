this.workbox = this.workbox || {};
this.workbox.googleAnalytics = (function (exports,Plugin_mjs,cacheNames_mjs,Route_mjs,Router_mjs,NetworkFirst_mjs,NetworkOnly_mjs) {
  'use strict';

  try {
    self.workbox.v['workbox:google-analytics:4.0.0-alpha.0'] = 1;
  } catch (e) {} // eslint-disable-line

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  const QUEUE_NAME = 'workbox-google-analytics';
  const MAX_RETENTION_TIME = 60 * 48; // Two days in minutes

  const GOOGLE_ANALYTICS_HOST = 'www.google-analytics.com';
  const GTM_HOST = 'www.googletagmanager.com';
  const ANALYTICS_JS_PATH = '/analytics.js';
  const GTAG_JS_PATH = '/gtag/js';
  // endpoints. Most of the time the default path (/collect) is used, but
  // occasionally an experimental endpoint is used when testing new features,
  // (e.g. /r/collect or /j/collect)

  const COLLECT_PATHS_REGEX = /^\/(\w+\/)?collect/;

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  /**
   * Promisifies the FileReader API to await a text response from a Blob.
   *
   * @param {Blob} blob
   * @return {Promise<string>}
   *
   * @private
   */

  const getTextFromBlob = async blob => {
    // This usage of `return await new Promise...` is intentional to work around
    // a bug in the transpiled/minified output.
    // See https://github.com/GoogleChrome/workbox/issues/1186
    return await new Promise((resolve, reject) => {
      const reader = new FileReader();

      reader.onloadend = () => resolve(reader.result);

      reader.onerror = () => reject(reader.error);

      reader.readAsText(blob);
    });
  };
  /**
   * Creates the requestWillDequeue callback to be used with the background
   * sync queue plugin. The callback takes the failed request and adds the
   * `qt` param based on the current time, as well as applies any other
   * user-defined hit modifications.
   *
   * @param {Object} config See workbox.googleAnalytics.initialize.
   * @return {Function} The requestWillDequeu callback function.
   *
   * @private
   */


  const createRequestWillReplayCallback = config => {
    return async storableRequest => {
      let {
        url,
        requestInit,
        timestamp
      } = storableRequest;
      url = new URL(url); // Measurement protocol requests can set their payload parameters in either
      // the URL query string (for GET requests) or the POST body.

      let params;

      if (requestInit.body) {
        const payload = requestInit.body instanceof Blob ? await getTextFromBlob(requestInit.body) : requestInit.body;
        params = new URLSearchParams(payload);
      } else {
        params = url.searchParams;
      } // Calculate the qt param, accounting for the fact that an existing
      // qt param may be present and should be updated rather than replaced.


      const originalHitTime = timestamp - (Number(params.get('qt')) || 0);
      const queueTime = Date.now() - originalHitTime; // Set the qt param prior to applying the hitFilter or parameterOverrides.

      params.set('qt', queueTime);

      if (config.parameterOverrides) {
        for (const param of Object.keys(config.parameterOverrides)) {
          const value = config.parameterOverrides[param];
          params.set(param, value);
        }
      }

      if (typeof config.hitFilter === 'function') {
        config.hitFilter.call(null, params);
      }

      requestInit.body = params.toString();
      requestInit.method = 'POST';
      requestInit.mode = 'cors';
      requestInit.credentials = 'omit';
      requestInit.headers = {
        'Content-Type': 'text/plain'
      }; // Ignore URL search params as they're now in the post body.

      storableRequest.url = `${url.origin}${url.pathname}`;
    };
  };
  /**
   * Creates GET and POST routes to catch failed Measurement Protocol hits.
   *
   * @param {Plugin} queuePlugin
   * @return {Array<Route>} The created routes.
   *
   * @private
   */


  const createCollectRoutes = queuePlugin => {
    const match = ({
      url
    }) => url.hostname === GOOGLE_ANALYTICS_HOST && COLLECT_PATHS_REGEX.test(url.pathname);

    const handler = new NetworkOnly_mjs.NetworkOnly({
      plugins: [queuePlugin]
    });
    return [new Route_mjs.Route(match, handler, 'GET'), new Route_mjs.Route(match, handler, 'POST')];
  };
  /**
   * Creates a route with a network first strategy for the analytics.js script.
   *
   * @param {string} cacheName
   * @return {Route} The created route.
   *
   * @private
   */


  const createAnalyticsJsRoute = cacheName => {
    const match = ({
      url
    }) => url.hostname === GOOGLE_ANALYTICS_HOST && url.pathname === ANALYTICS_JS_PATH;

    const handler = new NetworkFirst_mjs.NetworkFirst({
      cacheName
    });
    return new Route_mjs.Route(match, handler, 'GET');
  };
  /**
   * Creates a route with a network first strategy for the gtag.js script.
   *
   * @param {string} cacheName
   * @return {Route} The created route.
   *
   * @private
   */


  const createGtagJsRoute = cacheName => {
    const match = ({
      url
    }) => url.hostname === GTM_HOST && url.pathname === GTAG_JS_PATH;

    const handler = new NetworkFirst_mjs.NetworkFirst({
      cacheName
    });
    return new Route_mjs.Route(match, handler, 'GET');
  };
  /**
   * @param {Object=} [options]
   * @param {Object} [options.cacheName] The cache name to store and retrieve
   *     analytics.js. Defaults to the cache names provided by `workbox-core`.
   * @param {Object} [options.parameterOverrides]
   *     [Measurement Protocol parameters](https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters),
   *     expressed as key/value pairs, to be added to replayed Google Analytics
   *     requests. This can be used to, e.g., set a custom dimension indicating
   *     that the request was replayed.
   * @param {Function} [options.hitFilter] A function that allows you to modify
   *     the hit parameters prior to replaying
   *     the hit. The function is invoked with the original hit's URLSearchParams
   *     object as its only argument.
   *
   * @memberof workbox.googleAnalytics
   */


  const initialize = (options = {}) => {
    const cacheName = cacheNames_mjs.cacheNames.getGoogleAnalyticsName(options.cacheName);
    const queuePlugin = new Plugin_mjs.Plugin(QUEUE_NAME, {
      maxRetentionTime: MAX_RETENTION_TIME,
      callbacks: {
        requestWillReplay: createRequestWillReplayCallback(options)
      }
    });
    const routes = [createAnalyticsJsRoute(cacheName), createGtagJsRoute(cacheName), ...createCollectRoutes(queuePlugin)];
    const router = new Router_mjs.Router();

    for (const route of routes) {
      router.registerRoute(route);
    }

    router.addFetchListener();
  };

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */

  exports.initialize = initialize;

  return exports;

}({},workbox.backgroundSync,workbox.core._private,workbox.routing,workbox.routing,workbox.strategies,workbox.strategies));

//# sourceMappingURL=workbox-offline-ga.dev.js.map
