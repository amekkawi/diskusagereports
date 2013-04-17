/*
 * Disk Usage Reports
 * http://diskusagereports.com/
 *
 * Copyright (c) 2013 André Mekkawi <diskusage@andremekkawi.com>
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */
define([
	'backbone'
], function(Backbone){

	return Backbone.Model.extend({

		defaults: {
			hash: null,
			name: null,
			parents: [],

			files: {
				direct: 0,
				total: 0
			},

			size: {
				direct: 0,
				total: 0
			},

			subdirs: {
				total: 0,
				list: []
			},

			modified: {},
			sizes: {},
			top100: []
		},

		initialize: function() {

		}
	});

});