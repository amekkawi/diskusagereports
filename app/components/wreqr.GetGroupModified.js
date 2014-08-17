define([
	'underscore'
], function(_) {

	function sortComparator(sort, a, b) {
		var rev = 1;
		var attrs;
		switch (sort) {
			case 'A':
				rev = -1;
			case 'a':
				attrs = [ 'index' ];
				break;
			case 's':
				rev = -1;
			case 'S':
				attrs = [ 'size', 'index' ];
				break;
			case 'c':
				rev = -1;
			case 'C':
				attrs = [ 'files', 'index' ];
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

	function processDir(deferred, dir, sort) {
		var app = this;
		var data, mapId;

		// Get the data directly from the dir model, which will contain it if the data is small enough.
		if ((data = dir.get('modifiedDates')) != null) {
			deferred.resolveWith(app, [ sortAndSlice(data, sort, data.length, 1) ]);
		}

		// Get the data from a map (e.g. "modifieddates_1")
		else if ((mapId = dir.get('modifiedDatesMap')) != null) {
			app.request('GetFile', 'modifieddates_' + mapId)
				.done(function(map) {
					var data = map[dir.id];
					if (data) {
						var parsed = dir.parse({ modifiedDates: data }).modifiedDates;
						deferred.resolveWith(app, [ sortAndSlice(parsed, sort, parsed.length, 1) ]);
					}
					else
						deferred.rejectWith(app, [ 'MODIFIED_NOT_FOUND' ]);
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
			app.request('GetDirectory', dirOrHash)
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
