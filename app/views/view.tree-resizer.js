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
	'layoutmanager',
	'underscore',
	'text!templates/view.tree-resizer.html'
], function(Backbone, Layout, _, template){

	return Backbone.Layout.extend({

		template: _.template(template),
		el: false,

		initialize: function() {

		}
	});

});