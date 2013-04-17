/*
 * Disk Usage Reports
 * http://diskusagereports.com/
 *
 * Copyright (c) 2013 André Mekkawi <diskusage@andremekkawi.com>
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */
define([
	'backbone',
	'underscore'
], function(Backbone, _){

	return Backbone.Model.extend({

		tabToShort: null,
		tabToLong: null,

		initialize: function() {
			this.tabToShort = {
				dirs: 'd',
				files: 'f',
				modified: 'm',
				sizes: 's',
				ext: 'e',
				top: 't'
			};

			this.tabToLong = _.invert(this.tabToShort);
		},

		defaults: {
			name: null,
			hash: null,
			tab: 'dirs',
			page: 1
		}
	});

});