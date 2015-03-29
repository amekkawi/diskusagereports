define([
	'underscore',
	'jquery',
	'components/util.memoizepromise'
], function(_, $, MemoizePromise) {

	GetMapEntry.Build = Build;
	return GetMapEntry;

	/**
	 * Build a GetMapEntry WREQR request with preset options.
	 *
	 * @param {Object} [options]
	 * @returns {GetMapEntryRequest}
	 */
	function Build(options) {
		options = options || {};
		GetMapEntryRequest.memoize = memoize;
		return GetMapEntryRequest;

		/**
		 * Memoize the WREQR request.
		 * @param options
		 * @returns {GetMapEntryRequest}
		 */
		function memoize(options) {
			return MemoizePromise(GetMapEntryRequest, options);
		}

		/**
		 * GetMapEntry WREQR request with preset options.
		 *
		 * @param {String} key
		 * @param {Number} [mapIndex]
		 * @returns {Object}
		 */
		function GetMapEntryRequest(key, mapIndex) {
			console.log('GetMapEntry', key, mapIndex || options.lookupRequest);
			return GetMapEntry(this, key, mapIndex, options);
		}
	}

	/**
	 * Get an entry from a map file.
	 *
	 * @param {Object}        app The Marionette.Application.
	 * @param {String|Object} key
	 * @param {Object}        [options]
	 * @param {String}        [options.lookupRequest] WREQR request name to get the map lookup.
	 * @param {String}        [options.mapPrefix]
	 * @param {Function}      [options.parse]
	 * @returns {Object} jQuery.Deferred
	 */
	function GetMapEntry(app, key, mapIndex, options) {
		var deferred = $.Deferred();

		if (mapIndex != null) {
			getFromMap(app, deferred, key, mapIndex, options);
		}
		else if (options.lookupRequest) {
			findInLookup(app, deferred, key, options);
		}
		else {
			throw new Error('GetMapEntry must either have a mapIndex or options.lookupRequest');
		}

		return deferred;
	}

	// Use a lookup to determine what map file the entry is in.
	function findInLookup(app, deferred, key, options) {
		app.request(options.lookupRequest)
			.done(function(lookup) {

				// Determine which map file the entry is in.
				var found = _.find(lookup, function(item) {
					return key.substr(0, item[0].length) >= item[0]
						&& key.substr(0, item[1].length) <= item[1];
				});

				if (found) {
					getFromMap(app, deferred, key, found[2], options);
				}

				// Fail if a map file could not be determined for the hash.
				else {
					deferred.rejectWith(app, [ 'ENTRY_NOT_FOUND', 'NOT_IN_LOOKUP' ]);
				}
			})
			.fail(function() {
				deferred.rejectWith(app, Array.prototype.slice.call(arguments, 0));
			});
	}

	// Get the map file and the entry.
	function getFromMap(app, deferred, key, mapIndex, options) {
		console.log('GetMapEntry getFromMap', options.mapPrefix, key, mapIndex);
		app.request('GetFile', options.mapPrefix + mapIndex)
			.done(function(resp) {

				// Resolve the promise if found.
				var entry = resp[key];
				if (entry) {
					deferred.resolveWith(app, [ options.parse ? options.parse(entry, key) : entry ]);
				}

				// Fail if it is not in the map.
				else {
					deferred.rejectWith(app, [ 'ENTRY_NOT_FOUND', 'NOT_IN_MAP' ]);
				}
			})
			.fail(function(xhr, status, error) {
				deferred.rejectWith(app, Array.prototype.slice.call(arguments, 0));
			});
	}
});
