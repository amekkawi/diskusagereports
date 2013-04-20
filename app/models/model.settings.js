/*
 * Disk Usage Reports
 * http://diskusagereports.com/
 *
 * Copyright (c) 2013 André Mekkawi <diskusage@andremekkawi.com>
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */
define([
	'underscore',
	'models/model.reportfile'
], function(_, ReportFile){

	return ReportFile.extend({

		id: 'settings',

		defaults: {
			version: null,
			listversion: 1,

			name: null,
			root: null,
			path: null,

			ds: "/",
			created: null,
			directorytree: true,
			escaped: false,

			errors: [],

			modified: [],
			sizes: []
		},

		validate: function(attributes) {
			if (!_.isString(attributes.root) || attributes.root.length == 0)
				return 'Invalid root hash';

			if (!_.isString(attributes.ds))
				return 'Invalid directory separator';

			if (!_.isBoolean(attributes.directorytree))
				return 'Invalid directory tree flag';

			if (!_.isBoolean(attributes.escaped))
				return 'Invalid escaped flag';

			// TODO: Validate modified and sizes.
		},

		fetch: function(options) {
			var self = this,
				options = options || {},
				origError = options.error;

			options.error = function(){
				if (self.suffix.length)
					self.suffix.shift();

				if (self.suffix.length) {
					self.fetch(options);
				}
				else if (_.isFunction(origError)) {
					origError.apply(this, arguments);
				}
			};

			ReportFile.prototype.fetch.call(this, options);
		}
	});

});