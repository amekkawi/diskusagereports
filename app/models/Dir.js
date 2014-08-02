define([
	'underscore',
	'models/Model'
], function(_, Model) {
	"use strict";

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
