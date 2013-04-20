/*
 * Disk Usage Reports
 * http://diskusagereports.com/
 *
 * Copyright (c) 2013 André Mekkawi <diskusage@andremekkawi.com>
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */
define([
	'app',
	'backbone',
	'layout',
	'underscore',
	'text!templates/layout.directory.html',
	'views/view.summary',
	'views/view.tabs'
], function(app, Backbone, Layout, _, template, SummaryView, TabsView){

	var summaryView = new SummaryView({ model: app.models.directory }),
		tabsView = new TabsView({ model: app.models.report });

	return Layout.extend({

		template: _.template(template),
		el: false,

		views: {
			'.du-directory-head': [
				summaryView,
				tabsView
			]
		},

		resize: function(maxWidth, maxHeight) {
			if (!this.$el.is(':visible'))
				return;

			var $el = this.$el,
				diff = $el.outerHeight(true) - $el.height(),
				innerHeight = maxHeight - diff,

				$body = $el.find('>.du-directory-body'),
				bodyTop = $body.position().top,
				bodyDiff = $body.outerHeight(true) - $body.height();

			$el.find('>.du-directory-body').height(innerHeight - bodyDiff - bodyTop);
		},

		addListeners: function() {
			this.getViews().each(function(view){
				view.addListeners();
			});
			return this;
		}
	});

});