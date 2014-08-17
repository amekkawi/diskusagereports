define([
	'underscore'
], function(_) {

	function sortComparator(sort, a, b) {
		var rev = 1;
		var attrs;
		switch (sort) {
			case 'N':
				rev = -1;
			case 'n':
				attrs = [ 'name' ];
				break;
			case 's':
				rev = -1;
			case 'S':
				attrs = [ 'size', 'name' ];
				break;
			case 'M':
				rev = -1;
			case 'm':
				attrs = [ 'date', 'time', 'name' ];
				break;
			default:
				throw new Error('Failed to determine sort order.');
		}

		for (var i = 0, l = attrs.length; i < l; i++) {
			var attr = attrs[i];
			if (a[attr] < b[attr]) return -1 * rev;
			if (a[attr] > b[attr]) return 1 * rev;
		}

		return 0;
	}

	function sortAndSlice(dirs, sort, perPage, page) {
		var maxPage = Math.ceil(dirs.length / perPage);
		page = Math.min(maxPage, page);

		return dirs
			.slice(0)
			.sort(function(a, b) {
				return sortComparator(sort, a, b)
			})
			.slice(
				(page - 1) * perPage,
				Math.min(dirs.length, page * perPage)
			);
	}

	function processDir(deferred, dir, sort, page) {
		var app = this;
		var settings = app.settings;
		var count = dir.get('directFileCount');
		var perPage = settings.get('perPage');
		var maxPage = Math.ceil(count / perPage);

		page = Math.max(1, Math.min(maxPage, page));

		var files, mapId;

		// Get the file list directly from the dir model, which will contain it if the file list is small enough.
		if ((files = dir.get('files')) != null) {
			deferred.resolveWith(app, [ sortAndSlice(files, sort, perPage, page) ]);
		}

		// Get the file list from a map (e.g. "filesmap_1.txt")
		else if ((mapId = dir.get('filesMap')) != null) {
			app.request('GetFile', 'filesmap_' + mapId)
				.done(function(map) {
					var data = map[dir.id];
					if (data)
						deferred.resolveWith(app, [ sortAndSlice(dir.parse({ files: data }).files, sort, perPage, page) ]);
					else
						deferred.rejectWith(app, [ 'FILES_NOT_FOUND' ]);
				})
				.fail(function() {
					deferred.rejectWith(app, Array.prototype.slice.call(arguments, 0));
				});
		}

		// Get the file list via the multi-part lists (e.g. "files_2140d2c2dc425c0aaab8a8443e8880ca_1.txt")
		else if (dir.get('filesSegments') != null) {

			// Determine the sort column and order.
			var reversed = false;
			var sortIndex;
			switch (sort) {
				case 'N':
					reversed = true;
				case 'n':
					sortIndex = 0;
					break;
				case 'S':
					reversed = true;
				case 's':
					sortIndex = 1;
					break;
				case 'M':
					reversed = true;
				case 'm':
					sortIndex = 2;
					break;
				default:
					throw new Error('Failed to determine sort order.');
			}

			// Reverse the page number if the sort is reversed.
			if (reversed)
				page = maxPage - page + 1;

			var pagesPerSegment = settings.get('pagesPerFiles');

			// The main segment file for retreiving the list items.
			var segmentId = Math.ceil(page / pagesPerSegment);

			// The page within the segment file (see Options->maxFileListFilePages) where the last list item is found.
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
				return app.request('GetFile', 'files_' + dir.id + '_' + file.segmentId)
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

					deferred.resolveWith(app, [ dir.parse({ files: data }).files ]);
				})
				.fail(function() {
					deferred.rejectWith(app, Array.prototype.slice.call(arguments, 0));
				});
		}
	}

	return function(dirOrHash, sort, page) {
		var app = this;
		var deferred = $.Deferred();

		if (_.isString(dirOrHash)) {
			app.request('GetDirectory', hash)
				.done(function(dir) {
					processDir.call(app, deferred, dir, sort, page);
				})
				.fail(function() {
					deferred.rejectWith(app, Array.prototype.slice.call(arguments, 0));
				});
		}
		else {
			processDir.call(app, deferred, dirOrHash, sort, page);
		}

		return deferred;
	};
});
