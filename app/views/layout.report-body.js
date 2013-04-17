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
	'text!templates/layout.report-body.html'
], function(Backbone, Layout, _, template){

	return Backbone.Layout.extend({

		template: _.template(template),
		el: false,

		initialize: function() {
			this.on("resize", this.resize, this);
		},

		resize: function(maxWidth, maxHeight) {
			var $el = this.$el,
				diff = $el.outerHeight(true) - $el.height();

			$el.height(maxHeight - diff);
		}
	});

});