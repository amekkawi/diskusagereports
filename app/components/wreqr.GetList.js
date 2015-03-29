define([
	'underscore',
	'models/Dir',
	'components/util.memoizepromise'
], function(_, Dir, MemoizePromise) {

	GetList.Build = Build;
	return GetList;

	/**
	 * Build a GetList WREQR request with preset options.
	 *
	 * @param {Object} [options]
	 * @returns {GetListRequest}
	 */
	function Build(options) {
		options = options || {};
		GetListRequest.memoize = memoize;
		return GetListRequest;

		/**
		 * Memoize the WREQR request.
		 * @param options
		 * @returns {GetListRequest}
		 */
		function memoize(options) {
			return MemoizePromise(GetListRequest, options);
		}

		/**
		 * GetList WREQR request with preset options.
		 *
		 * @param key
		 * @param sort
		 * @param page
		 * @returns {Object}
		 */
		function GetListRequest(key, sort, page) {
			return GetList(this, key, sort, page, options);
		}
	}

	/**
	 * TODO:
	 *
	 * @param {object}        app The Marionette.Application.
	 * @param {string|object} key
	 * @param {string}        sort
	 * @param {string|int}    page
	 * @param {object}        [options]
	 * @param {function}      [options.sortedMap]
	 * @returns {Object} jQuery.Deferred
	 */
	function GetList(app, key, sort, page, options) {
		options = options || {};

		var deferred = $.Deferred();

		has('DEBUG') && has('DEBUG_GETLIST') && console.log('GetList', key, sort, page, options);

		if (_.isString(key)) {

			// Find map entry using lookup.
			if (options.sortedMap) {
				getFromSortedMap(app, deferred, key, sort, page, options);
			}

			// Get directory first.
			else {
				app.request('GetDirectory', key)
					.done(function (dir) {
						getFromDirModel(app, deferred, dir, sort, page, options);
					})
					.fail(function () {
						deferred.rejectWith(app, Array.prototype.slice.call(arguments, 0));
					});
			}
		}

		// Save data for segmented list files (e.g. "files_2140d2c2dc425c0aaab8a8443e8880ca_1.txt")
		else if (_.isPlainObject(key)) {
			getFromSegments(app, deferred, key, sort, page, options);
		}

		// Dir model.
		else if (_.isObject(key)) {
			getFromDirModel(app, deferred, key, sort, page, options);
		}

		else {
			deferred.rejectWith(app, [ 'INVALID_KEY' ]);
		}

		return deferred;
	}

	function processFullList(app, deferred, list, sort, page, options) {
		var perPage = options.perPage === false
			? list.length
			: app.settings.get('perPage');

		deferred.resolveWith(app, [ sortAndSlice(list, sort, perPage, page, options) ]);
	}

	/**
	 * Process a {@link Dir} model to get the requested collection.
	 *
	 * @param {Object} app
	 * @param {Object} deferred
	 * @param {Dir} dir
	 * @param {String} sort
	 * @param {Number} page
	 * @param {Object} options
	 */
	function getFromDirModel(app, deferred, dir, sort, page, options) {
		has('DEBUG') && has('DEBUG_GETLIST') && console.log('GetList getFromDirModel', dir.id);
		var attribute = options.attribute;
		var val;

		// Get the data directly from the dir model, which will contain it if the data is small enough.
		if ((val = dir.get(attribute)) != null) {
			processFullList(app, deferred, val, sort, page, options);
		}

		// Get from segmented list files (e.g. "files_2140d2c2dc425c0aaab8a8443e8880ca_1.txt")
		else if ((val = dir.get(attribute + 'Save')) != null) {
			getFromSegments(app, deferred, dir.id, val, sort, page, options);
		}

		// Get the data from a map (e.g. "filesmap_1.txt")
		else if ((val = dir.get(attribute + 'Map')) != null) {
			getFromIndexedMap(app, deferred, dir.id, val, sort, page, options);
		}

		else if (options.sortedMap) {
			app.request(options.sortedMap, dir.id)
				.done(function (entry) {
					// Parse through GetList again if we got save data.
					if (entry[attribute + 'Save']) {
						getFromSegments(app, deferred, dir.id, entry[attribute + 'Save'], sort, page, options);
					}
					// Otherwise, resolve using the result.
					else if (entry[attribute]) {
						processFullList(app, deferred, entry[attribute], sort, page, options);
					}
					else {
						deferred.rejectWith(app, ['NOT_FOUND']);
					}
				})
				.fail(function () {
					deferred.rejectWith(app, Array.prototype.slice.call(arguments, 0));
				});
		}

		else {
			deferred.rejectWith(app, [ 'NO_DATA' ]);
		}
	}

	function getFromSortedMap(app, deferred, key, sort, page, options) {
		has('DEBUG') && has('DEBUG_GETLIST') && console.log('GetList getFromSortedMap', options.sortedMap, key);
		app.request(options.sortedMap, key)
			.done(function (mapData) {
				processMapEntry(app, deferred, key, mapData, sort, page, options);
			})
			.fail(function () {
				deferred.rejectWith(app, Array.prototype.slice.call(arguments, 0));
			});
	}

	function getFromIndexedMap(app, deferred, key, mapId, sort, page, options) {
		has('DEBUG') && has('DEBUG_GETLIST') && console.log('GetList getFromIndexedMap', options.mapRequest, key, mapId);
		app.request(options.mapRequest, key, mapId)
			.done(function(entry) {
				processMapEntry(app, deferred, key, entry, sort, page, options);
			})
			.fail(function() {
				deferred.rejectWith(app, Array.prototype.slice.call(arguments, 0));
			});
	}

	// Parse attribute through Dir model.
	function parse(app, mapData, attribute) {
		var data = {};
		data[attribute] = mapData;
		return Dir.parse(data, { settings: app.settings });
	}

	function processMapEntry(app, deferred, key, mapData, sort, page, options) {
		has('DEBUG') && has('DEBUG_GETLIST') && console.log('GetList processMapEntry', key, mapData, sort, page);
		var attribute = options.attribute;
		var data = mapData;

		// Contains the full list.
		if (data[attribute] != null) {
			processFullList(app, deferred, data[attribute], sort, page, options);
		}

		// Get from segmented list files (e.g. "files_2140d2c2dc425c0aaab8a8443e8880ca_1.txt")
		else if (data[attribute + 'Save'] != null) {
			getFromSegments(app, deferred, key, data[attribute + 'Save'], sort, page, options);
		}

		else {
			// TODO: Handle unexpected value?
		}
	}

	function getFromSegments(app, deferred, key, saveData, sort, page, options) {
		has('DEBUG') && has('DEBUG_GETLIST') && console.log('GetList processMapEntry', key, saveData, sort, page);
		var attribute = options.attribute;
		var segmentPrefix = options.segmentPrefix;
		var segmentCount = saveData.segments;

		// If there is only one segment then it just didn't fit in the map.
		// Fetch the segment and process it as the list.
		if (segmentCount === 1) {
			app.request('GetFile', segmentPrefix + '_' + key + '_1')
				.done(function(mapData) {
					mapData = parse(app, mapData, attribute);
					processFullList(app, deferred, mapData[attribute], sort, page, options);
				})
				.fail(function() {
					deferred.rejectWith(app, Array.prototype.slice.call(arguments, 0));
				});

			return;
		}

		var settings = app.settings;
		var pagesPerSegment = saveData.pagesPerSegment;
		var perPage = settings.get('perPage');

		// Determine the total number of items in the list.
		var count = ((segmentCount - 1) * pagesPerSegment * perPage) + (saveData.remainder);

		// Determine the max page number and make sure page is not over it.
		var maxPage = Math.ceil(count / perPage);
		page = Math.max(1, Math.min(maxPage, page));

		var sortingData = options.sorting[sort.toLowerCase()];
		if (!sortingData)
			throw new Error('Unknown sort for ' + attribute + ':' + sort);

		// Determine the sort column and order.
		var sortIndex = sortingData.segmentIndex;
		var reversed = sort === sort.toUpperCase();

		// Reverse the page number if the sort is reversed.
		if (reversed)
			page = maxPage - page + 1;

		// The main segment file for retreiving the list items.
		var segmentId = Math.ceil(page / pagesPerSegment);

		// The page within the segment file where the last list item is found.
		var segmentPage = page - ((segmentId - 1) * pagesPerSegment);

		// The remainder tell us how many segment files are on the last page,
		// which will cause problems when the sort order is reversed.
		var remainder = reversed ? count % perPage : 0;

		// The segment files needed to display the list.
		var segments = [];

		// If the sort is reversed and there is a per-page remainder,
		// we may need more than one file to display the per-page amount.
		if (reversed && remainder !== 0) {

			// Get the previous segment file if we need to pull the remainder from the end of it.
			if (segmentPage === 1 && segmentId > 1) {
				segments.push({
					segmentId: segmentId - 1,
					start: -(perPage - remainder)
				});
			}

			// Specify the list items we need from the main segment file, offset by the remainder.
			segments.push({
				segmentId: segmentId,
				start: Math.max(0, (segmentPage-1) * perPage - (perPage - remainder)),
				end: (segmentPage) * perPage - (perPage - remainder)
			});
		}

		// Otherwise, we only need the main segment file to display the per-page amount.
		else {
			segments.push({
				segmentId: segmentId,
				start: (segmentPage-1) * perPage,
				end: (segmentPage) * perPage
			});
		}

		// Get all the required segment files, and collect their promises.
		var innerDeferreds = _.map(segments, function(file){
			return app.request('GetFile', segmentPrefix + '_' + key + '_' + file.segmentId)
				.done(function(data) {
					file.entries = data[sortIndex].slice(file.start, file.end);
				});
		});

		// Wait for the promises to be resolved or rejected.
		$.when.apply($, innerDeferreds)
			.done(function(){

				// Create the full list of entries.
				var data = segments.length === 1 ? segments[0].entries : _.reduce(segments, function(ret, segment) {
					return ret.concat(segment.entries);
				}, []);

				// Reverse the entries, if necessary.
				if (reversed)
					data.reverse();

				deferred.resolveWith(app, [ parse(app, data, attribute)[attribute] ]);
			})
			.fail(function() {
				deferred.rejectWith(app, Array.prototype.slice.call(arguments, 0));
			});
	}

	function sortComparator(attributes, rev, a, b) {
		for (var i = 0, l = attributes.length; i < l; i++) {
			var attr = attributes[i];
			if (a[attr] < b[attr]) return rev * -1;
			if (a[attr] > b[attr]) return rev;
		}
		return 0;
	}

	function sortData(list, sort, options) {
		var sorting = options.sorting[sort.toLowerCase()];
		if (!sorting)
			throw new Error('Unknown sort for ' + options.attribute + ':' + sort);

		if (!list.length)
			return list;

		var sortRev = (sort === sort.toUpperCase() ? -1 : 1) * (sorting.defaultReverse ? -1 : 1);
		return list
			.sort(function(a, b) {
				return sortComparator(sorting.attributes, sortRev, a, b)
			});
	}

	function sliceData(list, perPage, page) {
		var maxPage = Math.ceil(list.length / perPage);
		page = Math.min(maxPage, page);

		if (maxPage <= 1)
			return list;

		return list.slice(
			(page - 1) * perPage,
			Math.min(list.length, page * perPage)
		);
	}

	function sortAndSlice(list, sort, perPage, page, options) {
		if (!list.length)
			return list;

		return sliceData(sortData(list.slice(0), sort, options), perPage, page);
	}
});
