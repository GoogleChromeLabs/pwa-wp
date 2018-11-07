this.workbox = this.workbox || {};
this.workbox.core = (function () {
  'use strict';

  try {
    self.workbox.v['workbox:core:4.0.0-beta.0'] = 1;
  } catch (e) {} // eslint-disable-line

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  /**
   * The available log levels in Workbox: debug, log, warn, error and silent.
   *
   * @property {int} debug Prints all logs from Workbox. Useful for debugging.
   * @property {int} log Prints console log, warn, error and groups. Default for
   * debug builds.
   * @property {int} warn Prints console warn, error and groups. Default for
   * non-debug builds.
   * @property {int} error Print console error and groups.
   * @property {int} silent Force no logging from Workbox.
   *
   * @alias workbox.core.LOG_LEVELS
   */

  var LOG_LEVELS = {
    debug: 0,
    log: 1,
    warn: 2,
    error: 3,
    silent: 4
  };

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  // Related bug: https://bugs.webkit.org/show_bug.cgi?id=182754

  const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
  const GREY = `#7f8c8d`;
  const GREEN = `#2ecc71`;
  const YELLOW = `#f39c12`;
  const RED = `#c0392b`;
  const BLUE = `#3498db`;

  const getDefaultLogLevel = () => LOG_LEVELS.log;

  let logLevel = getDefaultLogLevel();

  const shouldPrint = minLevel => logLevel <= minLevel;

  const setLoggerLevel = newLogLevel => logLevel = newLogLevel;

  const getLoggerLevel = () => logLevel; // We always want groups to be logged unless logLevel is silent.


  const groupLevel = LOG_LEVELS.error;

  const _print = function (keyName, logArgs, levelColor) {
    const logLevel = keyName.indexOf('group') === 0 ? groupLevel : LOG_LEVELS[keyName];

    if (!shouldPrint(logLevel)) {
      return;
    }

    if (!levelColor || keyName === 'groupCollapsed' && isSafari) {
      console[keyName](...logArgs);
      return;
    }

    const logPrefix = ['%cworkbox', `background: ${levelColor}; color: white; padding: 2px 0.5em; ` + `border-radius: 0.5em;`];
    console[keyName](...logPrefix, ...logArgs);
  };

  const groupEnd = () => {
    if (shouldPrint(groupLevel)) {
      console.groupEnd();
    }
  };

  const defaultExport = {
    groupEnd,
    unprefixed: {
      groupEnd
    }
  };

  const setupLogs = (keyName, color) => {
    defaultExport[keyName] = (...args) => _print(keyName, args, color);

    defaultExport.unprefixed[keyName] = (...args) => _print(keyName, args);
  };

  const levelToColor = {
    debug: GREY,
    log: GREEN,
    warn: YELLOW,
    error: RED,
    groupCollapsed: BLUE
  };
  Object.keys(levelToColor).forEach(keyName => setupLogs(keyName, levelToColor[keyName]));

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  var messages = {
    'invalid-value': ({
      paramName,
      validValueDescription,
      value
    }) => {
      if (!paramName || !validValueDescription) {
        throw new Error(`Unexpected input to 'invalid-value' error.`);
      }

      return `The '${paramName}' parameter was given a value with an ` + `unexpected value. ${validValueDescription} Received a value of ` + `${JSON.stringify(value)}.`;
    },
    'not-in-sw': ({
      moduleName
    }) => {
      if (!moduleName) {
        throw new Error(`Unexpected input to 'not-in-sw' error.`);
      }

      return `The '${moduleName}' must be used in a service worker.`;
    },
    'not-an-array': ({
      moduleName,
      className,
      funcName,
      paramName
    }) => {
      if (!moduleName || !className || !funcName || !paramName) {
        throw new Error(`Unexpected input to 'not-an-array' error.`);
      }

      return `The parameter '${paramName}' passed into ` + `'${moduleName}.${className}.${funcName}()' must be an array.`;
    },
    'incorrect-type': ({
      expectedType,
      paramName,
      moduleName,
      className,
      funcName
    }) => {
      if (!expectedType || !paramName || !moduleName || !funcName) {
        throw new Error(`Unexpected input to 'incorrect-type' error.`);
      }

      return `The parameter '${paramName}' passed into ` + `'${moduleName}.${className ? className + '.' : ''}` + `${funcName}()' must be of type ${expectedType}.`;
    },
    'incorrect-class': ({
      expectedClass,
      paramName,
      moduleName,
      className,
      funcName,
      isReturnValueProblem
    }) => {
      if (!expectedClass || !moduleName || !funcName) {
        throw new Error(`Unexpected input to 'incorrect-class' error.`);
      }

      if (isReturnValueProblem) {
        return `The return value from ` + `'${moduleName}.${className ? className + '.' : ''}${funcName}()' ` + `must be an instance of class ${expectedClass.name}.`;
      }

      return `The parameter '${paramName}' passed into ` + `'${moduleName}.${className ? className + '.' : ''}${funcName}()' ` + `must be an instance of class ${expectedClass.name}.`;
    },
    'missing-a-method': ({
      expectedMethod,
      paramName,
      moduleName,
      className,
      funcName
    }) => {
      if (!expectedMethod || !paramName || !moduleName || !className || !funcName) {
        throw new Error(`Unexpected input to 'missing-a-method' error.`);
      }

      return `${moduleName}.${className}.${funcName}() expected the ` + `'${paramName}' parameter to expose a '${expectedMethod}' method.`;
    },
    'add-to-cache-list-unexpected-type': ({
      entry
    }) => {
      return `An unexpected entry was passed to ` + `'workbox-precaching.PrecacheController.addToCacheList()' The entry ` + `'${JSON.stringify(entry)}' isn't supported. You must supply an array of ` + `strings with one or more characters, objects with a url property or ` + `Request objects.`;
    },
    'add-to-cache-list-conflicting-entries': ({
      firstEntry,
      secondEntry
    }) => {
      if (!firstEntry || !secondEntry) {
        throw new Error(`Unexpected input to ` + `'add-to-cache-list-duplicate-entries' error.`);
      }

      return `Two of the entries passed to ` + `'workbox-precaching.PrecacheController.addToCacheList()' had matching ` + `URLs but different revision details. This means workbox-precaching ` + `is unable to determine cache the asset correctly. Please remove one ` + `of the entries.`;
    },
    'plugin-error-request-will-fetch': ({
      thrownError
    }) => {
      if (!thrownError) {
        throw new Error(`Unexpected input to ` + `'plugin-error-request-will-fetch', error.`);
      }

      return `An error was thrown by a plugins 'requestWillFetch()' method. ` + `The thrown error message was: '${thrownError.message}'.`;
    },
    'invalid-cache-name': ({
      cacheNameId,
      value
    }) => {
      if (!cacheNameId) {
        throw new Error(`Expected a 'cacheNameId' for error 'invalid-cache-name'`);
      }

      return `You must provide a name containing at least one character for ` + `setCacheDeatils({${cacheNameId}: '...'}). Received a value of ` + `'${JSON.stringify(value)}'`;
    },
    'unregister-route-but-not-found-with-method': ({
      method
    }) => {
      if (!method) {
        throw new Error(`Unexpected input to ` + `'unregister-route-but-not-found-with-method' error.`);
      }

      return `The route you're trying to unregister was not  previously ` + `registered for the method type '${method}'.`;
    },
    'unregister-route-route-not-registered': () => {
      return `The route you're trying to unregister was not previously ` + `registered.`;
    },
    'queue-replay-failed': ({
      name
    }) => {
      return `Replaying the background sync queue '${name}' failed.`;
    },
    'duplicate-queue-name': ({
      name
    }) => {
      return `The Queue name '${name}' is already being used. ` + `All instances of backgroundSync.Queue must be given unique names.`;
    },
    'expired-test-without-max-age': ({
      methodName,
      paramName
    }) => {
      return `The '${methodName}()' method can only be used when the ` + `'${paramName}' is used in the constructor.`;
    },
    'unsupported-route-type': ({
      moduleName,
      className,
      funcName,
      paramName
    }) => {
      return `The supplied '${paramName}' parameter was an unsupported type. ` + `Please check the docs for ${moduleName}.${className}.${funcName} for ` + `valid input types.`;
    },
    'not-array-of-class': ({
      value,
      expectedClass,
      moduleName,
      className,
      funcName,
      paramName
    }) => {
      return `The supplied '${paramName}' parameter must be an array of ` + `'${expectedClass}' objects. Received '${JSON.stringify(value)},'. ` + `Please check the call to ${moduleName}.${className}.${funcName}() ` + `to fix the issue.`;
    },
    'max-entries-or-age-required': ({
      moduleName,
      className,
      funcName
    }) => {
      return `You must define either config.maxEntries or config.maxAgeSeconds` + `in ${moduleName}.${className}.${funcName}`;
    },
    'statuses-or-headers-required': ({
      moduleName,
      className,
      funcName
    }) => {
      return `You must define either config.statuses or config.headers` + `in ${moduleName}.${className}.${funcName}`;
    },
    'invalid-string': ({
      moduleName,
      className,
      funcName,
      paramName
    }) => {
      if (!paramName || !moduleName || !className || !funcName) {
        throw new Error(`Unexpected input to 'invalid-string' error.`);
      }

      return `When using strings, the '${paramName}' parameter must start with ` + `'http' (for cross-origin matches) or '/' (for same-origin matches). ` + `Please see the docs for ${moduleName}.${className}.${funcName}() for ` + `more info.`;
    },
    'channel-name-required': () => {
      return `You must provide a channelName to construct a ` + `BroadcastCacheUpdate instance.`;
    },
    'invalid-responses-are-same-args': () => {
      return `The arguments passed into responsesAreSame() appear to be ` + `invalid. Please ensure valid Responses are used.`;
    },
    'expire-custom-caches-only': () => {
      return `You must provide a 'cacheName' property when using the ` + `expiration plugin with a runtime caching strategy.`;
    },
    'unit-must-be-bytes': ({
      normalizedRangeHeader
    }) => {
      if (!normalizedRangeHeader) {
        throw new Error(`Unexpected input to 'unit-must-be-bytes' error.`);
      }

      return `The 'unit' portion of the Range header must be set to 'bytes'. ` + `The Range header provided was "${normalizedRangeHeader}"`;
    },
    'single-range-only': ({
      normalizedRangeHeader
    }) => {
      if (!normalizedRangeHeader) {
        throw new Error(`Unexpected input to 'single-range-only' error.`);
      }

      return `Multiple ranges are not supported. Please use a  single start ` + `value, and optional end value. The Range header provided was ` + `"${normalizedRangeHeader}"`;
    },
    'invalid-range-values': ({
      normalizedRangeHeader
    }) => {
      if (!normalizedRangeHeader) {
        throw new Error(`Unexpected input to 'invalid-range-values' error.`);
      }

      return `The Range header is missing both start and end values. At least ` + `one of those values is needed. The Range header provided was ` + `"${normalizedRangeHeader}"`;
    },
    'no-range-header': () => {
      return `No Range header was found in the Request provided.`;
    },
    'range-not-satisfiable': ({
      size,
      start,
      end
    }) => {
      return `The start (${start}) and end (${end}) values in the Range are ` + `not satisfiable by the cached response, which is ${size} bytes.`;
    },
    'attempt-to-cache-non-get-request': ({
      url,
      method
    }) => {
      return `Unable to cache '${url}' because it is a '${method}' request and ` + `only 'GET' requests can be cached.`;
    },
    'cache-put-with-no-response': ({
      url
    }) => {
      return `There was an attempt to cache '${url}' but the response was not ` + `defined.`;
    },
    'no-response': ({
      url,
      error
    }) => {
      let message = `The strategy could not generate a response for '${url}'.`;

      if (error) {
        message += ` The underlying error is ${error}.`;
      }

      return message;
    },
    'bad-precaching-response': ({
      url,
      status
    }) => {
      return `The precaching request for '${url}' failed with an HTTP ` + `status of ${status}.`;
    }
  };

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */

  const generatorFunction = (code, ...args) => {
    const message = messages[code];

    if (!message) {
      throw new Error(`Unable to find message for code '${code}'.`);
    }

    return message(...args);
  };

  const exportedValue = generatorFunction;

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  /**
   * Workbox errors should be thrown with this class.
   * This allows use to ensure the type easily in tests,
   * helps developers identify errors from workbox
   * easily and allows use to optimise error
   * messages correctly.
   *
   * @private
   */

  class WorkboxError extends Error {
    /**
     *
     * @param {string} errorCode The error code that
     * identifies this particular error.
     * @param {Object=} details Any relevant arguments
     * that will help developers identify issues should
     * be added as a key on the context object.
     */
    constructor(errorCode, details) {
      let message = exportedValue(errorCode, details);
      super(message);
      this.name = errorCode;
      this.details = details;
    }

  }

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  /*
   * This method returns true if the current context is a service worker.
   */

  const isSwEnv = moduleName => {
    if (!('ServiceWorkerGlobalScope' in self)) {
      throw new WorkboxError('not-in-sw', {
        moduleName
      });
    }
  };
  /*
   * This method throws if the supplied value is not an array.
   * The destructed values are required to produce a meaningful error for users.
   * The destructed and restructured object is so it's clear what is
   * needed.
   */


  const isArray = (value, {
    moduleName,
    className,
    funcName,
    paramName
  }) => {
    if (!Array.isArray(value)) {
      throw new WorkboxError('not-an-array', {
        moduleName,
        className,
        funcName,
        paramName
      });
    }
  };

  const hasMethod = (object, expectedMethod, {
    moduleName,
    className,
    funcName,
    paramName
  }) => {
    const type = typeof object[expectedMethod];

    if (type !== 'function') {
      throw new WorkboxError('missing-a-method', {
        paramName,
        expectedMethod,
        moduleName,
        className,
        funcName
      });
    }
  };

  const isType = (object, expectedType, {
    moduleName,
    className,
    funcName,
    paramName
  }) => {
    if (typeof object !== expectedType) {
      throw new WorkboxError('incorrect-type', {
        paramName,
        expectedType,
        moduleName,
        className,
        funcName
      });
    }
  };

  const isInstance = (object, expectedClass, {
    moduleName,
    className,
    funcName,
    paramName,
    isReturnValueProblem
  }) => {
    if (!(object instanceof expectedClass)) {
      throw new WorkboxError('incorrect-class', {
        paramName,
        expectedClass,
        moduleName,
        className,
        funcName,
        isReturnValueProblem
      });
    }
  };

  const isOneOf = (value, validValues, {
    paramName
  }) => {
    if (!validValues.includes(value)) {
      throw new WorkboxError('invalid-value', {
        paramName,
        value,
        validValueDescription: `Valid values are ${JSON.stringify(validValues)}.`
      });
    }
  };

  const isArrayOfClass = (value, expectedClass, {
    moduleName,
    className,
    funcName,
    paramName
  }) => {
    const error = new WorkboxError('not-array-of-class', {
      value,
      expectedClass,
      moduleName,
      className,
      funcName,
      paramName
    });

    if (!Array.isArray(value)) {
      throw error;
    }

    for (let item of value) {
      if (!(item instanceof expectedClass)) {
        throw error;
      }
    }
  };

  const finalAssertExports = {
    hasMethod,
    isArray,
    isInstance,
    isOneOf,
    isSwEnv,
    isType,
    isArrayOfClass
  };

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  const callbacks = new Set();
  /**
   * Adds a function to the set of callbacks that will be executed when there's
   * a quota error.
   *
   * @param {Function} callback
   * @memberof workbox.core
   */

  function registerQuotaErrorCallback(callback) {
    {
      finalAssertExports.isType(callback, 'function', {
        moduleName: 'workbox-core',
        funcName: 'register',
        paramName: 'callback'
      });
    }

    callbacks.add(callback);

    {
      defaultExport.log('Registered a callback to respond to quota errors.', callback);
    }
  }
  /**
   * Runs all of the callback functions, one at a time sequentially, in the order
   * in which they were registered.
   *
   * @memberof workbox.core
   * @private
   */


  async function executeQuotaErrorCallbacks() {
    {
      defaultExport.log(`About to run ${callbacks.size} callbacks to clean up caches.`);
    }

    for (const callback of callbacks) {
      await callback();

      {
        defaultExport.log(callback, 'is complete.');
      }
    }

    {
      defaultExport.log('Finished running callbacks.');
    }
  }

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  /**
   * A class that wraps common IndexedDB functionality in a promise-based API.
   * It exposes all the underlying power and functionality of IndexedDB, but
   * wraps the most commonly used features in a way that's much simpler to use.
   *
   * @private
   */

  class DBWrapper {
    /**
     * @param {string} name
     * @param {number} version
     * @param {Object=} [callback]
     * @param {!Function} [callbacks.onupgradeneeded]
     * @param {!Function} [callbacks.onversionchange] Defaults to
     *     DBWrapper.prototype._onversionchange when not specified.
     */
    constructor(name, version, {
      onupgradeneeded,
      onversionchange = this._onversionchange
    } = {}) {
      this._name = name;
      this._version = version;
      this._onupgradeneeded = onupgradeneeded;
      this._onversionchange = onversionchange; // If this is null, it means the database isn't open.

      this._db = null;
    }
    /**
     * Returns the IDBDatabase instance (not normally needed).
     */


    get db() {
      return this._db;
    }
    /**
     * Opens a connected to an IDBDatabase, invokes any onupgradedneeded
     * callback, and added an onversionchange callback to the database.
     *
     * @return {IDBDatabase}
     */


    async open() {
      if (this._db) return;
      this._db = await new Promise((resolve, reject) => {
        // This flag is flipped to true if the timeout callback runs prior
        // to the request failing or succeeding. Note: we use a timeout instead
        // of an onblocked handler since there are cases where onblocked will
        // never never run. A timeout better handles all possible scenarios:
        // https://github.com/w3c/IndexedDB/issues/223
        let openRequestTimedOut = false;
        setTimeout(() => {
          openRequestTimedOut = true;
          reject(new Error('The open request was blocked and timed out'));
        }, this.OPEN_TIMEOUT);
        const openRequest = indexedDB.open(this._name, this._version);

        openRequest.onerror = () => reject(openRequest.error);

        openRequest.onupgradeneeded = evt => {
          if (openRequestTimedOut) {
            openRequest.transaction.abort();
            evt.target.result.close();
          } else if (this._onupgradeneeded) {
            this._onupgradeneeded(evt);
          }
        };

        openRequest.onsuccess = ({
          target
        }) => {
          const db = target.result;

          if (openRequestTimedOut) {
            db.close();
          } else {
            db.onversionchange = this._onversionchange;
            resolve(db);
          }
        };
      });
      return this;
    }
    /**
     * Polyfills the native `getKey()` method. Note, this is overridden at
     * runtime if the browser supports the native method.
     *
     * @param {string} storeName
     * @param {*} query
     * @return {Array}
     */


    async getKey(storeName, query) {
      return (await this.getAllKeys(storeName, query, 1))[0];
    }
    /**
     * Polyfills the native `getAll()` method. Note, this is overridden at
     * runtime if the browser supports the native method.
     *
     * @param {string} storeName
     * @param {*} query
     * @param {number} count
     * @return {Array}
     */


    async getAll(storeName, query, count) {
      return await this.getAllMatching(storeName, {
        query,
        count
      });
    }
    /**
     * Polyfills the native `getAllKeys()` method. Note, this is overridden at
     * runtime if the browser supports the native method.
     *
     * @param {string} storeName
     * @param {*} query
     * @param {number} count
     * @return {Array}
     */


    async getAllKeys(storeName, query, count) {
      return (await this.getAllMatching(storeName, {
        query,
        count,
        includeKeys: true
      })).map(({
        key
      }) => key);
    }
    /**
     * Supports flexible lookup in an object store by specifying an index,
     * query, direction, and count. This method returns an array of objects
     * with the signature .
     *
     * @param {string} storeName
     * @param {Object} [opts]
     * @param {string} [opts.index] The index to use (if specified).
     * @param {*} [opts.query]
     * @param {IDBCursorDirection} [opts.direction]
     * @param {number} [opts.count] The max number of results to return.
     * @param {boolean} [opts.includeKeys] When true, the structure of the
     *     returned objects is changed from an array of values to an array of
     *     objects in the form {key, primaryKey, value}.
     * @return {Array}
     */


    async getAllMatching(storeName, {
      index,
      query = null,
      // IE errors if query === `undefined`.
      direction = 'next',
      count,
      includeKeys
    } = {}) {
      return await this.transaction([storeName], 'readonly', (txn, done) => {
        const store = txn.objectStore(storeName);
        const target = index ? store.index(index) : store;
        const results = [];

        target.openCursor(query, direction).onsuccess = ({
          target
        }) => {
          const cursor = target.result;

          if (cursor) {
            const {
              primaryKey,
              key,
              value
            } = cursor;
            results.push(includeKeys ? {
              primaryKey,
              key,
              value
            } : value);

            if (count && results.length >= count) {
              done(results);
            } else {
              cursor.continue();
            }
          } else {
            done(results);
          }
        };
      });
    }
    /**
     * Accepts a list of stores, a transaction type, and a callback and
     * performs a transaction. A promise is returned that resolves to whatever
     * value the callback chooses. The callback holds all the transaction logic
     * and is invoked with two arguments:
     *   1. The IDBTransaction object
     *   2. A `done` function, that's used to resolve the promise when
     *      when the transaction is done, if passed a value, the promise is
     *      resolved to that value.
     *
     * @param {Array<string>} storeNames An array of object store names
     *     involved in the transaction.
     * @param {string} type Can be `readonly` or `readwrite`.
     * @param {!Function} callback
     * @return {*} The result of the transaction ran by the callback.
     */


    async transaction(storeNames, type, callback) {
      await this.open();
      return await new Promise((resolve, reject) => {
        const txn = this._db.transaction(storeNames, type);

        txn.onabort = ({
          target
        }) => reject(target.error);

        txn.oncomplete = () => resolve();

        callback(txn, value => resolve(value));
      });
    }
    /**
     * Delegates async to a native IDBObjectStore method.
     *
     * @param {string} method The method name.
     * @param {string} storeName The object store name.
     * @param {string} type Can be `readonly` or `readwrite`.
     * @param {...*} args The list of args to pass to the native method.
     * @return {*} The result of the transaction.
     */


    async _call(method, storeName, type, ...args) {
      const callback = (txn, done) => {
        txn.objectStore(storeName)[method](...args).onsuccess = ({
          target
        }) => {
          done(target.result);
        };
      };

      return await this.transaction([storeName], type, callback);
    }
    /**
     * The default onversionchange handler, which closes the database so other
     * connections can open without being blocked.
     */


    _onversionchange() {
      this.close();
    }
    /**
     * Closes the connection opened by `DBWrapper.open()`. Generally this method
     * doesn't need to be called since:
     *   1. It's usually better to keep a connection open since opening
     *      a new connection is somewhat slow.
     *   2. Connections are automatically closed when the reference is
     *      garbage collected.
     * The primary use case for needing to close a connection is when another
     * reference (typically in another tab) needs to upgrade it and would be
     * blocked by the current, open connection.
     */


    close() {
      if (this._db) this._db.close();
    }

  } // Exposed to let users modify the default timeout on a per-instance
  // or global basis.

  DBWrapper.prototype.OPEN_TIMEOUT = 2000; // Wrap native IDBObjectStore methods according to their mode.

  const methodsToWrap = {
    'readonly': ['get', 'count', 'getKey', 'getAll', 'getAllKeys'],
    'readwrite': ['add', 'put', 'clear', 'delete']
  };

  for (const [mode, methods] of Object.entries(methodsToWrap)) {
    for (const method of methods) {
      if (method in IDBObjectStore.prototype) {
        // Don't use arrow functions here since we're outside of the class.
        DBWrapper.prototype[method] = async function (storeName, ...args) {
          return await this._call(method, storeName, mode, ...args);
        };
      }
    }
  }

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  /**
   * Deletes the database.
   * Note: this is exported separately from the DBWrapper module because most
   * usages of IndexedDB in workbox dont need deleting, and this way it can be
   * reused in tests to delete databases without creating DBWrapper instances.
   *
   * @param {string} name The database name.
   * @private
   */

  const deleteDatabase = async name => {
    await new Promise((resolve, reject) => {
      const request = indexedDB.deleteDatabase(name);

      request.onerror = ({
        target
      }) => {
        reject(target.error);
      };

      request.onblocked = () => {
        reject(new Error('Delete blocked'));
      };

      request.onsuccess = () => {
        resolve();
      };
    });
  };

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  /**
   * Handles running a series of migration functions to upgrade an IndexedDB
   * database in a `versionchange` event.
   *
   * @private
   * @param {IDBVersionChangeEvent} event
   * @param {Object<string, Function>} migrationFunctions
   */

  const migrateDb = (event, migrationFunctions) => {
    let {
      oldVersion,
      newVersion
    } = event; // Call all `migrationFunctions` values between oldVersion and newVersion.

    const migrate = oldVersion => {
      const next = () => {
        ++oldVersion;

        if (oldVersion <= newVersion) {
          migrate(oldVersion);
        }
      };

      const migrationFunction = migrationFunctions[`v${oldVersion}`];

      if (typeof migrationFunction === 'function') {
        migrationFunction(next);
      } else {
        next();
      }
    };

    migrate(oldVersion);
  };

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  const _cacheNameDetails = {
    prefix: 'workbox',
    suffix: self.registration.scope,
    googleAnalytics: 'googleAnalytics',
    precache: 'precache',
    runtime: 'runtime'
  };

  const _createCacheName = cacheName => {
    return [_cacheNameDetails.prefix, cacheName, _cacheNameDetails.suffix].filter(value => value.length > 0).join('-');
  };

  const cacheNames = {
    updateDetails: details => {
      Object.keys(_cacheNameDetails).forEach(key => {
        if (typeof details[key] !== 'undefined') {
          _cacheNameDetails[key] = details[key];
        }
      });
    },
    getGoogleAnalyticsName: userCacheName => {
      return userCacheName || _createCacheName(_cacheNameDetails.googleAnalytics);
    },
    getPrecacheName: userCacheName => {
      return userCacheName || _createCacheName(_cacheNameDetails.precache);
    },
    getRuntimeName: userCacheName => {
      return userCacheName || _createCacheName(_cacheNameDetails.runtime);
    }
  };

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  var pluginEvents = {
    CACHE_DID_UPDATE: 'cacheDidUpdate',
    CACHE_WILL_UPDATE: 'cacheWillUpdate',
    CACHED_RESPONSE_WILL_BE_USED: 'cachedResponseWillBeUsed',
    FETCH_DID_FAIL: 'fetchDidFail',
    REQUEST_WILL_FETCH: 'requestWillFetch'
  };

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  var pluginUtils = {
    filter: (plugins, callbackname) => {
      return plugins.filter(plugin => callbackname in plugin);
    }
  };

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */

  const getFriendlyURL = url => {
    const urlObj = new URL(url, location);

    if (urlObj.origin === location.origin) {
      return urlObj.pathname;
    }

    return urlObj.href;
  };

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  /**
   * Wrapper around cache.put().
   *
   * Will call `cacheDidUpdate` on plugins if the cache was updated.
   *
   * @param {Object} options
   * @param {string} options.cacheName
   * @param {Request} options.request
   * @param {Response} options.response
   * @param {Event} [options.event]
   * @param {Array<Object>} [options.plugins=[]]
   *
   * @private
   * @memberof module:workbox-core
   */

  const putWrapper = async ({
    cacheName,
    request,
    response,
    event,
    plugins = []
  } = {}) => {
    if (!response) {
      {
        defaultExport.error(`Cannot cache non-existent response for ` + `'${getFriendlyURL(request.url)}'.`);
      }

      throw new WorkboxError('cache-put-with-no-response', {
        url: getFriendlyURL(request.url)
      });
    }

    let responseToCache = await _isResponseSafeToCache({
      request,
      response,
      event,
      plugins
    });

    if (!responseToCache) {
      {
        defaultExport.debug(`Response '${getFriendlyURL(request.url)}' will not be ` + `cached.`, responseToCache);
      }

      return;
    }

    {
      if (responseToCache.method && responseToCache.method !== 'GET') {
        throw new WorkboxError('attempt-to-cache-non-get-request', {
          url: getFriendlyURL(request.url),
          method: responseToCache.method
        });
      }
    }

    const cache = await caches.open(cacheName);
    const updatePlugins = pluginUtils.filter(plugins, pluginEvents.CACHE_DID_UPDATE);
    let oldResponse = updatePlugins.length > 0 ? await matchWrapper({
      cacheName,
      request
    }) : null;

    {
      defaultExport.debug(`Updating the '${cacheName}' cache with a new Response for ` + `${getFriendlyURL(request.url)}.`);
    }

    try {
      await cache.put(request, responseToCache);
    } catch (error) {
      // See https://developer.mozilla.org/en-US/docs/Web/API/DOMException#exception-QuotaExceededError
      if (error.name === 'QuotaExceededError') {
        await executeQuotaErrorCallbacks();
      }

      throw error;
    }

    for (let plugin of updatePlugins) {
      await plugin[pluginEvents.CACHE_DID_UPDATE].call(plugin, {
        cacheName,
        request,
        event,
        oldResponse,
        newResponse: responseToCache
      });
    }
  };
  /**
   * This is a wrapper around cache.match().
   *
   * @param {Object} options
   * @param {string} options.cacheName Name of the cache to match against.
   * @param {Request} options.request The Request that will be used to look up
   *     cache entries.
   * @param {Event} [options.event] The event that propted the action.
   * @param {Object} [options.matchOptions] Options passed to cache.match().
   * @param {Array<Object>} [options.plugins=[]] Array of plugins.
   * @return {Response} A cached response if available.
   *
   * @private
   * @memberof module:workbox-core
   */


  const matchWrapper = async ({
    cacheName,
    request,
    event,
    matchOptions,
    plugins = []
  }) => {
    const cache = await caches.open(cacheName);
    let cachedResponse = await cache.match(request, matchOptions);

    {
      if (cachedResponse) {
        defaultExport.debug(`Found a cached response in '${cacheName}'.`);
      } else {
        defaultExport.debug(`No cached response found in '${cacheName}'.`);
      }
    }

    for (let plugin of plugins) {
      if (pluginEvents.CACHED_RESPONSE_WILL_BE_USED in plugin) {
        cachedResponse = await plugin[pluginEvents.CACHED_RESPONSE_WILL_BE_USED].call(plugin, {
          cacheName,
          request,
          event,
          matchOptions,
          cachedResponse
        });

        {
          if (cachedResponse) {
            finalAssertExports.isInstance(cachedResponse, Response, {
              moduleName: 'Plugin',
              funcName: pluginEvents.CACHED_RESPONSE_WILL_BE_USED,
              isReturnValueProblem: true
            });
          }
        }
      }
    }

    return cachedResponse;
  };
  /**
   * This method will call cacheWillUpdate on the available plugins (or use
   * response.ok) to determine if the Response is safe and valid to cache.
   *
   * @param {Object} options
   * @param {Request} options.request
   * @param {Response} options.response
   * @param {Event} [options.event]
   * @param {Array<Object>} [options.plugins=[]]
   * @return {Promise<Response>}
   *
   * @private
   * @memberof module:workbox-core
   */


  const _isResponseSafeToCache = async ({
    request,
    response,
    event,
    plugins
  }) => {
    let responseToCache = response;
    let pluginsUsed = false;

    for (let plugin of plugins) {
      if (pluginEvents.CACHE_WILL_UPDATE in plugin) {
        pluginsUsed = true;
        responseToCache = await plugin[pluginEvents.CACHE_WILL_UPDATE].call(plugin, {
          request,
          response: responseToCache,
          event
        });

        {
          if (responseToCache) {
            finalAssertExports.isInstance(responseToCache, Response, {
              moduleName: 'Plugin',
              funcName: pluginEvents.CACHE_WILL_UPDATE,
              isReturnValueProblem: true
            });
          }
        }

        if (!responseToCache) {
          break;
        }
      }
    }

    if (!pluginsUsed) {
      {
        if (!responseToCache.ok) {
          if (responseToCache.status === 0) {
            defaultExport.warn(`The response for '${request.url}' is an opaque ` + `response. The caching strategy that you're using will not ` + `cache opaque responses by default.`);
          } else {
            defaultExport.debug(`The response for '${request.url}' returned ` + `a status code of '${response.status}' and won't be cached as a ` + `result.`);
          }
        }
      }

      responseToCache = responseToCache.ok ? responseToCache : null;
    }

    return responseToCache ? responseToCache : null;
  };

  const cacheWrapper = {
    put: putWrapper,
    match: matchWrapper
  };

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  /**
   * Wrapper around the fetch API.
   *
   * Will call requestWillFetch on available plugins.
   *
   * @param {Object} options
   * @param {Request|string} options.request
   * @param {Object} [options.fetchOptions]
   * @param {Event} [options.event]
   * @param {Array<Object>} [options.plugins=[]]
   * @return {Promise<Response>}
   *
   * @private
   * @memberof module:workbox-core
   */

  const wrappedFetch = async ({
    request,
    fetchOptions,
    event,
    plugins = []
  }) => {
    // We *should* be able to call `await event.preloadResponse` even if it's
    // undefined, but for some reason, doing so leads to errors in our Node unit
    // tests. To work around that, explicitly check preloadResponse's value first.
    if (event && event.preloadResponse) {
      const possiblePreloadResponse = await event.preloadResponse;

      if (possiblePreloadResponse) {
        {
          defaultExport.log(`Using a preloaded navigation response for ` + `'${getFriendlyURL(request.url)}'`);
        }

        return possiblePreloadResponse;
      }
    }

    if (typeof request === 'string') {
      request = new Request(request);
    }

    {
      finalAssertExports.isInstance(request, Request, {
        paramName: request,
        expectedClass: 'Request',
        moduleName: 'workbox-core',
        className: 'fetchWrapper',
        funcName: 'wrappedFetch'
      });
    }

    const failedFetchPlugins = pluginUtils.filter(plugins, pluginEvents.FETCH_DID_FAIL); // If there is a fetchDidFail plugin, we need to save a clone of the
    // original request before it's either modified by a requestWillFetch
    // plugin or before the original request's body is consumed via fetch().

    const originalRequest = failedFetchPlugins.length > 0 ? request.clone() : null;

    try {
      for (let plugin of plugins) {
        if (pluginEvents.REQUEST_WILL_FETCH in plugin) {
          request = await plugin[pluginEvents.REQUEST_WILL_FETCH].call(plugin, {
            request: request.clone(),
            event
          });

          {
            if (request) {
              finalAssertExports.isInstance(request, Request, {
                moduleName: 'Plugin',
                funcName: pluginEvents.CACHED_RESPONSE_WILL_BE_USED,
                isReturnValueProblem: true
              });
            }
          }
        }
      }
    } catch (err) {
      throw new WorkboxError('plugin-error-request-will-fetch', {
        thrownError: err
      });
    } // The request can be altered by plugins with `requestWillFetch` making
    // the original request (Most likely from a `fetch` event) to be different
    // to the Request we make. Pass both to `fetchDidFail` to aid debugging.


    const pluginFilteredRequest = request.clone();

    try {
      const fetchResponse = await fetch(request, fetchOptions);

      {
        defaultExport.debug(`Network request for ` + `'${getFriendlyURL(request.url)}' returned a response with ` + `status '${fetchResponse.status}'.`);
      }

      return fetchResponse;
    } catch (error) {
      {
        defaultExport.error(`Network request for ` + `'${getFriendlyURL(request.url)}' threw an error.`, error);
      }

      for (let plugin of failedFetchPlugins) {
        await plugin[pluginEvents.FETCH_DID_FAIL].call(plugin, {
          error,
          event,
          originalRequest: originalRequest.clone(),
          request: pluginFilteredRequest.clone()
        });
      }

      throw error;
    }
  };

  const fetchWrapper = {
    fetch: wrappedFetch
  };

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */

  var _private = /*#__PURE__*/Object.freeze({
    DBWrapper: DBWrapper,
    deleteDatabase: deleteDatabase,
    migrateDb: migrateDb,
    WorkboxError: WorkboxError,
    assert: finalAssertExports,
    cacheNames: cacheNames,
    cacheWrapper: cacheWrapper,
    fetchWrapper: fetchWrapper,
    getFriendlyURL: getFriendlyURL,
    logger: defaultExport
  });

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  /**
   * Logs a warning to the user recommending changing
   * to max-age=0 or no-cache.
   *
   * @param {string} cacheControlHeader
   *
   * @private
   */

  function showWarning(cacheControlHeader) {
    const docsUrl = 'https://developers.google.com/web/tools/workbox/guides/service-worker-checklist#cache-control_of_your_service_worker_file';
    defaultExport.warn(`You are setting a 'cache-control' header of ` + `'${cacheControlHeader}' on your service worker file. This should be ` + `set to 'max-age=0' or 'no-cache' to ensure the latest service worker ` + `is served to your users. Learn more here: ${docsUrl}`);
  }
  /**
   * Checks for cache-control header on SW file and
   * warns the developer if it exists with a value
   * other than max-age=0 or no-cache.
   *
   * @return {Promise}
   * @private
   */


  function checkSWFileCacheHeaders() {
    // This is wrapped as an iife to allow async/await while making
    //  rollup exclude it in builds.
    return (async () => {
      try {
        const swFile = self.location.href;
        const response = await fetch(swFile);

        if (!response.ok) {
          // Response failed so nothing we can check;
          return;
        }

        if (!response.headers.has('cache-control')) {
          // No cache control header.
          return;
        }

        const cacheControlHeader = response.headers.get('cache-control');
        const maxAgeResult = /max-age\s*=\s*(\d*)/g.exec(cacheControlHeader);

        if (maxAgeResult) {
          if (parseInt(maxAgeResult[1], 10) === 0) {
            return;
          }
        }

        if (cacheControlHeader.indexOf('no-cache') !== -1) {
          return;
        }

        if (cacheControlHeader.indexOf('no-store') !== -1) {
          return;
        }

        showWarning(cacheControlHeader);
      } catch (err) {// NOOP
      }
    })();
  }

  const finalCheckSWFileCacheHeaders = checkSWFileCacheHeaders;

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  /**
   * This class is never exposed publicly. Inidividual methods are exposed
   * using jsdoc alias commands.
   *
   * @memberof workbox.core
   * @private
   */

  class WorkboxCore {
    /**
     * You should not instantiate this object directly.
     *
     * @private
     */
    constructor() {
      // Give our version strings something to hang off of.
      try {
        self.workbox.v = self.workbox.v || {};
      } catch (err) {} // NOOP
      // A WorkboxCore instance must be exported before we can use the logger.
      // This is so it can get the current log level.


      {
        const padding = '   ';
        defaultExport.groupCollapsed('Welcome to Workbox!');
        defaultExport.unprefixed.log(`You are currently using a development build. ` + `By default this will switch to prod builds when not on localhost. ` + `You can force this with workbox.setConfig({debug: true|false}).`);
        defaultExport.unprefixed.log(`📖 Read the guides and documentation\n` + `${padding}https://developers.google.com/web/tools/workbox/`);
        defaultExport.unprefixed.log(`❓ Use the [workbox] tag on Stack Overflow to ask questions\n` + `${padding}https://stackoverflow.com/questions/ask?tags=workbox`);
        defaultExport.unprefixed.log(`🐛 Found a bug? Report it on GitHub\n` + `${padding}https://github.com/GoogleChrome/workbox/issues/new`);
        defaultExport.groupEnd();

        if (typeof finalCheckSWFileCacheHeaders === 'function') {
          finalCheckSWFileCacheHeaders();
        }
      }
    }
    /**
     * Get the current cache names used by Workbox.
     *
     * `cacheNames.precache` is used for precached assets,
     * `cacheNames.googleAnalytics` is used by `workbox-google-analytics` to
     * store `analytics.js`,
     * and `cacheNames.runtime` is used for everything else.
     *
     * @return {Object} An object with `precache` and `runtime` cache names.
     *
     * @alias workbox.core.cacheNames
     */


    get cacheNames() {
      return {
        googleAnalytics: cacheNames.getGoogleAnalyticsName(),
        precache: cacheNames.getPrecacheName(),
        runtime: cacheNames.getRuntimeName()
      };
    }
    /**
     * You can alter the default cache names used by the Workbox modules by
     * changing the cache name details.
     *
     * Cache names are generated as `<prefix>-<Cache Name>-<suffix>`.
     *
     * @param {Object} details
     * @param {Object} details.prefix The string to add to the beginning of
     * the precache and runtime cache names.
     * @param {Object} details.suffix The string to add to the end of
     * the precache and runtime cache names.
     * @param {Object} details.precache The cache name to use for precache
     * caching.
     * @param {Object} details.runtime The cache name to use for runtime caching.
     * @param {Object} details.googleAnalytics The cache name to use for
     * `workbox-google-analytics` caching.
     *
     * @alias workbox.core.setCacheNameDetails
     */


    setCacheNameDetails(details) {
      {
        Object.keys(details).forEach(key => {
          finalAssertExports.isType(details[key], 'string', {
            moduleName: 'workbox-core',
            className: 'WorkboxCore',
            funcName: 'setCacheNameDetails',
            paramName: `details.${key}`
          });
        });

        if ('precache' in details && details.precache.length === 0) {
          throw new WorkboxError('invalid-cache-name', {
            cacheNameId: 'precache',
            value: details.precache
          });
        }

        if ('runtime' in details && details.runtime.length === 0) {
          throw new WorkboxError('invalid-cache-name', {
            cacheNameId: 'runtime',
            value: details.runtime
          });
        }

        if ('googleAnalytics' in details && details.googleAnalytics.length === 0) {
          throw new WorkboxError('invalid-cache-name', {
            cacheNameId: 'googleAnalytics',
            value: details.googleAnalytics
          });
        }
      }

      cacheNames.updateDetails(details);
    }
    /**
     * Get the current log level.
     *
     * @return {number}.
     *
     * @alias workbox.core.logLevel
     */


    get logLevel() {
      return getLoggerLevel();
    }
    /**
     * Set the current log level passing in one of the values from
     * [LOG_LEVELS]{@link module:workbox-core.LOG_LEVELS}.
     *
     * @param {number} newLevel The new log level to use.
     *
     * @alias workbox.core.setLogLevel
     */


    setLogLevel(newLevel) {
      {
        finalAssertExports.isType(newLevel, 'number', {
          moduleName: 'workbox-core',
          className: 'WorkboxCore',
          funcName: 'logLevel [setter]',
          paramName: `logLevel`
        });
      }

      if (newLevel > LOG_LEVELS.silent || newLevel < LOG_LEVELS.debug) {
        throw new WorkboxError('invalid-value', {
          paramName: 'logLevel',
          validValueDescription: `Please use a value from LOG_LEVELS, i.e ` + `'logLevel = workbox.core.LOG_LEVELS.debug'.`,
          value: newLevel
        });
      }

      setLoggerLevel(newLevel);
    }

  }

  var defaultExport$1 = new WorkboxCore();

  /*
    Copyright 2018 Google LLC

    Use of this source code is governed by an MIT-style
    license that can be found in the LICENSE file or at
    https://opensource.org/licenses/MIT.
  */
  const finalExports = Object.assign(defaultExport$1, {
    _private,
    LOG_LEVELS,
    registerQuotaErrorCallback
  });

  return finalExports;

}());

//# sourceMappingURL=workbox-core.dev.js.map
