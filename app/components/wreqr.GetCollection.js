define([
	'underscore'
], function(_) {

	function sortComparator(attributes, rev, a, b) {
		for (var i = 0, l = attributes.length; i < l; i++) {
			var attr = attributes[i];
			if (a[attr] < b[attr]) return rev * -1;
			if (a[attr] > b[attr]) return rev;
		}
		return 0;
	}

	function sortData(dirs, sort, options) {
		var sorting = options.sorting[sort.toLowerCase()];
		if (!sorting)
			throw new Error('Unknown sort for ' + options.attribute + ':' + sort);

		var sortRev = (sort === sort.toUpperCase() ? -1 : 1) * (sorting.defaultReverse ? -1 : 1);
		return dirs
			.sort(function(a, b) {
				return sortComparator(sorting.attributes, sortRev, a, b)
			});
	}

	function sliceData(dirs, perPage, page) {
		var maxPage = Math.ceil(dirs.length / perPage);
		page = Math.min(maxPage, page);

		return dirs.slice(
			(page - 1) * perPage,
			Math.min(dirs.length, page * perPage)
		);
	}

	function sortAndSlice(dirs, sort, perPage, page, options) {
		return sliceData(sortData(dirs.slice(0), sort, options), perPage, page);
	}

	function processDir(deferred, dir, sort, page, options) {
		var app = this;
		var settings = app.settings;
		var attribute = options.attribute;

		var count = !!options.count && dir.get(options.count);
		var perPage, maxPage;
		if (count !== false) {
			count = dir.get(options.count);
			perPage = settings.get('perPage');
			maxPage = Math.ceil(count / perPage);
			page = Math.max(1, Math.min(maxPage, page));
		}
		else {
			perPage = false;
			maxPage = 1;
			page = 1;
		}

		var data, mapId;

		// Get the data directly from the dir model, which will contain it if the data is small enough.
		if ((data = dir.get(attribute)) != null) {
			deferred.resolveWith(app, [ sortAndSlice(data, sort, perPage === false ? data.length : perPage, page, options) ]);
		}

		// Get the data from a map (e.g. "filesmap_1.txt")
		else if ((mapId = dir.get(attribute + 'Map')) != null) {
			getFromMap.call(this, deferred, dir, mapId, sort, perPage, page, options);
		}

		// Get the file list via the multi-part lists (e.g. "files_2140d2c2dc425c0aaab8a8443e8880ca_1.txt")
		else if (dir.get(attribute + 'Segments') != null) {
			getFromSegments.call(this, deferred, dir, sort, perPage, page, maxPage, options);
		}
	}

	function getFromMap(deferred, dir, mapId, sort, perPage, page, options) {
		var app = this;
		var attribute = options.attribute;
		var mapPrefix = options.mapPrefix;

		app.request('GetFile', mapPrefix + '_' + mapId)
			.done(function(map) {
				var mapData = map[dir.id];
				if (mapData) {
					var data = dir.parseAttribute(attribute, mapData);
					deferred.resolveWith(app, [sortAndSlice(data, sort, perPage === false ? data.length : perPage, page, options)]);
				}
				else
					deferred.rejectWith(app, [ options.notFound ]);
			})
			.fail(function() {
				deferred.rejectWith(app, Array.prototype.slice.call(arguments, 0));
			});
	}

	function getFromSegments(deferred, dir, sort, perPage, page, maxPage, options) {
		var app = this;
		var settings = app.settings;
		var attribute = options.attribute;
		var segmentPrefix = options.segmentPrefix;
		var count = !!options.count && dir.get(options.count);

		var sortingData = options.sorting[sort.toLowerCase()];
		if (!sortingData)
			throw new Error('Unknown sort for ' + attribute + ':' + sort);

		// Determine the sort column and order.
		var sortIndex = sortingData.segmentIndex;
		var reversed = sort === sort.toUpperCase();

		// Reverse the page number if the sort is reversed.
		if (reversed)
			page = maxPage - page + 1;

		var pagesPerSegment = settings.get(options.pagesPerSegment);

		// The main segment file for retreiving the list items.
		var segmentId = Math.ceil(page / pagesPerSegment);

		// The page within the segment file where the last list item is found.
		var segmentPage = page - ((segmentId - 1) * 2);

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
			return app.request('GetFile', segmentPrefix + '_' + dir.id + '_' + file.segmentId)
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

				deferred.resolveWith(app, [ dir.parseAttribute(attribute, data) ]);
			})
			.fail(function() {
				deferred.rejectWith(app, Array.prototype.slice.call(arguments, 0));
			});
	}

	return function(options) {
		options = options || {};

		return function (dirOrHash, sort, page) {
			var app = this;
			var deferred = $.Deferred();

			if (_.isString(dirOrHash)) {
				app.request('GetDirectory', dirOrHash)
					.done(function (dir) {
						processDir.call(app, deferred, dir, sort, page, options);
					})
					.fail(function () {
						deferred.rejectWith(app, Array.prototype.slice.call(arguments, 0));
					});
			}
			else {
				processDir.call(app, deferred, dirOrHash, sort, page, options);
			}

			return deferred;
		};
	};
});
