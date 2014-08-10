define([
	'jquery'
], function($) {
	return function(options) {
		options = options || {};

		var responseCache = {
			limit: options.responseCacheLimit || 10,
			map: {},
			keys: []
		};

		var deferredCache = {
			limit: options.deferredCacheLimit || 10,
			map: {},
			keys: []
		};

		function pushToCache(cache, key, value) {
			if (!cache.limit)
				return;

			cache.map[key] = value;
			cache.keys.push(key);

			// Trim if over cache limit.
			if (cache.keys.length > cache.limit) {
				var firstKey = cache.keys.shift();
				delete cache.map[firstKey];
			}
		}

		return function(fileName) {
			if (!fileName && options.defaultFileName)
				fileName = options.defaultFileName;

			var cachedDeferred = deferredCache.map[fileName];
			if (cachedDeferred) {
				if (cachedDeferred.state() === 'pending')
					cachedDeferred._abortCount++;

				return cachedDeferred;
			}

			var app = this;
			var deferred = $.Deferred();
			var xhr;

			deferred._abortCount = 1;
			deferred.abort = function() {
				if ((--deferred._abortCount) === 0)
					xhr && xhr.abort();
			};

			pushToCache(deferredCache, fileName, deferred);

			var cachedResponse = responseCache.map[fileName];
			if (cachedResponse) {
				deferred.resolveWith(app, [ cachedResponse ]);
			}
			else if (app.settings) {
				xhr = $.ajax({
					dataType: 'json',
					url: app.urlRoot + '/' + fileName + app.suffix
				})
				.done(function(resp) {
					pushToCache(responseCache, fileName, resp);
					deferred.resolveWith(app, [ resp ]);
				})
				.fail(function(xhr, status, error) {
					if (status === 'abort') {
						delete deferredCache.map[fileName];
						deferred.rejectWith(app, [ 'ABORT' ]);
						return;
					}

					if (options.cacheFailed !== true)
						delete deferredCache.map[fileName];

					deferred.rejectWith(app, [ options.errorNotFound || 'NOT_FOUND', 'FETCH_FAIL', xhr, status, error ]);
				});
			}
			else {
				deferred.rejectWith(app, [ 'SETTINGS_NOT_LOADED' ]);
			}

			return deferred;
		};
	};
});
