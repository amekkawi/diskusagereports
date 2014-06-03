define([
	'jquery'
], function($) {

	var dirAttributeMapping = {
		'2.0': {
			n: 'name',
			d: 'subDirCount',
			F: 'directFileCount',
			f: 'subFileCount',
			S: 'directFileSize',
			s: 'subFileSize',
			L: 'dirs',
			l: 'files',
			t: 'top',
			u: 'fileSizes',
			m: 'modifiedDates',
			p: 'parents'
		},
		'1.0': {
			bytes: 'directFileSize',
			totalbytes: 'fileSize',
			num: 'directFileCount',
			totalnum: 'fileCount',
			sizes: 'fileSizes',
			subdirs: 'dirs',
			top100: 'top',
			modified: 'modifiedDates',
			types: 'fileTypes'
		}
	};

	function remapAttributes(obj, map) {
		return _.reduce(obj, function(ret, val, key) {
			ret[map[key] || key] = val;
			return ret;
		}, {});
	}

	var parseDir = {
		'2.0': function(dir) {
			dir = remapAttributes(
				dir,
				dirAttributeMapping['2.0']
			);

			// Normalize numbers.
			_.each(['subDirCount','directFileCount','subFileCount','directFileSize','subFileSize'], function(attribute){
				if (_.has(dir, attribute))
					dir[attribute] = parseInt(dir[attribute]);
			}, this);

			// Add totals.
			if (_.has(dir, 'directFileCount') && _.has(dir, 'subFileCount'))
				dir.fileCount = dir.directFileCount + dir.subFileCount;
			if (_.has(dir, 'directFileSize') && _.has(dir, 'subFileSize'))
				dir.fileSize = dir.directFileSize + dir.subFileSize;

			// Normalize parent list.
			if (_.has(dir, 'parents')) {
				dir.parents = _.map(dir.parents, function(val) {
					return {
						hash: val[0],
						name: val[1]
					};
				});
			}

			/*
			 // Normalize subdir list.
			 if (_.has(dir, 'parents')) {
			 dir.parents = _.map(dir.parents, function(val) {
			 return {
			 hash: val[0],
			 name: val[1],
			 subDirCount: val[2],
			 fileCount: val[3],
			 fileSize: val[4]
			 };
			 });
			 }

			 // Normalize grouped totals.
			 _.each(['fileSizes', 'modifiedDates'], function(attribute) {
			 if (_.has(dir, attribute)) {
			 dir[attribute] = _.reduce(dir[attribute][0], function(ret, i) {
			 ret[i] = dir[attribute][1][i];
			 }, []);
			 }
			 }, this);
			 */

			return dir;
		},
		'1.0': function(dir) {
			dir = remapAttributes(
				dir,
				dirAttributeMapping['1.0']
			);

			return dir;
		}
	};

	return function(hash) {
		var app = this;
		var deferred = $.Deferred();
		var xhr;

		deferred.abort = function() {
			xhr && xhr.abort();
		};

		if (!app.settings) {
			deferred.rejectWith(app, [ 'SETTINGS_NOT_LOADED' ]);
		}
		else if (!app.settings.has('version') || app.settings.get('version') === '1.0') {
			xhr = $.ajax({
				dataType: 'json',
				url: app.urlRoot + '/' + hash + app.suffix
			})
				.done(function(resp) {
					deferred.resolveWith(app, [ parseDir['1.0'](resp) ]);
				})
				.fail(function(xhr, status, error) {
					deferred.rejectWith(app, [ 'DIR_NOT_FOUND', 'FETCH_FAIL', xhr, status, error ]);
				});
		}
		else {
			app.request('GetLookup')
				.done(function(dirmapLookup) {
					var found = _.find(dirmapLookup, function(item) {
						return hash.substr(0, item[0].length) >= item[0]
							&& hash.substr(0, item[1].length) <= item[1];
					});

					if (found) {
						xhr = $.ajax({
							dataType: 'json',
							url: app.urlRoot + '/dirmap_' + found[2] + app.suffix
						})
							.done(function(resp) {
								var dir = resp[hash];
								if (dir) {
									if (app.settings.get('version') === '2.0')
										dir = parseDir['2.0'](dir);

									deferred.resolveWith(app, [ dir ]);
									app.vent.trigger('dirmapLookup:loaded', dirmapLookup);
								}
								else {
									deferred.rejectWith(app, [ 'DIR_NOT_FOUND', 'NOT_IN_MAP' ]);
								}
							})
							.fail(function(xhr, status, error) {
								deferred.rejectWith(app, [ 'DIR_NOT_FOUND', 'FETCH_FAIL', xhr, status, error ]);
							});
					}
					else {
						deferred.rejectWith(app, [ 'DIR_NOT_FOUND', 'NOT_IN_LOOKUP' ]);
					}
				})
				.fail(function() {
					deferred.rejectWith(app, [ 'LOOKUP_NOT_FOUND' ]);
				});
		}

		return deferred;
	};
});
