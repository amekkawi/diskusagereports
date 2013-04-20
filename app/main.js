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
	'views/layout.report'
],
function(app, Backbone, _, $, Layout, lang, ReportLayout) {

	var reportLayout = new ReportLayout({ models: app.models });
	reportLayout.$el.appendTo('body');
	reportLayout.render();
	reportLayout.addListeners();

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

	// Delay the loading message to avoid it from blinking quickly.
	var messageDelay = _.delay(function(){
		app.models.report.set({
			message: lang['message_loading'],
			messageType: 'loading'
		});
	}, 250);

	app.models.settings.fetch({
		success: function(model, response, options) {
			clearTimeout(messageDelay);

			if (!model.isValid()) {
				app.models.report.set({
					message: lang['message_settings_invalid'],
					messageType: 'error'
				});
			}
			else {
				app.models.report.set('message', null);
			}
		},
		error: function(model, response, options){
			clearTimeout(messageDelay);
			app.models.report.set({
				message: _.template(lang['message_settings_' + (response.status || response.statusText)] || lang['message_settings'], { status: (response.status || response.statusText)+'' }),
				messageType: 'error'
			});
		}
	});

	// TODO: When to trigger history handling?
	//Backbone.history.start({ pushState: false });

}/*,

function() {
	// TODO: Handle script errors.
	console.log('Handler:', arguments);
}*/);