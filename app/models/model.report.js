/*
 * Disk Usage Reports
 * http://diskusagereports.com/
 *
 * Copyright (c) 2013 André Mekkawi <diskusage@andremekkawi.com>
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */
define([
	'models/model',
	'underscore',
	'tabs'
], function(Model, _, Tabs){
	return Model.extend({

		defaults: {
			hash: null,
			tab: 'dirs',
			page: 1
		},

		validate: function(attributes) {
			var tab = Tabs.lookup[attributes.tab];
			if (!tab || attributes.tab !== tab.long)
				return 'Invalid tab attribute';

			if (!_.isNumber(attributes.page) || !_.isFinite(attributes.page) || attributes.page % 1 != 0 || attributes.page < 1)
				return 'Invalid page attribute';
		}
	});

});
