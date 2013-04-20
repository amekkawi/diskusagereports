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
	'layout',
	'underscore',
	'text!templates/view.tree.html'
], function(Backbone, Layout, _, template){

	return Layout.extend({

		template: _.template(template),
		tagName: 'div',
		className: 'du-tree du-loading',

		addListeners: function() {
			return this;
		}
	});

});