"use strict";
define([
	'underscore',
	'models/Model'
], function(_, Model) {
	var ModelDir = Model.extend({
		url: function() {
			if (!this.urlRoot)
				this.urlRoot = this.settings.urlRoot;
			return Model.prototype.url.apply(this, arguments) + this.settings.suffix;
		},
		parse: function(response) {
			switch (this.settings.get('version')) {
				case '2.0':
					return this._parse2_0(response);
				default:
					return this._parse1_0(response);
			}
		},
		_parse2_0: function(response) {
			var hash = this.get('hash');
			if (!_.has(response, hash))
				return;

			response = this.remapAttributes(
				response[hash] || {},
				ModelDir.AttributeMaps['2.0']
			);

			// Normalize numbers.
			_.each(['subDirCount','directFileCount','subFileCount','directFileSize','subFileSize'], function(attribute){
				if (_.has(response, attribute))
					response[attribute] = parseInt(response[attribute]);
			}, this);

			// Add totals.
			if (_.has(response, 'directFileCount') && _.has(response, 'subFileCount'))
				response.fileCount = response.directFileCount + response.subFileCount;
			if (_.has(response, 'directFileSize') && _.has(response, 'subFileSize'))
				response.fileSize = response.directFileSize + response.subFileSize;

			// Normalize parent list.
			if (_.has(response, 'parents')) {
				response.parents = _.map(response.parents, function(val) {
					return {
						hash: val[0],
						name: val[1]
					};
				});
			}

			/*
			 // Normalize subdir list.
			 if (_.has(response, 'parents')) {
			 response.parents = _.map(response.parents, function(val) {
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
			 if (_.has(response, attribute)) {
			 response[attribute] = _.reduce(response[attribute][0], function(ret, i) {
			 ret[i] = response[attribute][1][i];
			 }, []);
			 }
			 }, this);
			 */

			return response;
		},
		_parse1_0: function(response) {
			response = this.remapAttributes(
				response,
				ModelDir.AttributeMaps['1.0']
			);

			return response;
		},
		remapAttributes: function(obj, map) {
			return _.reduce(obj, function(ret, val, key) {
				ret[map[key] || key] = val;
				return ret;
			}, {});
		}
	}, {
		AttributeMaps: {
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
		}
	});

	return ModelDir;
});
