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

					// Sub-dirs are in segment files.
					else {
						dir.dirsSegments = _.reduce(['name','size','count','dirs'], function(ret, key, i){
							ret[key] = _.map(subDirs[i], function(file) {
								return {
									lower: file[0],
									upper: file[1],
									id: file[2]
								};
							});
							return ret;
						}, {});
						delete dir.dirs;
					}
				}
			}

			// Normalize the files list.
			if (_.has(dir, 'files')) {
				var files = dir.files;

				// Files are in a filesmap_* file.
				if (typeof files === 'string') {
					dir.filesMap = '' + files;
					delete dir.files;
				}
				else if (typeof files === 'number') {
					dir.filesSegments = files;
					delete dir.files;
				}
				else {
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
			}

			// Normalize the file sizes and modified date groups.
			_.each(['fileSizes', 'modifiedDates'], function(group) {
				if (_.has(dir, group)) {
					if (typeof dir[group] === 'string' || typeof dir[group] === 'number') {
						dir[group + 'Map'] = ''+dir[group];
						delete dir[group];
					} else {
						dir[group] = _.map(dir[group][0], function(groupIndex, i) {
							return {
								index: groupIndex,
								size: dir[group][1][i][0],
								files: dir[group][1][i][1]
							};
						});
					}
				}
			});

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

	return Model.extend(/** @lends Dir.prototype */{

		/**
		 * Backbone model for directory data.
		 *
		 * @extends {Model}
		 * @constructs
		 * @name Dir
		 */

		/**
		 * Parse the JSON for a directory.
		 *
		 * @param {Object} response The JSON for the directory.
		 * @param {Object} [options]
		 * @param {Settings} [options.settings]
		 * @returns {*}
		 */
		parse: function(response, options) {
			var settings = this.settings || options.settings;
			switch (settings.get('version')) {
				case '2.0':
					return parseDir['2.0'](response);
				default:
					return parseDir['1.0'](response);
			}
		},

		/**
		 * Parse the JSON for a single attribute.
		 *
		 * @param {String} name
		 * @param value
		 * @param {Object} [options]
		 * @param {Settings} [options.settings]
		 * @returns {*}
		 */
		parseAttribute: function(name, value, options) {
			var data = {};
			data[name] = value;
			return this.parse(data, options)[name];
		}
	});
});
