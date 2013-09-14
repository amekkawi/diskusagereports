/*
 * Disk Usage Reports
 * http://diskusagereports.com/
 *
 * Copyright (c) 2013 André Mekkawi <diskusage@andremekkawi.com>
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */
require([
	'app',
	'backbone',
	'underscore',
	'jquery',
	'layoutmanager',
	'i18n!nls/report',
	'Tabs',
	'views/layout.report'
],
function(app, Backbone, _, $, Layout, lang, Tabs, Report) {

	// ====================================
	// Create the report layout.
	// ====================================

	var config = app.config,
		baseUrl = config.reportsBaseURL,
		reportName = config.report;

	var report = new Report({
		suffix: config.suffix,
		urlRoot: baseUrl + (baseUrl && baseUrl.charAt(baseUrl.length - 1) === '/' ? '' : '/')
				+ reportName + (reportName.charAt(reportName.length - 1) === '/' ? '' : '/')
	});
	report.$el.appendTo('body');
	report.render();
	report.addListeners();

	// ====================================
	// Handle history
	// ====================================

	report.model.on("change", function(model, options){
		if (_.has(model.changed, 'hash') || _.has(model.changed, 'tab') || _.has(model.changed, 'page'))
			if (!_.isNull(model.attributes.hash) && !options.history && !options.root)
				app.router.navigate(model.attributes.hash + '/' + Tabs.lookup[model.attributes.tab] + '/' + model.attributes.page);
	});

	app.router.on("route:directory", function(hash, tab, page) {
		report.set({
			hash: hash,
			tab: tab,
			page: page
		}, { history: true });
	});

	Backbone.history.start({ pushState: false });

	// ====================================
	// Handle browser-resizing.
	// ====================================

	var resizeReport = function(){
		report.resize(report.$el.width(), report.$el.height());
	};
	var resizeThrottle = _.throttle(resizeReport, 200);
	$(window).on("resize", resizeThrottle);

	// Do a resize just in case every 15 seconds.
	var resizeCheck = function() {
		resizeThrottle();
		_.delay(resizeCheck, 15000);
	};
	_.delay(resizeCheck, 15000);

	// Initial resize.
	resizeReport();

	// ====================================
	// Load the report.
	// ====================================

	report.load({
		success: function(models) {

		}
	});

}/*,

function() {
	// TODO: Handle script errors.
	console.log('Handler:', arguments);
}*/);
