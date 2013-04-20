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
	'layoutmanger'
], function(Backbone, Layout){

	return Backbone.Layout.extend({
		tagName: 'div',
		className: 'du-tree-resizer'
	});

});