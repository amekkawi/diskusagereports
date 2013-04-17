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

		tabs: null,
		tabToShort: null,
		tabToLong: null,

		initialize: function() {
			this.tabs = [
				{ lang: 'tab_dirs', long: 'dirs', short: 'd' },
				{ lang: 'tab_files', long: 'files', short: 'f' },
				{ lang: 'tab_modified', long: 'modified', short: 'm' },
				{ lang: 'tab_sizes', long: 'sizes', short: 's' },
				{ lang: 'tab_ext', long: 'ext', short: 'e' },
				{ lang: 'tab_top', long: 'top', short: 't' }
			];

			var long = _.map(this.tabs, function(tab) { return tab.long }),
				short = _.map(this.tabs, function(tab) { return tab.short });

			this.tabToShort = _.zipObject(long, short);
			this.tabToLong = _.zipObject(short, long);
			console.log(this.tabs, this.tabToShort, this.tabToLong);
		},

		defaults: {
			name: null,
			hash: null,
			tab: 'dirs',
			page: 1
		}
	});

});