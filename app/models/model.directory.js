/*
 * Disk Usage Reports
 * http://diskusagereports.com/
 *
 * Copyright (c) 2013 André Mekkawi <diskusage@andremekkawi.com>
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */
define([
	'models/model.reportfile'
], function(ReportFile){

	return ReportFile.extend({

		id: null,

		defaults: {
			hash: null,
			name: null,
			parents: [],

			subdirs: [],
			totalsubdirs: 0,

			num: 0,
			totalnum: 0,

			bytes: 0,
			totalbytes: 0,

			files: null,

			modified: null,
			sizes: null,
			top100: null
		},

		parse: function(response, options) {
			response.hash = this.id;
			return response;
		}

	});

});