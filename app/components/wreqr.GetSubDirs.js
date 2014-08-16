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
			case 'c':
				rev = -1;
			case 'C':
				attrs = [ 'fileCount', 'name' ];
				break;
			case 'd':
				rev = -1;
			case 'D':
				attrs = [ 'dirCount', 'name' ];
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
				Math.min(dirs.length - 1, page * perPage)
			);
	}

	function processDir(deferred, dir, sort, page) {
		var app = this;
		var settings = app.settings;
		var subDirCount = dir.get('directSubDirCount');
		var perPage = settings.get('perPage');
		var maxPage = Math.ceil(subDirCount / perPage);

		page = Math.max(1, Math.min(maxPage, page));

		var dirs, dirsMapId, dirsLookup;
		if ((dirs = dir.get('dirs')) != null) {
			deferred.resolveWith(app, [ sortAndSlice(dir.parse({ dirs: dirs }).dirs, sort, perPage, page) ]);
		}
		else if ((dirsMapId = dir.get('dirsMap')) != null) {
			app.request('GetFile', 'subdirsmap_' + dirsMapId)
				.done(function(subDirsMap) {
					var subDirs = subDirsMap[dir.id];
					if (subDirs)
						deferred.resolveWith(app, [ sortAndSlice(dir.parse({ dirs: subDirs }).dirs, sort, perPage, page) ]);
					else
						deferred.rejectWith(app, [ 'SUBDIRS_NOT_FOUND' ]);
				})
				.fail(function() {
					deferred.rejectWith(app, Array.prototype.slice.call(arguments, 0));
				});
		}
		else if ((dirsLookup = dir.get('dirsLookup')) != null) {

			// Determine the sort column and order.
			var reversed = false;
			var subDirsIndex;
			switch (sort) {
				case 'N':
					reversed = true;
				case 'n':
					subDirsIndex = 0;
					break;
				case 'S':
					reversed = true;
				case 's':
					subDirsIndex = 1;
					break;
				case 'C':
					reversed = true;
				case 'c':
					subDirsIndex = 2;
					break;
				case 'D':
					reversed = true;
				case 'd':
					subDirsIndex = 3;
					break;
				default:
					throw new Error('Failed to determine sort order.');
			}

			// Reverse the page number if the sort is reversed.
			if (reversed)
				page = maxPage - page + 1;

			var pagesPerSubdirs = settings.get('pagesPerSubdirs');
			var segmentId = Math.ceil(page / pagesPerSubdirs);
			var segmentPage = page - ((segmentId - 1) * 2);
			var remainder = reversed ? subDirCount % perPage : 0;

			var files = [];
			if (reversed && remainder !== 0) {
				if (segmentPage === 1 && segmentId > 1) {
					files.push({
						segmentId: segmentId - 1,
						start: -(perPage - remainder)
					});
				}

				files.push({
					segmentId: segmentId,
					start: Math.max(0, (segmentPage-1) * perPage - (perPage - remainder)),
					end: (segmentPage) * perPage - (perPage - remainder)
				});
			}
			else {
				files.push({
					segmentId: segmentId,
					start: (segmentPage-1) * perPage,
					end: (segmentPage) * perPage
				});
			}

			var innerDeferreds = _.map(files, function(file){
				return app.request('GetFile', 'subdirs_' + dir.id + '_' + file.segmentId)
					.done(function(subDirsFile) {
						file.entries = subDirsFile[subDirsIndex].slice(file.start, file.end);
					});
			});

			$.when.apply($, innerDeferreds)
				.done(function(){
					var subDirs = _.reduce(files, function(ret, file) {
						return ret.concat(file.entries);
					}, []);

					// Reverse the array.
					if (reversed)
						subDirs.reverse();

					deferred.resolveWith(app, [ dir.parse({ dirs: subDirs }).dirs ]);
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
