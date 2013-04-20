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
	'views/layout.report'
],
function(app, Backbone, _, $, Layout, ReportLayout) {

	var reportLayout = new ReportLayout();
	reportLayout.$el.appendTo('body');
	reportLayout.render();

	// Handle browser-resizing.
	var resizeReport = function(){
		reportLayout.trigger('resize', reportLayout.$el.width(), reportLayout.$el.height());
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

	// TODO: When to trigger history handling?
	//Backbone.history.start({ pushState: false });

}/*,

function() {
	// TODO: Handle script errors.
	console.log('Handler:', arguments);
}*/);