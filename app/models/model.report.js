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

			this.tabToShort = _.object(long, short);
			this.tabToLong = _.object(short, long);
		},

		defaults: {
			message: null,
			messageType: null,

			name: null,
			hash: null,
			tab: 'dirs',
			page: 1
		},

		validate: function(attributes) {
			if (!_.isObject(attributes))
				return 'Invalid attributes object';

			if (!_.isString(attributes.name))
				return 'Invalid name attribute';

			if (!_.isString(attributes.hash))
				return 'Invalid hash attribute';

			if (!_.isString(attributes.tab) || !_.isString(this.tabToShort[attributes.tab]))
				return 'Invalid tab attribute';

			if (!_.isFinite(attributes.page) || attributes.page % 1 != 0 || attributes.page < 1)
				return 'Invalid page attribute';
		}
	});

});