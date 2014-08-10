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

	function sortAndSlice(settings, dirs, sort, page) {
		var perPage = settings.get('perPage');
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
		var perPage = settings.get('perPage');
		var maxPage = Math.ceil(dir.get('directSubDirCount') / perPage);

		page = Math.max(1, Math.min(maxPage, page));

		var dirs, dirsMapId, dirsLookup;
		if ((dirs = dir.get('dirs')) != null) {
			deferred.resolveWith(app, [ sortAndSlice(settings, dir.parse({ dirs: dirs }).dirs, sort, page), false ]);
		}
		else if ((dirsMapId = dir.get('dirsMap')) != null) {
			app.request('GetFile', 'subdirsmap_' + dirsMapId)
				.done(function(subDirsMap) {
					var subDirs = subDirsMap[dir.id];
					if (subDirs)
						deferred.resolveWith(app, [ sortAndSlice(settings, dir.parse({ dirs: subDirs }).dirs, sort, page), false ]);
					else
						deferred.rejectWith(app, [ 'SUBDIRS_NOT_FOUND' ]);
				})
				.fail(function() {
					deferred.rejectWith(app, Array.prototype.slice.call(arguments, 0));
				});
		}
		else if ((dirsLookup = dir.get('dirsLookup')) != null) {
			var pagesPerSubdirs = settings.get('pagesPerSubdirs');
			var dirLookupId = Math.ceil(page / pagesPerSubdirs);

			var subDirsIndex;
			switch (sort.toLowerCase()) {
				case 'n':
					subDirsIndex = 0;
					break;
				case 's':
					subDirsIndex = 1;
					break;
				case 'c':
					subDirsIndex = 2;
					break;
				case 'd':
					subDirsIndex = 3;
					break;
				default:
					throw new Error('Failed to determine sort order.');
			}

			app.request('GetFile', 'subdirs_' + dir.id + '_' + dirLookupId)
				.done(function(subDirsFile) {
					var subPage = page - ((dirLookupId - 1) * 2);
					deferred.resolveWith(app, [ sortAndSlice(settings, dir.parse({ dirs: subDirsFile[subDirsIndex] }).dirs, sort, subPage), false ]);
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
