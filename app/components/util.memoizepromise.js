define([
	'underscore'
], function(_) {
	return MemoizePromise;

	function MemoizePromise(fn, options) {
		options = options || {};
		var map = {};
		var stack = [];
		return memoized;

		function pushToMap(key, promise) {
			map[key] = promise;
			stack.push(key);
			if (options.limit && stack.length > options.limit) {
				//console.log('Memoized Reached LIMIT', options.limit, key);
				delete map[stack.shift()];
			}
		}

		function memoized(key) {
			var cacheKey = key;

			// Optionally force the key value.
			if (_.has(options, 'key'))
				cacheKey = options.key;

			// Optionally resolve the key value through a callback.
			else if (options.resolver)
				cacheKey = options.resolver.apply(this, arguments);

			// Returned a cached entry, if found.
			if (map[cacheKey]) {
				//console.log('Memoized Cached', options.label, key);
				return map[cacheKey];
			}

			var promise = fn.apply(this, arguments);
			//console.log('Memoized Ret Promise', options.label, key);

			promise.done(function() {
				//console.log('Memoized Push Resolved', options.label, key);
				pushToMap(cacheKey, promise);
			});

			if (options.cacheRejected) {
				promise.fail(function() {
					//console.log('Memoized Push Failed', options.label, key);
					pushToMap(cacheKey, promise);
				});
			}

			return promise;
		}
	}
});
