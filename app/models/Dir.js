define([
	'underscore',
	'models/Model'
], function(_, Model) {
	"use strict";

	var dirAttributeMapping = {
		'2.0': {
			n: 'name',
			D: 'directSubDirCount',
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

			// Normalize the sub-dirs list.
			if (_.has(dir, 'dirs')) {
				var subDirs = dir.dirs;

				// Subdirs are in a subdirsmap_* file.
				if (typeof subDirs === 'number' || typeof subDirs === 'string') {
					dir.dirsMap = '' + subDirs;
					delete dir.dirs;
				}
				else if (subDirs.length) {

					// Contains the full list.
					if (typeof subDirs[0][0] === 'string') {
						dir.dirs = _.map(subDirs, function(subDir) {
							return {
								hash: subDir[0],
								name: subDir[1],
								dirCount: subDir[2],
								fileCount: subDir[3],
								size: subDir[4]
							};
						});
					}

					// Is a lookup mapping.
					else {
						dir.dirsLookup = subDirs;
						delete dir.dirs;
					}
				}
			}

			// Normalize the files list.
			if (_.has(dir, 'files')) {
				var files = dir.files;

				// Files are in a filesmap_* file.
				if (typeof files === 'number' || typeof files === 'string') {
					dir.filesMap = '' + files;
					delete dir.files;
				}
				else if (files.length) {

					// Contains the full list.
					if (typeof files[0][0] === 'string') {
						dir.files = _.map(files, function(file) {
							return {
								type: file[0],
								name: file[1],
								size: file[2],
								date: file[3],
								time: file[4]
							};
						});
					}

					// Is a lookup mapping.
					else {
						dir.filesLookup = files;
						delete dir.files;
					}
				}
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

	return Model.extend({
		url: function() {
			if (!this.urlRoot)
				this.urlRoot = this.settings.urlRoot;
			return Model.prototype.url.apply(this, arguments) + this.settings.suffix;
		},
		parse: function(response, options) {
			var settings = this.settings || options.settings;
			switch (settings.get('version')) {
				case '2.0':
					return parseDir['2.0'](response);
				default:
					return parseDir['1.0'](response);
			}
		}
	});
});
