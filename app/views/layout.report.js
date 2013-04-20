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
	'views/view.title',
	'views/layout.report-body',
	'views/view.footer'
], function(app, Backbone, Layout, _, template, TitleView, ReportBodyView, FooterView){

	var titleView = new TitleView({ model: app.models.settings }),
		reportBodyView = new ReportBodyView(),
		footerView = new FooterView({ model: app.models.settings });

	return Layout.extend({

		tagName: 'div',
		className: 'du-report du-loading',

		views: {
			'': [
				titleView,
				reportBodyView,
				footerView
			]
		},

		initialize: function() {
			this.on("resize", this.resize, this);
		},

		resize: function(maxWidth, maxHeight) {
			reportBodyView.resize(maxWidth, maxHeight - titleView.$el.outerHeight(true) - footerView.$el.outerHeight(true));
		},

		addListeners: function() {
			this.getViews().each(function(view){
				view.addListeners();
			});
			return this;
		}
	});

});