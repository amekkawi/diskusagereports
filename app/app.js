/*
 * Disk Usage Reports
 * http://diskusagereports.com/
 *
 * Copyright (c) 2013 André Mekkawi <diskusage@andremekkawi.com>
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */
define([
	'module',
	'underscore',
	'router',
	'models/model.settings',
	'models/model.report'
],
function(module, _, Router, ModelSettings, ModelReport) {
	var config = _.extend(
		module.config() || {},
		{
			report: window && window.location
				&& _.isString(window.location.search) && window.location.search.substring(1) || ''
		},
		window && window['reportConfig'] || {}
	);

	return _.extend({
		version: '@@SourceVersion',

		config: config,

		router: new Router(),

		models: {
			settings: new ModelSettings({}, {
				urlRoot: config.reportsBaseURL + config.report + '/',
				suffix: config.suffix
			}),
			report: new ModelReport({}, {
				suffix: config.suffix
			})
		}
	}, Backbone.Events);
});