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
		}
	});

});